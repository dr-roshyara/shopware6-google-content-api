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

use Pickware\DalBundle\EntityManager;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\PickwareErpStarter\ImportExport\Exception\ImportException;
use Pickware\PickwareErpStarter\ImportExport\Importer;
use Pickware\PickwareErpStarter\ImportExport\ImportExportStateService;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportElementCollection;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportElementDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportElementEntity;
use Pickware\PickwareErpStarter\ImportExport\ReadWrite\Csv\Validator;
use Pickware\PickwareErpStarter\Supplier\Model\ProductSupplierConfigurationDefinition;
use Pickware\PickwareErpStarter\Supplier\Model\ProductSupplierConfigurationEntity;
use Pickware\PickwareErpStarter\Supplier\Model\SupplierDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Throwable;

class ProductSupplierConfigurationImporter implements Importer
{
    public const TECHNICAL_NAME = 'product-supplier-configuration';
    public const VALIDATION_SCHEMA = [
        '$id' => 'pickware-erp--import-export--product-supplier-configuration-import',
        'type' => 'object',
        'properties' => [
            'productNumber' => [
                'type' => 'string',
                'maxLength' => 64,
            ],
            'productName' => [
                'type' => 'string',
                'maxLength' => 255,
            ],
            'ean' => [
                'type' => 'string',
                'maxLength' => 255,
            ],
            'manufacturer' => [
                'type' => 'string',
                'maxLength' => 255,
            ],
            'manufacturerProductNumber' => [
                'type' => 'string',
                'maxLength' => 255,
            ],
            'supplier' => [
                'type' => 'string',
                'maxLength' => 255,
            ],
            'supplierNumber' => [
                'type' => 'string',
                'maxLength' => 255,
            ],
            'supplierProductNumber' => [
                'type' => 'string',
                'maxLength' => 64, // This limit is set by the database field
            ],
            'minPurchase' => [
                '$ref' => '#/definitions/integerMinOneOrEmpty',
            ],
            'purchaseSteps' => [
                '$ref' => '#/definitions/integerMinOneOrEmpty',
            ],
            'purchasePriceNet' => [
                '$ref' => '#/definitions/numberMinZeroOrEmpty',
            ],
        ],
        'required' => ['productNumber'],
        'definitions' => [
            'empty' => [
                'type' => 'string',
                'maxLength' => 0,
            ],
            'integerMinOneOrEmpty' => [
                'oneOf' => [
                    ['$ref' => '#/definitions/empty'],
                    [
                        'type' => 'integer',
                        'minimum' => 1,
                    ],
                ],
            ],
            'numberMinZeroOrEmpty' => [
                'oneOf' => [
                    ['$ref' => '#/definitions/empty'],
                    [
                        'type' => 'number',
                        'minimum' => 0,
                    ],
                ],
            ],
        ],
    ];

    private EntityManager $entityManager;
    private ProductSupplierConfigurationImportCsvRowNormalizer $normalizer;
    private ImportExportStateService $importExportStateService;
    private int $batchSize;
    private Validator $validator;

    public function __construct(
        EntityManager $entityManager,
        ProductSupplierConfigurationImportCsvRowNormalizer $normalizer,
        ImportExportStateService $importExportStateService,
        int $batchSize
    ) {
        $this->entityManager = $entityManager;
        $this->normalizer = $normalizer;
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
        $productIdsByNumber = $this->getProductNumberIdMapping($normalizedRows, $context);
        $originalColumnNamesByNormalizedColumnNames = $this->normalizer->mapNormalizedToOriginalColumnNames(array_keys(
            $importElements->first()->getRowData(),
        ));

        $supplierByName = [];
        $supplierByNumber = [];
        $manufacturerByName = [];

        $productPayloads = [];
        $productSupplierConfigurationPayloads = [];
        foreach ($importElements->getElements() as $index => $importElement) {
            $normalizedRow = $normalizedRows[$index];

            $errors = $this->validator->validateRow($normalizedRow, $originalColumnNamesByNormalizedColumnNames);
            if ($this->failOnErrors($importElement->getId(), $errors, $context)) {
                continue;
            }

            $productId = $productIdsByNumber[mb_strtolower($normalizedRow['productNumber'])] ?? null;
            if (!$productId) {
                $errors->addError(ProductSupplierConfigurationException::createProductNotFoundError(
                    $normalizedRow['productNumber'],
                ));
            }

            if ($this->failOnErrors($importElement->getId(), $errors, $context)) {
                continue;
            }

            try {
                $updatedProductSupplierConfiguration = $this->getProductSupplierConfigurationUpdatePayload(
                    $productId,
                    $importElement->getId(),
                    $normalizedRow,
                    $supplierByName,
                    $supplierByNumber,
                    $errors,
                    $context,
                );
                if ($updatedProductSupplierConfiguration !== null) {
                    $productSupplierConfigurationPayloads[] = $updatedProductSupplierConfiguration;
                }
                $updatedProduct = $this->getProductUpdatePayload(
                    $productId,
                    $importElement->getId(),
                    $normalizedRow,
                    $manufacturerByName,
                    $errors,
                    $context,
                );
                if ($updatedProduct !== null) {
                    $productPayloads[] = $updatedProduct;
                }
            } catch (Throwable $exception) {
                throw ImportException::rowImportError($exception, $importElement->getRowNumber());
            }
        }

        try {
            $this->entityManager->runInTransactionWithRetry(
                function () use ($productSupplierConfigurationPayloads, $productPayloads, $context): void {
                    if (count($productSupplierConfigurationPayloads) > 0) {
                        $this->entityManager->update(
                            ProductSupplierConfigurationDefinition::class,
                            $productSupplierConfigurationPayloads,
                            $context,
                        );
                    }
                    if (count($productPayloads) > 0) {
                        $this->entityManager->update(
                            ProductDefinition::class,
                            $productPayloads,
                            $context,
                        );
                    }
                },
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

    private function getProductNumberIdMapping(array $normalizedRows, Context $context): array
    {
        $productNumbers = array_column($normalizedRows, 'productNumber');
        /** @var ProductCollection $products */
        $products = $this->entityManager->findBy(ProductDefinition::class, [
            'productNumber' => $productNumbers,
        ], $context);

        $productNumbers = $products->map(fn (ProductEntity $product) => mb_strtolower($product->getProductNumber()));

        return array_combine($productNumbers, $products->getKeys());
    }

    private function getProductSupplierConfigurationUpdatePayload(
        string $productId,
        string $importElementId,
        array $normalizedRow,
        array &$supplierByName,
        array &$supplerByNumber,
        JsonApiErrors $errors,
        Context $context
    ): ?array {
        $hasSupplierValue = isset($normalizedRow['supplier']);
        $hasSupplierNumberValue = isset($normalizedRow['supplierNumber']) && $normalizedRow['supplierNumber'] !== '';
        $hasSupplierProductNumberValue = isset($normalizedRow['supplierProductNumber']);
        $hasMinPurchaseValue = isset($normalizedRow['minPurchase']);
        $hasPurchaseStepsValue = isset($normalizedRow['purchaseSteps']);

        /** @var ProductSupplierConfigurationEntity $configuration */
        $configuration = $this->entityManager->getOneBy(
            ProductSupplierConfigurationDefinition::class,
            ['productId' => $productId],
            $context,
        );

        $payload = ['id' => $configuration->getId()];

        if ($hasSupplierNumberValue) {
            $supplier = null;
            if (array_key_exists($normalizedRow['supplierNumber'], $supplerByNumber)) {
                $supplier = $supplerByNumber[$normalizedRow['supplierNumber']];
            } else {
                $supplier = $this->entityManager->findOneBy(
                    SupplierDefinition::class,
                    ['number' => $normalizedRow['supplierNumber']],
                    $context,
                );
                $supplerByNumber[$normalizedRow['supplierNumber']] = $supplier;
            }

            if (!$supplier) {
                $errors->addError(
                    ProductSupplierConfigurationException::createSupplierNotFoundByNumberError(
                        $normalizedRow['supplierNumber'],
                    ),
                );
            } else {
                $payload['supplierId'] = $supplier->getUniqueIdentifier();
            }
        } elseif ($hasSupplierValue) {
            if ($normalizedRow['supplier'] !== '') {
                $supplier = null;
                if (array_key_exists($normalizedRow['supplier'], $supplierByName)) {
                    $supplier = $supplierByName[$normalizedRow['supplier']];
                } else {
                    $supplier = $this->entityManager->findOneBy(
                        SupplierDefinition::class,
                        ['name' => $normalizedRow['supplier']],
                        $context,
                    );
                    $supplierByName[$normalizedRow['supplier']] = $supplier;
                }

                if (!$supplier) {
                    $errors->addError(
                        ProductSupplierConfigurationException::createSupplierNotFoundByNameError(
                            $normalizedRow['supplier'],
                        ),
                    );
                } else {
                    $payload['supplierId'] = $supplier->getUniqueIdentifier();
                }
            } else {
                $payload['supplierId'] = null;
            }
        }

        if ($hasSupplierProductNumberValue) {
            if ($normalizedRow['supplierProductNumber'] !== '') {
                $payload['supplierProductNumber'] = $normalizedRow['supplierProductNumber'];
            } else {
                $payload['supplierProductNumber'] = null;
            }
        }

        if ($hasMinPurchaseValue && $normalizedRow['minPurchase'] !== '') {
            $payload['minPurchase'] = $normalizedRow['minPurchase'];
        }

        if ($hasPurchaseStepsValue && $normalizedRow['purchaseSteps'] !== '') {
            $payload['purchaseSteps'] = $normalizedRow['purchaseSteps'];
        }

        if ($this->failOnErrors($importElementId, $errors, $context)) {
            return null;
        }

        return $payload;
    }

    private function getProductUpdatePayload(
        string $productId,
        string $importElementId,
        array $normalizedRow,
        array &$manufacturerByName,
        JsonApiErrors $errors,
        Context $context
    ): ?array {
        if (count($errors) > 0) {
            return null;
        }

        $payload = ['id' => $productId];

        $hasPurchasePriceNetValue = isset($normalizedRow['purchasePriceNet'])
            && $normalizedRow['purchasePriceNet'] !== '';
        $hasGtinValue = isset($normalizedRow['gtin']);
        $hasManufacturerValue = isset($normalizedRow['manufacturer']);
        $hasManufacturerProductNumberValue = isset($normalizedRow['manufacturerProductNumber']);

        if ($hasPurchasePriceNetValue) {
            /** @var ProductEntity $product */
            $product = $context->enableInheritance(function (Context $inheritanceContext) use ($productId) {
                return $this->entityManager->findByPrimaryKey(
                    ProductDefinition::class,
                    $productId,
                    $inheritanceContext,
                    ['tax'],
                );
            });

            $net = $normalizedRow['purchasePriceNet'];
            $gross = $net * (1 + $product->getTax()->getTaxRate() / 100);

            $purchasePrices = $product->getPurchasePrices() ?? new PriceCollection();
            $defaultCurrencyPurchasePrice = $purchasePrices->getCurrencyPrice(Defaults::CURRENCY);
            if ($defaultCurrencyPurchasePrice) {
                $defaultCurrencyPurchasePrice->setNet($net);
                $defaultCurrencyPurchasePrice->setGross($gross);
            } else {
                $purchasePrices->add(new Price(Defaults::CURRENCY, $net, $gross, true));
            }

            $payload['purchasePrices'] = $purchasePrices->map(fn ($price) => $price->jsonSerialize());
        }

        if ($hasGtinValue) {
            $payload['ean'] = $normalizedRow['gtin'];
        }

        if ($hasManufacturerValue) {
            if ($normalizedRow['manufacturer'] !== '') {
                $manufacturer = null;
                if (array_key_exists($normalizedRow['manufacturer'], $manufacturerByName)) {
                    $manufacturer = $manufacturerByName[$normalizedRow['manufacturer']];
                } else {
                    $manufacturer = $this->entityManager->findFirstBy(
                        ProductManufacturerDefinition::class,
                        new FieldSorting('createdAt', FieldSorting::ASCENDING),
                        $context,
                        ['name' => $normalizedRow['manufacturer']],
                    );
                    $manufacturerByName[$normalizedRow['manufacturer']] = $manufacturer;
                }

                if (!$manufacturer) {
                    $errors->addError(
                        ProductSupplierConfigurationException::createManufacturerNotFoundError(
                            $normalizedRow['manufacturer'],
                        ),
                    );
                } else {
                    $payload['manufacturerId'] = $manufacturer->getUniqueIdentifier();
                }
            } else {
                $payload['manufacturerId'] = null;
            }
        }

        if ($hasManufacturerProductNumberValue) {
            if ($normalizedRow['manufacturerProductNumber'] !== '') {
                $payload['manufacturerNumber'] = $normalizedRow['manufacturerProductNumber'];
            } else {
                $payload['manufacturerNumber'] = null;
            }
        }

        if ($this->failOnErrors($importElementId, $errors, $context)) {
            return null;
        }

        return $payload;
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
        return JsonApiErrors::noError();
    }
}
