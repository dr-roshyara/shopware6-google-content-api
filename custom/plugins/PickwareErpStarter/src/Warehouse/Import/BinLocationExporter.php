<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Warehouse\Import;

use Pickware\DalBundle\CriteriaJsonSerializer;
use Pickware\DalBundle\EntityManager;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\PickwareErpStarter\ImportExport\CsvErrorFactory;
use Pickware\PickwareErpStarter\ImportExport\Exporter;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportElementDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportEntity;
use Pickware\PickwareErpStarter\Translation\Translator;
use Pickware\PickwareErpStarter\Warehouse\Model\BinLocationDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

class BinLocationExporter implements Exporter
{
    public const TECHNICAL_NAME = 'bin-location';

    public const COLUMN_CODE = 'code';

    public const COLUMNS = [
        self::COLUMN_CODE,
    ];

    private EntityManager $entityManager;
    private CriteriaJsonSerializer $criteriaJsonSerializer;
    private Translator $translator;
    private int $batchSize;

    public function __construct(
        EntityManager $entityManager,
        CriteriaJsonSerializer $criteriaJsonSerializer,
        Translator $translator,
        int $batchSize
    ) {
        $this->entityManager = $entityManager;
        $this->criteriaJsonSerializer = $criteriaJsonSerializer;
        $this->translator = $translator;
        $this->batchSize = $batchSize;
    }

    public function exportChunk(string $exportId, int $nextRowNumberToWrite, Context $context): ?int
    {
        /** @var ImportExportEntity $export */
        $export = $this->entityManager->findByPrimaryKey(ImportExportDefinition::class, $exportId, $context);
        $exportConfig = $export->getConfig();

        $criteria = $this->criteriaJsonSerializer->deserializeFromArray(
            $exportConfig['criteria'],
            $this->getEntityDefinitionClassName(),
        );

        // Retrieve the next batch of matching results. Reminder: row number starts with 1.
        $criteria->setLimit($this->batchSize);
        $criteria->setOffset($nextRowNumberToWrite - 1);

        $exportRows = array_values($this->entityManager->findBy(
            BinLocationDefinition::class,
            $criteria,
            $context,
        )->getElements());

        $exportElementPayloads = [];
        foreach ($exportRows as $index => $exportRow) {
            $exportElementPayloads[] = [
                'id' => Uuid::randomHex(),
                'importExportId' => $exportId,
                'rowNumber' => $nextRowNumberToWrite + $index,
                'rowData' => [
                    'code' => $exportRow->getCode(),
                ],
            ];
        }

        $this->entityManager->create(
            ImportExportElementDefinition::class,
            $exportElementPayloads,
            $context,
        );
        $nextRowNumberToWrite += $this->batchSize;

        if (count($exportRows) < $this->batchSize) {
            return null;
        }

        return $nextRowNumberToWrite;
    }

    public function getEntityDefinitionClassName(): string
    {
        return BinLocationDefinition::class;
    }

    public function getCsvFileName(string $importExportId, string $locale, Context $context): string
    {
        /** @var ImportExportEntity $export */
        $export = $this->entityManager->findByPrimaryKey(ImportExportDefinition::class, $importExportId, $context);
        $this->translator->setTranslationLocale($locale, $context);

        return sprintf(
            $this->translator->translate('pickware-erp-starter.bin-location-export.file-name') . '.csv',
            $export->getCreatedAt()->format('Y-m-d H_i_s'),
        );
    }

    public function validateConfig(array $config): JsonApiErrors
    {
        $errors = new JsonApiErrors();
        $columns = $config['columns'] ?? [];

        foreach ($columns as $invalidColumn) {
            $errors->addError(CsvErrorFactory::invalidColumn($invalidColumn));
        }

        return $errors;
    }
}
