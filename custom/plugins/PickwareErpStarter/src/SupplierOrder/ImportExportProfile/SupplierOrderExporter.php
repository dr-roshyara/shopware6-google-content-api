<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\SupplierOrder\ImportExportProfile;

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
use Pickware\PickwareErpStarter\SupplierOrder\Model\SupplierOrderLineItemCollection;
use Pickware\PickwareErpStarter\SupplierOrder\Model\SupplierOrderLineItemDefinition;
use Pickware\PickwareErpStarter\SupplierOrder\Model\SupplierOrderLineItemEntity;
use Pickware\PickwareErpStarter\Translation\Translator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class SupplierOrderExporter implements Exporter
{
    public const PLUGIN_CONFIG_COLUMNS_KEY = 'PickwareErpStarter.global-plugin-config.supplierOrderCsvExportColumns';
    public const TECHNICAL_NAME = 'supplier-order';

    public const COLUMN_SUPPLIER_PRODUCT_NUMBER = 'product.extension.productSupplierConfiguration.supplierProductNumber';
    public const COLUMN_GTIN = 'product.ean';
    public const COLUMN_PRODUCT_NUMBER = 'product.productNumber';
    public const COLUMN_PRODUCT_NAME = 'product.name';
    public const COLUMN_MANUFACTURER = 'product.manufacturer';
    public const COLUMN_MANUFACTURER_NUMBER = 'manufacturerNumber';
    public const COLUMN_MINIMUM_PURCHASE = 'product.extension.pickwareErpProductSupplierConfiguration.minPurchase';
    public const COLUMN_PURCHASE_STEPS = 'product.extension.pickwareErpProductSupplierConfiguration.purchaseSteps';
    public const COLUMN_QUANTITY = 'quantity';
    public const COLUMN_UNIT_PRICE = 'unitPrice';
    public const COLUMN_TOTAL_PRICE = 'totalPrice';

    public const COLUMNS = [
        self::COLUMN_SUPPLIER_PRODUCT_NUMBER,
        self::COLUMN_GTIN,
        self::COLUMN_PRODUCT_NUMBER,
        self::COLUMN_PRODUCT_NAME,
        self::COLUMN_MANUFACTURER,
        self::COLUMN_MANUFACTURER_NUMBER,
        self::COLUMN_MINIMUM_PURCHASE,
        self::COLUMN_PURCHASE_STEPS,
        self::COLUMN_QUANTITY,
        self::COLUMN_UNIT_PRICE,
        self::COLUMN_TOTAL_PRICE,
    ];

    public const DEFAULT_COLUMNS = [
        self::COLUMN_SUPPLIER_PRODUCT_NUMBER,
        self::COLUMN_GTIN,
        self::COLUMN_MANUFACTURER,
        self::COLUMN_PRODUCT_NAME,
        self::COLUMN_QUANTITY,
    ];

    public const COLUMN_TRANSLATIONS = [
        self::COLUMN_SUPPLIER_PRODUCT_NUMBER => 'pickware-erp-starter.supplier-order-export.columns.supplier-product-number',
        self::COLUMN_GTIN => 'pickware-erp-starter.supplier-order-export.columns.gtin',
        self::COLUMN_PRODUCT_NUMBER => 'pickware-erp-starter.supplier-order-export.columns.product-number',
        self::COLUMN_PRODUCT_NAME => 'pickware-erp-starter.supplier-order-export.columns.product-name',
        self::COLUMN_MANUFACTURER => 'pickware-erp-starter.supplier-order-export.columns.manufacturer',
        self::COLUMN_MANUFACTURER_NUMBER => 'pickware-erp-starter.supplier-order-export.columns.manufacturer-number',
        self::COLUMN_MINIMUM_PURCHASE => 'pickware-erp-starter.supplier-order-export.columns.min-purchase',
        self::COLUMN_PURCHASE_STEPS => 'pickware-erp-starter.supplier-order-export.columns.purchase-steps',
        self::COLUMN_QUANTITY => 'pickware-erp-starter.supplier-order-export.columns.quantity',
        self::COLUMN_UNIT_PRICE => 'pickware-erp-starter.supplier-order-export.columns.unit-price',
        self::COLUMN_TOTAL_PRICE => 'pickware-erp-starter.supplier-order-export.columns.total-price',
    ];

    public const DELETED_TRANSLATION = 'pickware-erp-starter.supplier-order-export.deleted';

    public const COLUMN_IDENTIFIER_MAPPING = [
        'supplier-product-number' => self::COLUMN_SUPPLIER_PRODUCT_NUMBER,
        'ean' => self::COLUMN_GTIN,
        'product-number' => self::COLUMN_PRODUCT_NUMBER,
        'product-name' => self::COLUMN_PRODUCT_NAME,
        'manufacturer' => self::COLUMN_MANUFACTURER,
        'manufacturer-number' => self::COLUMN_MANUFACTURER_NUMBER,
        'min-purchase' => self::COLUMN_MINIMUM_PURCHASE,
        'purchase-steps' => self::COLUMN_PURCHASE_STEPS,
        'quantity' => self::COLUMN_QUANTITY,
        'unit-price' => self::COLUMN_UNIT_PRICE,
        'total-price' => self::COLUMN_TOTAL_PRICE,
    ];

    private EntityManager $entityManager;
    private CriteriaJsonSerializer $criteriaJsonSerializer;
    private Translator $translator;
    private ProductNameFormatterService $productNameFormatterService;
    private SystemConfigService $systemConfigService;
    private int $batchSize;

    public function __construct(
        EntityManager $entityManager,
        CriteriaJsonSerializer $criteriaJsonSerializer,
        Translator $translator,
        ProductNameFormatterService $productNameFormatterService,
        SystemConfigService $systemConfigService,
        int $batchSize
    ) {
        $this->entityManager = $entityManager;
        $this->criteriaJsonSerializer = $criteriaJsonSerializer;
        $this->translator = $translator;
        $this->productNameFormatterService = $productNameFormatterService;
        $this->systemConfigService = $systemConfigService;
        $this->batchSize = $batchSize;
    }

    public function getCsvFileName(string $importExportId, string $locale, Context $context): string
    {
        /** @var ImportExportEntity $export */
        $export = $this->entityManager->findByPrimaryKey(ImportExportDefinition::class, $importExportId, $context);

        $criteria = $this->getCriteria($export, 0);
        $supplierOrderLineItems = $this->getSupplierOrderLineItems($criteria, $context);
        $supplierOrder = $supplierOrderLineItems->first() ? $supplierOrderLineItems->first()->getSupplierOrder() : null;

        $this->translator->setTranslationLocale($locale, $context);

        return vsprintf(
            $this->translator->translate('pickware-erp-starter.supplier-order-export.file-name') . '.csv',
            [
                $supplierOrder ? $supplierOrder->getNumber() : '',
                $export->getCreatedAt()->format('Y-m-d H_i_s'),
            ],
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

    public function exportChunk(string $exportId, int $nextRowNumberToWrite, Context $context): ?int
    {
        /** @var ImportExportEntity $export */
        $export = $this->entityManager->findByPrimaryKey(ImportExportDefinition::class, $exportId, $context);
        $exportConfig = $export->getConfig();

        $systemConfigColumnIdentifiers = $this->systemConfigService->get(self::PLUGIN_CONFIG_COLUMNS_KEY);
        $systemConfigColumns = array_map(static fn ($identifier) => self::COLUMN_IDENTIFIER_MAPPING[$identifier], $systemConfigColumnIdentifiers ?? []);

        $columns = $exportConfig['columns'] ?? ($systemConfigColumnIdentifiers ? $systemConfigColumns : null) ?? self::DEFAULT_COLUMNS;

        // Retrieve the next batch of matching results. Reminder: row number starts with 1.
        $criteria = $this->getCriteria($export, $nextRowNumberToWrite - 1);
        $exportRows = $this->getSupplierOrderExportRows($criteria, $exportConfig['locale'], $columns, $context);

        $exportElementPayloads = [];
        foreach ($exportRows as $index => $exportRow) {
            $exportElementPayloads[] = [
                'id' => Uuid::randomHex(),
                'importExportId' => $exportId,
                'rowNumber' => $nextRowNumberToWrite + $index,
                'rowData' => $exportRow,
            ];
        }

        $this->entityManager->create(ImportExportElementDefinition::class, $exportElementPayloads, $context);

        $nextRowNumberToWrite += $this->batchSize;

        if (count($exportRows) < $this->batchSize) {
            return null;
        }

        return $nextRowNumberToWrite;
    }

    public function getEntityDefinitionClassName(): string
    {
        return SupplierOrderLineItemDefinition::class;
    }

    private function getSupplierOrderExportRows(Criteria $criteria, string $locale, array $columns, Context $context): array
    {
        $csvHeaderTranslations = $this->getCsvHeaderTranslations($locale, $context);
        $supplierOrderLineItems = $this->getSupplierOrderLineItems($criteria, $context);
        $deletedTranslation = $this->getDeletedTranslations($locale, $context);

        // We need to filter the supplier order line items to only get product ids if they are set
        $productNames = $this->productNameFormatterService->getFormattedProductNames(
            array_map(
                fn (SupplierOrderLineItemEntity $supplierOrderLineItemEntity) => $supplierOrderLineItemEntity->getProductId(),
                array_filter(
                    $supplierOrderLineItems->getElements(),
                    fn (SupplierOrderLineItemEntity $supplierOrderLineItemEntity) => $supplierOrderLineItemEntity->getProductId() != null,
                ),
            ),
            [],
            $context,
        );

        $rows = [];
        foreach ($supplierOrderLineItems as $supplierOrderLineItem) {
            $product = $supplierOrderLineItem->getProduct();
            $productSnapshot = $supplierOrderLineItem->getProductSnapshot();
            /** @var ProductSupplierConfigurationEntity|null $productSupplierConfiguration */
            $productSupplierConfiguration = $product ? $product->getExtension('pickwareErpProductSupplierConfiguration') : null;
            $manufacturer = $product ? $product->getManufacturer() : null;

            $columnValues = [
                self::COLUMN_SUPPLIER_PRODUCT_NUMBER => $productSupplierConfiguration ? $productSupplierConfiguration->getSupplierProductNumber() : '',
                self::COLUMN_GTIN => $product ? $product->getEan() : '',
                self::COLUMN_PRODUCT_NUMBER => $product ? $product->getProductNumber() : $productSnapshot['productNumber'],
                self::COLUMN_PRODUCT_NAME => $product ? $productNames[$product->getId()] : sprintf('%s (%s)', $productSnapshot['name'], $deletedTranslation),
                self::COLUMN_MANUFACTURER => $manufacturer ? $manufacturer->getName() : '',
                self::COLUMN_MANUFACTURER_NUMBER => $product ? $product->getManufacturerNumber() : '',
                self::COLUMN_MINIMUM_PURCHASE => $productSupplierConfiguration ? $productSupplierConfiguration->getMinPurchase() : '',
                self::COLUMN_PURCHASE_STEPS => $productSupplierConfiguration ? $productSupplierConfiguration->getPurchaseSteps() : '',
                self::COLUMN_QUANTITY => $supplierOrderLineItem->getQuantity(),
                self::COLUMN_UNIT_PRICE => $supplierOrderLineItem->getUnitPrice(),
                self::COLUMN_TOTAL_PRICE => $supplierOrderLineItem->getTotalPrice(),
            ];

            $currentRow = [];
            foreach ($columns as $column) {
                $currentRow[$csvHeaderTranslations[$column]]
                    = $columnValues[$column];
            }
            $rows[] = $currentRow;
        }

        return $rows;
    }

    private function getCriteria(ImportExportEntity $export, int $offset): Criteria
    {
        $criteria = $this->criteriaJsonSerializer->deserializeFromArray(
            $export->getConfig()['criteria'],
            $this->getEntityDefinitionClassName(),
        );
        $criteria->setLimit($this->batchSize);
        $criteria->setOffset($offset);

        return $criteria;
    }

    private function getSupplierOrderLineItems(Criteria $criteria, Context $context): SupplierOrderLineItemCollection
    {
        return $context->enableInheritance(function (Context $inheritanceContext) use ($criteria) {
            return $this->entityManager->findBy(
                SupplierOrderLineItemDefinition::class,
                $criteria,
                $inheritanceContext,
                [
                    'product',
                    'product.pickwareErpProductSupplierConfiguration',
                    'product.manufacturer',
                    'supplierOrder',
                ],
            );
        });
    }

    private function getCsvHeaderTranslations(string $locale, Context $context): array
    {
        $this->translator->setTranslationLocale($locale, $context);

        return array_map(fn ($snippedId) => $this->translator->translate($snippedId), self::COLUMN_TRANSLATIONS);
    }

    private function getDeletedTranslations(string $locale, Context $context): string
    {
        $this->translator->setTranslationLocale($locale, $context);

        return $this->translator->translate(self::DELETED_TRANSLATION);
    }
}
