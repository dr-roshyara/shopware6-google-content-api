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

use Pickware\DalBundle\EntityManager;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\PickwareErpStarter\ImportExport\Exception\ImportException;
use Pickware\PickwareErpStarter\ImportExport\Exception\ImportExportException;
use Pickware\PickwareErpStarter\ImportExport\Importer;
use Pickware\PickwareErpStarter\ImportExport\ImportExportStateService;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportElementCollection;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportElementDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportElementEntity;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportEntity;
use Pickware\PickwareErpStarter\ImportExport\ReadWrite\Csv\Validator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Throwable;

class BinLocationImporter implements Importer
{
    public const TECHNICAL_NAME = 'bin-location';
    public const VALIDATION_SCHEMA = [
        '$id' => 'pickware-erp--import-export--bin-location-import',
        'type' => 'object',
        'properties' => [
            'code' => [
                'type' => 'string',
                'maxLength' => 255,
            ],
        ],
        'required' => ['code'],
    ];

    public const CONFIG_KEY_WAREHOUSE_ID = 'warehouseId';

    private EntityManager $entityManager;
    private BinLocationImportCsvRowNormalizer $normalizer;
    private BinLocationUpsertService $binLocationUpsertService;
    private ImportExportStateService $importExportStateService;
    private int $batchSize;

    private Validator $validator;

    public function __construct(
        EntityManager $entityManager,
        BinLocationImportCsvRowNormalizer $normalizer,
        BinLocationUpsertService $binLocationUpsertService,
        ImportExportStateService $importExportStateService,
        int $batchSize
    ) {
        $this->entityManager = $entityManager;
        $this->normalizer = $normalizer;
        $this->binLocationUpsertService = $binLocationUpsertService;
        $this->importExportStateService = $importExportStateService;
        $this->batchSize = $batchSize;

        $this->validator = new Validator($normalizer, self::VALIDATION_SCHEMA);
    }

    public function validateHeaderRow(array $headerRow, Context $context): JsonApiErrors
    {
        return $this->validator->validateHeaderRow($headerRow, $context);
    }

    public function importChunk(string $importId, int $nextRowNumberToRead, Context $context): ?int
    {
        /** @var ImportExportEntity $import */
        $import = $this->entityManager->findByPrimaryKey(
            ImportExportDefinition::class,
            $importId,
            $context,
        );
        $config = $import->getConfig();

        $criteria = EntityManager::createCriteriaFromArray(['importExportId' => $importId]);
        $criteria->addFilter(new RangeFilter('rowNumber', [
            RangeFilter::GTE => $nextRowNumberToRead,
            RangeFilter::LT => $nextRowNumberToRead + $this->batchSize,
        ]));

        /** @var ImportExportElementCollection $importElements */
        $importElements = $this->entityManager->findBy(
            ImportExportElementDefinition::class,
            $criteria,
            $context,
        );

        if ($importElements->count() === 0) {
            return null;
        }

        $normalizedRows = $importElements->map(fn (ImportExportElementEntity $importElement) => $this->normalizer->normalizeRow($importElement->getRowData()));
        $originalColumnNamesByNormalizedColumnNames = $this->normalizer->mapNormalizedToOriginalColumnNames(array_keys(
            $importElements->first()->getRowData(),
        ));

        $importedCodes = [];
        foreach ($importElements->getElements() as $index => $importElement) {
            $normalizedRow = $normalizedRows[$index];

            $errors = $this->validator->validateRow($normalizedRow, $originalColumnNamesByNormalizedColumnNames);
            if ($this->failOnErrors($importElement->getId(), $errors, $context)) {
                continue;
            }

            $code = trim($normalizedRow['code']);
            if ($code === '') {
                continue;
            }

            $importedCodes[] = $code;
        }

        try {
            $this->binLocationUpsertService->upsertBinLocations(
                $importedCodes,
                $config[self::CONFIG_KEY_WAREHOUSE_ID],
                $context,
            );
        } catch (Throwable $exception) {
            throw ImportException::batchImportError($exception, $nextRowNumberToRead, $this->batchSize);
        }

        if ($importElements->count() < $this->batchSize) {
            return null;
        }

        $nextRowNumberToRead += $this->batchSize;

        return $nextRowNumberToRead;
    }

    private function failOnErrors(string $importElementId, JsonApiErrors $errors, Context $context): bool
    {
        if (count($errors) > 0) {
            $this->importExportStateService->failImportExportElement($importElementId, $errors, $context);

            return true;
        }

        return false;
    }

    public function validateConfig(array $config): JsonApiErrors
    {
        if (!isset($config[self::CONFIG_KEY_WAREHOUSE_ID]) || $config[self::CONFIG_KEY_WAREHOUSE_ID] === '') {
            return new JsonApiErrors([
                ImportExportException::createConfigParameterMissingError(self::CONFIG_KEY_WAREHOUSE_ID),
            ]);
        }

        return JsonApiErrors::noError();
    }
}
