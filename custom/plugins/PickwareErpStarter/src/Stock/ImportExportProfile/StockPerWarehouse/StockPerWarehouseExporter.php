<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stock\ImportExportProfile\StockPerWarehouse;

use Pickware\DalBundle\CriteriaJsonSerializer;
use Pickware\DalBundle\EntityManager;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\PickwareErpStarter\ImportExport\CsvErrorFactory;
use Pickware\PickwareErpStarter\ImportExport\Exporter;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportElementDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportEntity;
use Pickware\PickwareErpStarter\Product\ProductNameFormatterService;
use Pickware\PickwareErpStarter\Stock\Model\WarehouseStockDefinition;
use Pickware\PickwareErpStarter\Stock\Model\WarehouseStockEntity;
use Pickware\PickwareErpStarter\Translation\Translator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;

class StockPerWarehouseExporter implements Exporter
{
    public const TECHNICAL_NAME = 'stock-per-warehouse';

    public const COLUMN_PRODUCT_NUMBER = 'product.productNumber';
    public const COLUMN_PRODUCT_NAME = 'product.name';
    public const COLUMN_WAREHOUSE_NAME = 'warehouse.name';
    public const COLUMN_WAREHOUSE_CODE = 'warehouse.code';
    public const COLUMN_CHANGE = 'change';
    public const COLUMN_STOCK = 'quantity';

    public const COLUMNS = [
        self::COLUMN_PRODUCT_NUMBER,
        self::COLUMN_PRODUCT_NAME,
        self::COLUMN_WAREHOUSE_NAME,
        self::COLUMN_WAREHOUSE_CODE,
        self::COLUMN_CHANGE,
        self::COLUMN_STOCK,
    ];

    public const DEFAULT_COLUMNS = [
        self::COLUMN_PRODUCT_NAME,
        self::COLUMN_PRODUCT_NUMBER,
        self::COLUMN_WAREHOUSE_NAME,
        self::COLUMN_WAREHOUSE_CODE,
    ];

    public const COLUMN_TRANSLATIONS = [
        self::COLUMN_PRODUCT_NAME => 'pickware-erp-starter.stock-export.columns.product-name',
        self::COLUMN_PRODUCT_NUMBER => 'pickware-erp-starter.stock-export.columns.product-number',
        self::COLUMN_WAREHOUSE_NAME => 'pickware-erp-starter.stock-export.columns.warehouse-name',
        self::COLUMN_WAREHOUSE_CODE => 'pickware-erp-starter.stock-export.columns.warehouse-code',
        self::COLUMN_STOCK => 'pickware-erp-starter.stock-export.columns.stock',
        self::COLUMN_CHANGE => 'pickware-erp-starter.stock-export.columns.change',
    ];

    private EntityManager $entityManager;
    private int $batchSize;
    private Translator $translator;
    private ProductNameFormatterService $productNameFormatterService;
    private CriteriaJsonSerializer $criteriaJsonSerializer;

    public function __construct(
        EntityManager $entityManager,
        CriteriaJsonSerializer $criteriaJsonSerializer,
        Translator $translator,
        ProductNameFormatterService $productNameFormatterService,
        int $batchSize
    ) {
        $this->entityManager = $entityManager;
        $this->criteriaJsonSerializer = $criteriaJsonSerializer;
        $this->batchSize = $batchSize;
        $this->translator = $translator;
        $this->productNameFormatterService = $productNameFormatterService;
    }

    public function exportChunk(string $exportId, int $nextRowNumberToWrite, Context $context): ?int
    {
        /** @var ImportExportEntity $export */
        $export = $this->entityManager->findByPrimaryKey(ImportExportDefinition::class, $exportId, $context);
        $exportConfig = $export->getConfig();
        $columns = $exportConfig['columns'] ?? self::DEFAULT_COLUMNS;

        $criteria = $this->criteriaJsonSerializer->deserializeFromArray(
            $exportConfig['criteria'],
            $this->getEntityDefinitionClassName(),
        );

        // Retrieve the next batch of matching results. Reminder: row number starts with 1.
        $criteria->setLimit($this->batchSize);
        $criteria->setOffset($nextRowNumberToWrite - 1);

        $exportRows = $this->getStockOverviewPerWarehouseExportRows(
            $criteria,
            $exportConfig['locale'],
            $exportConfig['exportStockValues'],
            $columns,
            $context,
        );

        $exportElementPayloads = [];
        foreach ($exportRows as $index => $exportRow) {
            $exportElementPayloads[] = [
                'id' => Uuid::randomHex(),
                'importExportId' => $exportId,
                'rowNumber' => $nextRowNumberToWrite + $index,
                'rowData' => $exportRow,
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
        return WarehouseStockDefinition::class;
    }

    public function getCsvFileName(string $importExportId, string $locale, Context $context): string
    {
        /** @var ImportExportEntity $export */
        $export = $this->entityManager->findByPrimaryKey(ImportExportDefinition::class, $importExportId, $context);
        $this->translator->setTranslationLocale($locale, $context);

        return sprintf(
            $this->translator->translate('pickware-erp-starter.stock-export.file-name') . '.csv',
            $export->getCreatedAt()->format('Y-m-d H_i_s'),
        );
    }

    public function validateConfig(array $config): JsonApiErrors
    {
        $errors = new JsonApiErrors();
        $columns = $config['columns'] ?? [];

        $invalidColumns = array_diff($columns, self::COLUMNS);
        foreach ($invalidColumns as $invalidColumn) {
            $errors->addError(CsvErrorFactory::invalidColumn($invalidColumn));
        }

        return $errors;
    }

    /**
     * @param Criteria $criteria Only filters, sorting, limit and offset are respected
     */
    private function getStockOverviewPerWarehouseExportRows(
        Criteria $criteria,
        string $locale,
        bool $exportStockValues,
        array $columns,
        Context $context
    ): array {
        $csvHeaderTranslations = $this->getCsvHeaderTranslations($locale, $context);

        $warehouseStocks = $context->enableInheritance(function (Context $inheritanceContext) use ($criteria) {
            return $this->entityManager->findBy(
                WarehouseStockDefinition::class,
                EntityManager::sanitizeCriteria($criteria),
                $inheritanceContext,
                [
                    'product.options',
                    'warehouse',
                ],
            );
        });

        $productIds = $warehouseStocks->map(fn (WarehouseStockEntity $warehouseStock) => $warehouseStock->getProduct()->getId());
        $productNames = $this->productNameFormatterService->getFormattedProductNames(
            $productIds,
            [],
            $context,
        );

        $rows = [];
        /** @var WarehouseStockEntity $warehouseStock */
        foreach ($warehouseStocks as $warehouseStock) {
            $warehouse = $warehouseStock->getWarehouse();
            $columnValues = [
                self::COLUMN_PRODUCT_NAME => $productNames[$warehouseStock->getProduct()->getId()],
                self::COLUMN_PRODUCT_NUMBER => $warehouseStock->getProduct()->getProductNumber(),
                self::COLUMN_WAREHOUSE_NAME => $warehouse ? $warehouse->getName() : '',
                self::COLUMN_WAREHOUSE_CODE => $warehouse ? $warehouse->getCode() : '',
                self::COLUMN_STOCK => $warehouseStock->getQuantity(),
                self::COLUMN_CHANGE => 0,
            ];

            if ($exportStockValues) {
                if (!in_array(self::COLUMN_STOCK, $columns, true)) {
                    $columns[] = self::COLUMN_STOCK;
                }
            } elseif (!in_array(self::COLUMN_CHANGE, $columns, true)) {
                $columns[] = self::COLUMN_CHANGE;
            }

            $currentRow = [];
            foreach ($columns as $column) {
                $currentRow[$csvHeaderTranslations[$column]] = $columnValues[$column];
            }
            $rows[] = $currentRow;
        }

        return $rows;
    }

    private function getCsvHeaderTranslations(string $locale, Context $context): array
    {
        $this->translator->setTranslationLocale($locale, $context);

        return array_map(fn ($snippedId) => $this->translator->translate($snippedId), self::COLUMN_TRANSLATIONS);
    }
}
