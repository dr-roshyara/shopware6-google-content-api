<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\ImportExport;

use Doctrine\DBAL\Connection;
use Pickware\DalBundle\EntityManager;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\PickwareErpStarter\ImportExport\DependencyInjection\ExporterRegistry;
use Pickware\PickwareErpStarter\ImportExport\DependencyInjection\ImporterRegistry;
use Pickware\PickwareErpStarter\ImportExport\Exception\ImportException;
use Pickware\PickwareErpStarter\ImportExport\Exception\ImportExportException;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportEntity;
use Pickware\PickwareErpStarter\ImportExport\ReadWrite\Csv\DatabaseToCsvWriter;
use Pickware\PickwareErpStarter\ImportExport\ReadWrite\DependencyInjection\ImportExportReaderRegistry;
use Pickware\PickwareErpStarter\ImportExport\ReadWrite\FileReader;
use Pickware\PickwareErpStarter\ImportExport\ReadWrite\HeaderReader;
use Pickware\PickwareErpStarter\ImportExport\ReadWrite\ReadingOffset;
use Throwable;

class ImportExportSchedulerMessageHandler
{
    private ImportExportStateService $importExportStateService;
    private DatabaseToCsvWriter $databaseToCsvWriter;
    private EntityManager $entityManager;
    private Connection $db;
    private ImporterRegistry $importerRegistry;
    private ExporterRegistry $exporterRegistry;
    private ImportExportReaderRegistry $importExportReaderRegistry;

    public function __construct(
        ImportExportStateService $importExportStateService,
        DatabaseToCsvWriter $databaseToCsvWriter,
        EntityManager $entityManager,
        Connection $db,
        ImporterRegistry $importerRegistry,
        ExporterRegistry $exporterRegistry,
        ImportExportReaderRegistry $importExportReaderRegistry
    ) {
        $this->importExportStateService = $importExportStateService;
        $this->databaseToCsvWriter = $databaseToCsvWriter;
        $this->entityManager = $entityManager;
        $this->db = $db;
        $this->importerRegistry = $importerRegistry;
        $this->exporterRegistry = $exporterRegistry;
        $this->importExportReaderRegistry = $importExportReaderRegistry;
    }

    public function handleFileValidationMessage(ImportExportSchedulerMessage $message): ?ImportExportSchedulerMessage
    {
        $this->importExportStateService->validate($message->getImportExportId(), $message->getContext());
        $import = $this->getImportExportFromMessage($message, ['document']);

        $errors = JsonApiErrors::noError();
        if (!($import->getConfig()[ImportExportDefinition::READER_TECHNICAL_NAME_CONFIG_KEY] ?? null)) {
            $errors->addErrors(ImportExportException::createReaderTechnicalNameNotSetError());
        }

        if (count($errors) !== 0) {
            $this->importExportStateService->fail($message->getImportExportId(), $errors, $message->getContext());

            return null;
        }

        $importExportReader = $this->importExportReaderRegistry->getImportExportReaderByTechnicalName(
            $import->getConfig()[ImportExportDefinition::READER_TECHNICAL_NAME_CONFIG_KEY],
        );

        if ($importExportReader instanceof HeaderReader) {
            $header = $importExportReader->getHeader($import->getId(), $message->getContext());

            $errors->addErrors(
                ...$this->importerRegistry
                ->getImporterByTechnicalName($import->getProfileTechnicalName())
                ->validateHeaderRow($header, $message->getContext())
                ->getErrors(),
            );
        }

        if ($importExportReader instanceof FileReader) {
            if ($import->getDocument() === null) {
                $errors->addErrors(ImportExportException::createFileReaderWithoutDocumentError(
                    $importExportReader->getTechnicalName(),
                ));
            } elseif ($import->getDocument()->getMimeType() !== $importExportReader->getSupportedMimetype()) {
                $errors->addErrors(ImportExportException::createMimetypeMismatchError(
                    $importExportReader->getSupportedMimetype(),
                    $import->getDocument()->getMimeType(),
                ));
            }
        } else {
            if ($import->getDocument() !== null) {
                $errors->addErrors(ImportExportException::createDocumentWithoutFileReaderError(
                    $importExportReader->getTechnicalName(),
                ));
            }
        }

        if (count($errors) !== 0) {
            $this->importExportStateService->fail($message->getImportExportId(), $errors, $message->getContext());

            return null;
        }

        return new ImportExportSchedulerMessage(
            $message->getImportExportId(),
            ImportExportSchedulerMessage::STATE_READ_FILE_TO_DATABASE,
            $message->getContext(),
        );
    }

    public function handleReadFileToDatabaseMessage(ImportExportSchedulerMessage $message): ?ImportExportSchedulerMessage
    {
        $import = $this->getImportExportFromMessage($message, ['document']);
        if ($import->getStateData() === []) {
            // The first row number that is written to the db is 1 because the header row will not be written. But it
            // must be read and therefore the nextByteToRead starts with 0.
            $initialStateData = new ReadingOffset(1, 0);
            $this->importExportStateService->readFile(
                $message->getImportExportId(),
                $initialStateData,
                $message->getContext(),
            );
            $import->setStateData($initialStateData->jsonSerialize());
        }

        $newOffset = $this->importExportReaderRegistry
            ->getImportExportReaderByTechnicalName($import->getConfig()[ImportExportDefinition::READER_TECHNICAL_NAME_CONFIG_KEY])
            ->readChunk(
                $message->getImportExportId(),
                ReadingOffset::fromArray($import->getStateData()),
                $message->getContext(),
            );
        if ($newOffset === null) {
            $this->importExportStateService->resetStateData($message->getImportExportId(), $message->getContext());

            // When no new offset was returned, we consider the reading to be finished.
            return new ImportExportSchedulerMessage(
                $message->getImportExportId(),
                ImportExportSchedulerMessage::STATE_EXECUTE_IMPORT,
                $message->getContext(),
            );
        }
        // Otherwise, the import is progressed with the new offset
        $this->importExportStateService->readFile(
            $message->getImportExportId(),
            $newOffset,
            $message->getContext(),
        );

        return new ImportExportSchedulerMessage(
            $message->getImportExportId(),
            ImportExportSchedulerMessage::STATE_READ_FILE_TO_DATABASE,
            $message->getContext(),
        );
    }

    public function handleExecuteImportMessage(ImportExportSchedulerMessage $message): ?ImportExportSchedulerMessage
    {
        $import = $this->getImportExportFromMessage($message);
        if ($import->getStateData() === []) {
            $rowCount = $this->getImportExportRowCount($message->getImportExportId());
            $initialStateData = ['nextRowNumberToRead' => 1];
            $this->importExportStateService->startRun(
                $message->getImportExportId(),
                $rowCount,
                $initialStateData,
                $message->getContext(),
            );
            $import->setStateData($initialStateData);
        }

        $importer = $this->importerRegistry->getImporterByTechnicalName($import->getProfileTechnicalName());
        $nextRowNumberToRead = $import->getStateData()['nextRowNumberToRead'];
        try {
            $newNextRowNumberToRead = $importer->importChunk(
                $import->getId(),
                $nextRowNumberToRead,
                $message->getContext(),
            );
        } catch (ImportException $exception) {
            $this->importExportStateService->fail(
                $import->getId(),
                new JsonApiErrors([$exception->getJsonApiError()]),
                $message->getContext(),
            );

            return null;
        } catch (Throwable $exception) {
            $this->importExportStateService->fail(
                $import->getId(),
                new JsonApiErrors([
                    ImportException::unknownError($exception, $nextRowNumberToRead)->getJsonApiError(),
                ]),
                $message->getContext(),
            );

            return null;
        }

        if ($newNextRowNumberToRead === null) {
            // When no new row number was returned, we consider this import to be finished.
            $this->importExportStateService->finish($import->getId(), $message->getContext());

            return null;
        }
        // Otherwise, the import is progressed up until (not including) the new next row number
        $this->importExportStateService->progressRun(
            $import->getId(),
            $newNextRowNumberToRead - 1,
            ['nextRowNumberToRead' => $newNextRowNumberToRead],
            $message->getContext(),
        );

        return new ImportExportSchedulerMessage(
            $message->getImportExportId(),
            ImportExportSchedulerMessage::STATE_EXECUTE_IMPORT,
            $message->getContext(),
        );
    }

    public function handleExecuteExportMessage(ImportExportSchedulerMessage $message): ?ImportExportSchedulerMessage
    {
        $export = $this->getImportExportFromMessage($message);
        if ($export->getStateData() === []) {
            $initialStateData = ['nextRowNumberToWriteToDatabase' => 1];
            $this->importExportStateService->startRun(
                $message->getImportExportId(),
                $export->getConfig()['totalCount'],
                $initialStateData,
                $message->getContext(),
            );
            $export->setStateData($initialStateData);
        }

        $exporter = $this->exporterRegistry->getExporterByTechnicalName($export->getProfileTechnicalName());
        $newNextRowNumberToWrite = $exporter->exportChunk(
            $export->getId(),
            $export->getStateData()['nextRowNumberToWriteToDatabase'],
            $message->getContext(),
        );

        if ($newNextRowNumberToWrite === null) {
            $this->importExportStateService->resetStateData($export->getId(), $message->getContext());

            // When no new row number was returned, we consider this export to be finished. Continue with writing the
            // csv file.
            return new ImportExportSchedulerMessage(
                $message->getImportExportId(),
                ImportExportSchedulerMessage::STATE_WRITE_DATABASE_TO_CSV,
                $message->getContext(),
            );
        }
        // Otherwise, the export is progressed up until (not including) the new next row number
        $this->importExportStateService->progressRun(
            $export->getId(),
            $newNextRowNumberToWrite - 1,
            ['nextRowNumberToWriteToDatabase' => $newNextRowNumberToWrite],
            $message->getContext(),
        );

        return new ImportExportSchedulerMessage(
            $message->getImportExportId(),
            ImportExportSchedulerMessage::STATE_EXECUTE_EXPORT,
            $message->getContext(),
        );
    }

    public function handleWriteDatabaseToCsvMessage(
        ImportExportSchedulerMessage $message
    ): ?ImportExportSchedulerMessage {
        $export = $this->getImportExportFromMessage($message);
        if ($export->getStateData() === []) {
            $initialStateData = ['nextRowNumberToWriteToCsv' => 1];
            $rowCount = $this->getImportExportRowCount($message->getImportExportId());
            $this->importExportStateService->writeFile(
                $message->getImportExportId(),
                0,
                $rowCount,
                $initialStateData,
                $message->getContext(),
            );
            $export->setStateData($initialStateData);
        }

        $newNextRowNumberToWrite = $this->databaseToCsvWriter->writeChunk(
            $message->getImportExportId(),
            $export->getStateData()['nextRowNumberToWriteToCsv'],
            $message->getContext(),
        );

        if ($newNextRowNumberToWrite === null) {
            // When no new row number was returned, we consider this export to be finished.
            $this->importExportStateService->finish($message->getImportExportId(), $message->getContext());

            return null;
        }
        // Otherwise, the export is progressed with the new row number
        $this->importExportStateService->writeFile(
            $message->getImportExportId(),
            $newNextRowNumberToWrite - 1,
            $export->getConfig()['totalCount'],
            ['nextRowNumberToWriteToCsv' => $newNextRowNumberToWrite],
            $message->getContext(),
        );

        return new ImportExportSchedulerMessage(
            $message->getImportExportId(),
            ImportExportSchedulerMessage::STATE_WRITE_DATABASE_TO_CSV,
            $message->getContext(),
        );
    }

    private function getImportExportFromMessage(
        ImportExportSchedulerMessage $message,
        array $associations = []
    ): ImportExportEntity {
        return $this->entityManager->getByPrimaryKey(
            ImportExportDefinition::class,
            $message->getImportExportId(),
            $message->getContext(),
            $associations,
        );
    }

    private function getImportExportRowCount(string $importExportId): int
    {
        return (int) $this->db->fetchOne(
            'SELECT COUNT(`id`)
            FROM `pickware_erp_import_export_element`
            WHERE `import_export_id` = :importExportId',
            ['importExportId' => hex2bin($importExportId)],
        );
    }
}
