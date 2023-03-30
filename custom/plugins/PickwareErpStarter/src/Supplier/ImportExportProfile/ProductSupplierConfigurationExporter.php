<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Supplier\ImportExportProfile;

use Pickware\DalBundle\CriteriaJsonSerializer;
use Pickware\DalBundle\EntityManager;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\PickwareErpStarter\ImportExport\CsvErrorFactory;
use Pickware\PickwareErpStarter\ImportExport\Exporter;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportElementDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportEntity;
use Pickware\PickwareErpStarter\Product\ProductNameFormatterService;
use Pickware\PickwareErpStarter\Supplier\Model\ProductSupplierConfigurationEntity;
use Pickware\PickwareErpStarter\Translation\Translator;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;

class ProductSupplierConfigurationExporter implements Exporter
{
    public const TECHNICAL_NAME = 'product-supplier-configuration';

    public const COLUMN_PRODUCT_NUMBER = 'productNumber';
    public const COLUMN_PRODUCT_NAME = 'name';
    public const COLUMN_GTIN = 'ean';
    public const COLUMN_MANUFACTURER_NAME = 'manufacturer.name';
    public const COLUMN_MANUFACTURER_NUMBER = 'manufacturerNumber';
    public const COLUMN_SUPPLIER_NAME = 'extensions.pickwareErpProductSupplierConfiguration.supplier.name';
    public const COLUMN_SUPPLIER_NUMBER = 'extensions.pickwareErpProductSupplierConfiguration.supplier.number';
    public const COLUMN_SUPPLIER_PRODUCT_NUMBER = 'extensions.pickwareErpProductSupplierConfiguration.supplierProductNumber';
    public const COLUMN_MINIMUM_PURCHASE = 'extensions.pickwareErpProductSupplierConfiguration.minPurchase';
    public const COLUMN_PURCHASE_STEPS = 'extensions.pickwareErpProductSupplierConfiguration.purchaseSteps';
    public const COLUMN_PURCHASE_PRICE_NET = 'purchasePrices';

    public const COLUMNS = [
        self::COLUMN_PRODUCT_NUMBER,
        self::COLUMN_PRODUCT_NAME,
        self::COLUMN_GTIN,
        self::COLUMN_MANUFACTURER_NAME,
        self::COLUMN_MANUFACTURER_NUMBER,
        self::COLUMN_SUPPLIER_NAME,
        self::COLUMN_SUPPLIER_NUMBER,
        self::COLUMN_SUPPLIER_PRODUCT_NUMBER,
        self::COLUMN_MINIMUM_PURCHASE,
        self::COLUMN_PURCHASE_STEPS,
        self::COLUMN_PURCHASE_PRICE_NET,
    ];
    public const DEFAULT_COLUMNS = [
        self::COLUMN_PRODUCT_NUMBER,
        self::COLUMN_PRODUCT_NAME,
        self::COLUMN_MANUFACTURER_NAME,
        self::COLUMN_SUPPLIER_NAME,
        self::COLUMN_SUPPLIER_NUMBER,
        self::COLUMN_SUPPLIER_PRODUCT_NUMBER,
        self::COLUMN_MINIMUM_PURCHASE,
        self::COLUMN_PURCHASE_STEPS,
        self::COLUMN_PURCHASE_PRICE_NET,
    ];

    public const COLUMN_TRANSLATIONS = [
        self::COLUMN_PRODUCT_NUMBER => 'pickware-erp-starter.product-supplier-configuration-export.columns.product-number',
        self::COLUMN_PRODUCT_NAME => 'pickware-erp-starter.product-supplier-configuration-export.columns.product-name',
        self::COLUMN_GTIN => 'pickware-erp-starter.product-supplier-configuration-export.columns.gtin',
        self::COLUMN_MANUFACTURER_NAME => 'pickware-erp-starter.product-supplier-configuration-export.columns.manufacturer',
        self::COLUMN_MANUFACTURER_NUMBER => 'pickware-erp-starter.product-supplier-configuration-export.columns.manufacturer-number',
        self::COLUMN_SUPPLIER_NAME => 'pickware-erp-starter.product-supplier-configuration-export.columns.supplier',
        self::COLUMN_SUPPLIER_NUMBER => 'pickware-erp-starter.product-supplier-configuration-export.columns.supplier-number',
        self::COLUMN_SUPPLIER_PRODUCT_NUMBER => 'pickware-erp-starter.product-supplier-configuration-export.columns.supplier-product-number',
        self::COLUMN_MINIMUM_PURCHASE => 'pickware-erp-starter.product-supplier-configuration-export.columns.min-purchase',
        self::COLUMN_PURCHASE_STEPS => 'pickware-erp-starter.product-supplier-configuration-export.columns.purchase-steps',
        self::COLUMN_PURCHASE_PRICE_NET => 'pickware-erp-starter.product-supplier-configuration-export.columns.purchase-price-net',
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

        $exportRows = $this->getProductSupplierMappingExportRows(
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
        return ProductDefinition::class;
    }

    public function getCsvFileName(string $importExportId, string $locale, Context $context): string
    {
        /** @var ImportExportEntity $export */
        $export = $this->entityManager->findByPrimaryKey(ImportExportDefinition::class, $importExportId, $context);
        $this->translator->setTranslationLocale($locale, $context);

        return sprintf(
            $this->translator->translate('pickware-erp-starter.product-supplier-configuration-export.file-name') . '.csv',
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
    private function getProductSupplierMappingExportRows(Criteria $criteria, string $locale, array $columns, Context $context): array
    {
        $csvHeaderTranslations = $this->getCsvHeaderTranslations($locale, $context);
        $criteria = EntityManager::sanitizeCriteria($criteria);

        $productNames = [];
        $products = $context->enableInheritance(function (Context $inheritanceContext) use ($criteria, &$productNames) {
            $criteria->addAssociations([
                'pickwareErpProductSupplierConfiguration.supplier',
                'manufacturer',
                'options',
            ]);

            // Fetch ids to format names before fetching the full products to reduce memory usage peak
            $productIds = $this->entityManager->findIdsBy(ProductDefinition::class, $criteria, $inheritanceContext);
            $productNames = $this->productNameFormatterService->getFormattedProductNames($productIds, [], $inheritanceContext);

            return $this->entityManager->findBy(ProductDefinition::class, $criteria, $inheritanceContext);
        });

        $rows = [];
        foreach ($products as $product) {
            /** @var ProductSupplierConfigurationEntity $productSupplierConfiguration */
            $productSupplierConfiguration = $product->getExtension('pickwareErpProductSupplierConfiguration');
            $supplier = $productSupplierConfiguration ? $productSupplierConfiguration->getSupplier() : null;

            // Takes first purchasePrice if existent, see pw-erp-purchase-price-cell.vue value for reference
            $purchasePrices = $product->getPurchasePrices();
            $purchasePrice = null;
            if ($purchasePrices !== null) {
                $purchasePrice = $purchasePrices->first();
            }

            $columnValues = [
                self::COLUMN_PRODUCT_NUMBER => $product->getProductNumber(),
                self::COLUMN_PRODUCT_NAME => $productNames[$product->getId()],
                self::COLUMN_GTIN => $product->getEan(),
                self::COLUMN_MANUFACTURER_NAME => $product->getManufacturer() ? $product->getManufacturer()->getName() : '',
                self::COLUMN_MANUFACTURER_NUMBER => $product->getManufacturerNumber(),
                self::COLUMN_SUPPLIER_NAME => $supplier ? $supplier->getName() : '',
                self::COLUMN_SUPPLIER_NUMBER => $supplier ? $supplier->getNumber() : '',
                self::COLUMN_SUPPLIER_PRODUCT_NUMBER => $productSupplierConfiguration ? $productSupplierConfiguration->getSupplierProductNumber() : '',
                self::COLUMN_MINIMUM_PURCHASE => $productSupplierConfiguration ? $productSupplierConfiguration->getMinPurchase() : '',
                self::COLUMN_PURCHASE_STEPS => $productSupplierConfiguration ? $productSupplierConfiguration->getPurchaseSteps() : '',
                self::COLUMN_PURCHASE_PRICE_NET => $purchasePrice ? $purchasePrice->getNet() : '',
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
