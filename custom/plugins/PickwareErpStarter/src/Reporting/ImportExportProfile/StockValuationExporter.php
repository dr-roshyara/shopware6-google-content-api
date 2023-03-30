<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Reporting\ImportExportProfile;

use DateTime;
use Pickware\DalBundle\CriteriaJsonSerializer;
use Pickware\DalBundle\EntityManager;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\PickwareErpStarter\ImportExport\CsvErrorFactory;
use Pickware\PickwareErpStarter\ImportExport\Exporter;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportElementDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportEntity;
use Pickware\PickwareErpStarter\Product\ProductNameFormatterService;
use Pickware\PickwareErpStarter\Reporting\Model\StockValuationViewDefinition;
use Pickware\PickwareErpStarter\Reporting\Model\StockValuationViewEntity;
use Pickware\PickwareErpStarter\Translation\Translator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;

class StockValuationExporter implements Exporter
{
    public const TECHNICAL_NAME = 'stock-valuation';

    public const COLUMN_PRODUCT_NUMBER = 'product.productNumber';
    public const COLUMN_PRODUCT_NAME = 'product.name';
    public const COLUMN_STOCK = 'warehouseStock.quantity';
    public const COLUMN_PURCHASE_PRICE_NET_IN_DEFAULT_CURRENCY = 'purchasePriceNetInDefaultCurrency';
    public const COLUMN_PURCHASE_PRICE_GROSS_IN_DEFAULT_CURRENCY = 'purchasePriceGrossInDefaultCurrency';
    public const COLUMN_STOCK_VALUATION_NET_IN_DEFAULT_CURRENCY = 'stockValuationNetInDefaultCurrency';
    public const COLUMN_STOCK_VALUATION_GROSS_IN_DEFAULT_CURRENCY = 'stockValuationGrossInDefaultCurrency';
    public const COLUMN_CURRENCY_FACTOR = 'currency.factor';
    public const COLUMN_PURCHASE_PRICE_NET = 'purchasePriceNet';
    public const COLUMN_PURCHASE_PRICE_GROSS = 'purchasePriceGross';
    public const COLUMN_STOCK_VALUATION_NET = 'stockValuationNet';
    public const COLUMN_STOCK_VALUATION_GROSS = 'stockValuationGross';

    public const COLUMNS = [
        self::COLUMN_PRODUCT_NUMBER,
        self::COLUMN_PRODUCT_NAME,
        self::COLUMN_STOCK,
        self::COLUMN_PURCHASE_PRICE_NET_IN_DEFAULT_CURRENCY,
        self::COLUMN_PURCHASE_PRICE_GROSS_IN_DEFAULT_CURRENCY,
        self::COLUMN_STOCK_VALUATION_NET_IN_DEFAULT_CURRENCY,
        self::COLUMN_STOCK_VALUATION_GROSS_IN_DEFAULT_CURRENCY,
        self::COLUMN_CURRENCY_FACTOR,
        self::COLUMN_PURCHASE_PRICE_NET,
        self::COLUMN_PURCHASE_PRICE_GROSS,
        self::COLUMN_STOCK_VALUATION_NET,
        self::COLUMN_STOCK_VALUATION_GROSS,
    ];

    public const DEFAULT_COLUMNS = [
        self::COLUMN_PRODUCT_NAME,
        self::COLUMN_PRODUCT_NUMBER,
        self::COLUMN_STOCK,
        self::COLUMN_PURCHASE_PRICE_NET_IN_DEFAULT_CURRENCY,
        self::COLUMN_STOCK_VALUATION_NET_IN_DEFAULT_CURRENCY,
        self::COLUMN_STOCK_VALUATION_GROSS_IN_DEFAULT_CURRENCY,
    ];

    public const COLUMN_TRANSLATIONS = [
        self::COLUMN_PRODUCT_NAME => 'pickware-erp-starter.stock-valuation-export.columns.product-name',
        self::COLUMN_PRODUCT_NUMBER => 'pickware-erp-starter.stock-valuation-export.columns.product-number',
        self::COLUMN_STOCK => 'pickware-erp-starter.stock-valuation-export.columns.stock',
        self::COLUMN_PURCHASE_PRICE_NET_IN_DEFAULT_CURRENCY => 'pickware-erp-starter.stock-valuation-export.columns.purchase-price-net-in-default-currency',
        self::COLUMN_PURCHASE_PRICE_GROSS_IN_DEFAULT_CURRENCY => 'pickware-erp-starter.stock-valuation-export.columns.purchase-price-gross-in-default-currency',
        self::COLUMN_STOCK_VALUATION_NET_IN_DEFAULT_CURRENCY => 'pickware-erp-starter.stock-valuation-export.columns.stock-valuation-net-in-default-currency',
        self::COLUMN_STOCK_VALUATION_GROSS_IN_DEFAULT_CURRENCY => 'pickware-erp-starter.stock-valuation-export.columns.stock-valuation-gross-in-default-currency',
        self::COLUMN_CURRENCY_FACTOR => 'pickware-erp-starter.stock-valuation-export.columns.currency-factor',
        self::COLUMN_PURCHASE_PRICE_NET => 'pickware-erp-starter.stock-valuation-export.columns.purchase-price-net',
        self::COLUMN_PURCHASE_PRICE_GROSS => 'pickware-erp-starter.stock-valuation-export.columns.purchase-price-gross',
        self::COLUMN_STOCK_VALUATION_NET => 'pickware-erp-starter.stock-valuation-export.columns.stock-valuation-net',
        self::COLUMN_STOCK_VALUATION_GROSS => 'pickware-erp-starter.stock-valuation-export.columns.stock-valuation-gross',
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

        $exportRows = $this->getStockValuationExportRows(
            $criteria,
            $exportConfig['locale'],
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
        return StockValuationViewDefinition::class;
    }

    public function getCsvFileName(string $importExportId, string $locale, Context $context): string
    {
        $this->translator->setTranslationLocale($locale, $context);

        return sprintf(
            $this->translator->translate('pickware-erp-starter.stock-valuation-export.file-name') . '.csv',
            (new DateTime())->format('Y.m.d H:i:s'),
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
    private function getStockValuationExportRows(
        Criteria $criteria,
        string $locale,
        array $columns,
        Context $context
    ): array {
        $csvHeaderTranslations = $this->getCsvHeaderTranslations($locale, $context);

        $stockValuationViewEntities = $context->enableInheritance(function (Context $inheritanceContext) use ($criteria) {
            return $this->entityManager->findBy(
                StockValuationViewDefinition::class,
                EntityManager::sanitizeCriteria($criteria),
                $inheritanceContext,
                [
                    'currency',
                    'warehouseStock',
                    'product.cover',
                    'product.options',
                    'product.tax',
                ],
            );
        });

        $productIds = $stockValuationViewEntities->map(fn (StockValuationViewEntity $stockValuationViewEntity) => $stockValuationViewEntity->getProduct()->getId());
        $productNames = $this->productNameFormatterService->getFormattedProductNames(
            $productIds,
            [],
            $context,
        );

        $rows = [];
        /** @var StockValuationViewEntity $stockValuationViewEntity */
        foreach ($stockValuationViewEntities as $stockValuationViewEntity) {
            $warehouseStock = $stockValuationViewEntity->getWarehouseStock();
            $currency = $stockValuationViewEntity->getCurrency();
            $columnValues = [
                self::COLUMN_PRODUCT_NAME => $productNames[$stockValuationViewEntity->getProduct()->getId()],
                self::COLUMN_PRODUCT_NUMBER => $stockValuationViewEntity->getProduct()->getProductNumber(),
                self::COLUMN_STOCK => $warehouseStock ? $warehouseStock->getQuantity() : 0,
                self::COLUMN_PURCHASE_PRICE_NET_IN_DEFAULT_CURRENCY => $stockValuationViewEntity->getPurchasePriceNetInDefaultCurrency() ?? 0.0,
                self::COLUMN_PURCHASE_PRICE_GROSS_IN_DEFAULT_CURRENCY => $stockValuationViewEntity->getPurchasePriceGrossInDefaultCurrency() ?? 0.0,
                self::COLUMN_STOCK_VALUATION_NET_IN_DEFAULT_CURRENCY => $stockValuationViewEntity->getStockValuationNetInDefaultCurrency() ?? 0.0,
                self::COLUMN_STOCK_VALUATION_GROSS_IN_DEFAULT_CURRENCY => $stockValuationViewEntity->getStockValuationGrossInDefaultCurrency() ?? 0.0,
                self::COLUMN_CURRENCY_FACTOR => $currency ? $currency->getFactor() : 0.0,
                self::COLUMN_PURCHASE_PRICE_NET => $stockValuationViewEntity->getPurchasePriceNet(),
                self::COLUMN_PURCHASE_PRICE_GROSS => $stockValuationViewEntity->getPurchasePriceGross(),
                self::COLUMN_STOCK_VALUATION_NET => $stockValuationViewEntity->getStockValuationNet(),
                self::COLUMN_STOCK_VALUATION_GROSS => $stockValuationViewEntity->getStockValuationGross(),
            ];

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
