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

use League\Flysystem\FilesystemInterface;
use Pickware\DalBundle\EntityManager;
use Pickware\DocumentBundle\Model\DocumentDefinition;
use Pickware\DocumentBundle\Model\DocumentEntity;
use Pickware\PickwareErpStarter\ImportExport\DependencyInjection\ExporterRegistry;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportElementDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportEntity;
use Pickware\PickwareErpStarter\PickwareErpStarter;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;

class DatabaseToCsvWriter
{
    private EntityManager $entityManager;
    private FilesystemInterface $fileSystem;
    private ExporterRegistry $exporterRegistry;
    private int $batchSize;

    public function __construct(
        EntityManager $entityManager,
        FilesystemInterface $fileSystem,
        ExporterRegistry $exporterRegistry,
        int $batchSize
    ) {
        $this->entityManager = $entityManager;
        $this->fileSystem = $fileSystem;
        $this->exporterRegistry = $exporterRegistry;
        $this->batchSize = $batchSize;
    }

    /**
     * @param int $nextRowNumberToWrite Row number of the first element of the current batch.
     * @return ?int
     */
    public function writeChunk(string $exportId, int $nextRowNumberToWrite, Context $context): ?int
    {
        /** @var ImportExportEntity $export */
        $export = $this->entityManager->findByPrimaryKey(
            ImportExportDefinition::class,
            $exportId,
            $context,
            ['document'],
        );

        $criteria = EntityManager::createCriteriaFromArray(['importExportId' => $exportId]);
        $criteria->addFilter(new RangeFilter('rowNumber', [
            RangeFilter::GTE => $nextRowNumberToWrite,
            RangeFilter::LT => $nextRowNumberToWrite + $this->batchSize,
        ]));
        $criteria->addSorting(new FieldSorting('rowNumber', FieldSorting::ASCENDING));
        $elements = $this->entityManager->findBy(ImportExportElementDefinition::class, $criteria, $context);

        $document = $export->getDocument() ?? $this->createCsvDocument($export, $context);
        $path = $this->downloadDocumentFromFilesystem($document);

        try {
            $csvWriter = new CsvWriter($path);

            if ($nextRowNumberToWrite === 1 && count($elements) > 0) {
                $csvWriter->writeHeader($elements->first()->getRowData());
            }

            foreach ($elements as $element) {
                $csvWriter->append($element->getRowData());
                $nextRowNumberToWrite = $nextRowNumberToWrite + 1;
            }
        } finally {
            $csvWriter->close();
        }

        $this->uploadDocumentToFilesystem($document, $path, $context);

        if (count($elements) < $this->batchSize) {
            return null;
        }

        return $nextRowNumberToWrite;
    }

    private function createCsvDocument(ImportExportEntity $export, Context $context): DocumentEntity
    {
        $documentId = Uuid::randomHex();
        $filePath = sprintf('/documents/%s', $documentId);

        $exporter = $this->exporterRegistry->getExporterByTechnicalName($export->getProfileTechnicalName());
        $fileName = $exporter->getCsvFileName($export->getId(), $export->getConfig()['locale'], $context);

        $payload = [
            'id' => $documentId,
            'fileSizeInBytes' => 0,
            'documentTypeTechnicalName' => PickwareErpStarter::DOCUMENT_TYPE_TECHNICAL_NAME_EXPORT,
            'mimeType' => 'text/csv',
            'fileName' => $fileName,
            'pathInPrivateFileSystem' => $filePath,
        ];

        $this->entityManager->create(DocumentDefinition::class, [$payload], $context);

        /** @var DocumentEntity $documentEntity */
        $documentEntity = $this->entityManager->findOneBy(
            DocumentDefinition::class,
            EntityManager::createCriteriaFromArray(['id' => $documentId]),
            $context,
        );

        $this->entityManager->update(
            ImportExportDefinition::class,
            [
                [
                    'id' => $export->getId(),
                    'documentId' => $documentId,
                ],
            ],
            $context,
        );

        $export->setDocument($documentEntity);

        return $documentEntity;
    }

    private function uploadDocumentToFilesystem(DocumentEntity $document, string $path, Context $context): void
    {
        $readStream = fopen($path, 'rb');
        // Adding metadata for i.e. Google cloud storage to prohibit caching of the object
        $this->fileSystem->putStream($document->getPathInPrivateFileSystem(), $readStream, [
            'metadata' => [
                'cacheControl' => 'public, max-age=0',
            ],
        ]);
        if (is_resource($readStream)) {
            fclose($readStream);
        }

        $this->entityManager->update(
            DocumentDefinition::class,
            [
                [
                    'id' => $document->getId(),
                    'fileSizeInBytes' => $this->fileSystem->getSize($document->getPathInPrivateFileSystem()),
                ],
            ],
            $context,
        );

        unlink($path);
    }

    private function downloadDocumentFromFilesystem(DocumentEntity $document): string
    {
        $tempFilePath = tempnam(sys_get_temp_dir(), '');

        if ($this->fileSystem->has($document->getPathInPrivateFileSystem())) {
            $readStream = $this->fileSystem->readStream($document->getPathInPrivateFileSystem());
            $writeStream = fopen($tempFilePath, 'wb');
            stream_copy_to_stream($readStream, $writeStream);
            fclose($writeStream);
        }

        return $tempFilePath;
    }
}
