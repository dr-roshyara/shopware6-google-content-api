<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\ImportExport\ReadWrite\Csv;

use DateTime;
use Franzose\DoctrineBulkInsert\Query;
use League\Flysystem\FilesystemInterface;
use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportEntity;
use Pickware\PickwareErpStarter\ImportExport\ReadWrite\FileReader;
use Pickware\PickwareErpStarter\ImportExport\ReadWrite\HeaderReader;
use Pickware\PickwareErpStarter\ImportExport\ReadWrite\ImportExportReader;
use Pickware\PickwareErpStarter\ImportExport\ReadWrite\ReadingOffset;
use Shopware\Core\Content\ImportExport\Processing\Reader\CsvReader;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

class CsvToDatabaseReader implements ImportExportReader, FileReader, HeaderReader
{
    public const TECHNICAL_NAME = 'csv';
    private const BOM_UTF8 = "\xEF\xBB\xBF";

    private EntityManager $entityManager;
    private FilesystemInterface $documentBundleFileSystem;
    private Query $bulkInserter;
    private int $batchSize;

    public function __construct(
        EntityManager $entityManager,
        FilesystemInterface $documentBundleFileSystem,
        Query $bulkInserter,
        int $batchSize
    ) {
        $this->entityManager = $entityManager;
        $this->documentBundleFileSystem = $documentBundleFileSystem;
        $this->bulkInserter = $bulkInserter;
        $this->batchSize = $batchSize;
    }

    public function readChunk(string $importId, ReadingOffset $offset, Context $context): ?ReadingOffset
    {
        /** @var ImportExportEntity $import */
        $import = $this->entityManager->getByPrimaryKey(
            ImportExportDefinition::class,
            $importId,
            $context,
            ['document'],
        );

        $csvReader = new CsvReader(';', '"', '\\', true);
        $csvStream = $this->documentBundleFileSystem->readStream($import->getDocument()->getPathInPrivateFileSystem());

        $payload = [];
        $csvIterator = $csvReader->read(new Config([], []), $csvStream, $offset->getNextByteToRead());
        foreach ($csvIterator as $rowData) {
            $payload[] = [
                'id' => Uuid::randomBytes(),
                'import_export_id' => hex2bin($import->getId()),
                'row_number' => $offset->getNextRowNumberToWrite(),
                'row_data' => json_encode($rowData),
                'created_at' => (new DateTime())->format('Y-m-d H:i:s.u'),
            ];
            $offset->setNextRowNumberToWrite($offset->getNextRowNumberToWrite() + 1);
            if (count($payload) >= $this->batchSize) {
                break;
            }
        }
        $this->bulkInserter->execute('pickware_erp_import_export_element', $payload);

        $offset->setNextByteToRead(ftell($csvStream));
        if (feof($csvStream)) {
            return null;
        }

        return $offset;
    }

    public function getHeader(string $importId, Context $context): array
    {
        /** @var ImportExportEntity $import */
        $import = $this->entityManager->getByPrimaryKey(
            ImportExportDefinition::class,
            $importId,
            $context,
            ['document'],
        );
        $stream = $this->documentBundleFileSystem->readStream($import->getDocument()->getPathInPrivateFileSystem());
        $this->skipOverPossibleBom($stream);

        // The max read length value to avoid memory limits and timeouts for the case that a big file is
        // uploaded that does not have line breaks. This can happen when the user accidentally uploads a wrong file
        // format.
        return fgetcsv($stream, 8 * 1024, ';', '"', '\\');
    }

    public function getSupportedMimetype(): string
    {
        return FileReader::MIMETYPE_CSV;
    }

    public function getTechnicalName(): string
    {
        return self::TECHNICAL_NAME;
    }

    private function skipOverPossibleBom($stream): void
    {
        $maybeBom = fread($stream, 3);

        if ($maybeBom !== self::BOM_UTF8) {
            fseek($stream, 0, SEEK_SET);
        }
    }
}
