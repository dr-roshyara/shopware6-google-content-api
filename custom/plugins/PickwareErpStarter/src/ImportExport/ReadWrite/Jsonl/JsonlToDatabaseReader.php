<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\ImportExport\ReadWrite\Jsonl;

use DateTime;
use Franzose\DoctrineBulkInsert\Query;
use League\Flysystem\FilesystemInterface;
use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportEntity;
use Pickware\PickwareErpStarter\ImportExport\ReadWrite\FileReader;
use Pickware\PickwareErpStarter\ImportExport\ReadWrite\ImportExportReader;
use Pickware\PickwareErpStarter\ImportExport\ReadWrite\ReadingOffset;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

class JsonlToDatabaseReader implements ImportExportReader, FileReader
{
    public const TECHNICAL_NAME = 'jsonl';

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
        $import = $this->entityManager->findByPrimaryKey(
            ImportExportDefinition::class,
            $importId,
            $context,
            ['document'],
        );

        $jsonlReader = new JsonlReader();
        $jsonlStream = $this->documentBundleFileSystem->readStream($import->getDocument()->getPathInPrivateFileSystem());

        $payload = [];
        foreach ($jsonlReader->read($jsonlStream, $offset->getNextByteToRead()) as $rowData) {
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

        $offset->setNextByteToRead(ftell($jsonlStream));
        if (feof($jsonlStream)) {
            return null;
        }

        return $offset;
    }

    public function getSupportedMimetype(): string
    {
        return FileReader::MIMETYPE_JSONL;
    }

    public function getTechnicalName(): string
    {
        return self::TECHNICAL_NAME;
    }
}
