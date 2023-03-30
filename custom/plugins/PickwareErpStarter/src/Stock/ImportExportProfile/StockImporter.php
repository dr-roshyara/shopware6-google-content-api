<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stock\ImportExportProfile;

use Pickware\DalBundle\EntityManager;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\PickwareErpStarter\ImportExport\Exception\ImportException;
use Pickware\PickwareErpStarter\ImportExport\Importer;
use Pickware\PickwareErpStarter\ImportExport\ImportExportStateService;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportElementCollection;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportElementDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportElementEntity;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportEntity;
use Pickware\PickwareErpStarter\ImportExport\ReadWrite\Csv\Validator;
use Pickware\PickwareErpStarter\Picking\PickingRequest;
use Pickware\PickwareErpStarter\Picking\PickingRequestSolver;
use Pickware\PickwareErpStarter\Picking\ProductPickingRequest;
use Pickware\PickwareErpStarter\Product\Model\PickwareProductDefinition;
use Pickware\PickwareErpStarter\Product\Model\PickwareProductEntity;
use Pickware\PickwareErpStarter\Stock\Model\LocationTypeDefinition;
use Pickware\PickwareErpStarter\Stock\Model\StockDefinition;
use Pickware\PickwareErpStarter\StockApi\StockLocationReference;
use Pickware\PickwareErpStarter\StockApi\StockLocationReferenceFinder;
use Pickware\PickwareErpStarter\StockApi\StockMovement;
use Pickware\PickwareErpStarter\StockApi\StockMovementService;
use Pickware\PickwareErpStarter\StockApi\StockMovementServiceValidationException;
use Pickware\PickwareErpStarter\Stocking\ProductQuantity;
use Pickware\PickwareErpStarter\Stocking\StockingRequest;
use Pickware\PickwareErpStarter\Stocking\StockingStrategy;
use Pickware\PickwareErpStarter\Warehouse\Model\BinLocationDefinition;
use Pickware\PickwareErpStarter\Warehouse\Model\BinLocationEntity;
use Pickware\PickwareErpStarter\Warehouse\Model\ProductWarehouseConfigurationDefinition;
use Pickware\PickwareErpStarter\Warehouse\Model\ProductWarehouseConfigurationEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Throwable;

class StockImporter implements Importer
{
    private EntityManager $entityManager;
    private StockMovementService $stockMovementService;
    private StockImportCsvRowNormalizer $normalizer;
    private StockLocationReferenceFinder $stockLocationReferenceFinder;
    private ImportExportStateService $importExportStateService;
    private PickingRequestSolver $pickingRequestSolver;
    private StockingStrategy $stockingStrategy;
    private int $batchSize;
    private Validator $validator;
    private StockChangeCalculator $stockChangeCalculator;

    public function __construct(
        EntityManager $entityManager,
        StockMovementService $stockMovementService,
        StockImportCsvRowNormalizer $normalizer,
        StockLocationReferenceFinder $stockLocationReferenceFinder,
        ImportExportStateService $importExportStateService,
        PickingRequestSolver $pickingRequestSolver,
        StockingStrategy $stockingStrategy,
        StockChangeCalculator $stockChangeCalculator,
        Validator $validator,
        int $batchSize
    ) {
        $this->entityManager = $entityManager;
        $this->stockMovementService = $stockMovementService;
        $this->normalizer = $normalizer;
        $this->stockLocationReferenceFinder = $stockLocationReferenceFinder;
        $this->importExportStateService = $importExportStateService;
        $this->pickingRequestSolver = $pickingRequestSolver;
        $this->stockingStrategy = $stockingStrategy;
        $this->stockChangeCalculator = $stockChangeCalculator;
        $this->batchSize = $batchSize;
        $this->validator = $validator;
    }

    public function validateHeaderRow(array $headerRow, Context $context): JsonApiErrors
    {
        $errors = $this->validator->validateHeaderRow($headerRow, $context);

        $actualColumns = $this->normalizer->normalizeColumnNames($headerRow);
        if (in_array('binLocationCode', $actualColumns, true)
            && !in_array('warehouseCode', $actualColumns, true)
            && !in_array('warehouseName', $actualColumns, true)
        ) {
            $errors->addError(StockImportException::createWarehouseForBinLocationMissing());
        }

        return $errors;
    }

    public function importChunk(string $importId, int $nextRowNumberToRead, Context $context): ?int
    {
        /** @var ImportExportEntity $import */
        $import = $this->entityManager->findByPrimaryKey(ImportExportDefinition::class, $importId, $context);

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
        $productNumberIdMapping = $this->getProductNumberIdMapping($normalizedRows, $context);
        // Mapping: normalizedColumnName => originalColumnName
        $normalizedToOriginalColumnNameMapping = $this->normalizer->mapNormalizedToOriginalColumnNames(array_keys(
            $importElements->first()->getRowData(),
        ));

        $pickwareProductsByProductId = [];
        $productWarehouseConfigurationsByConcatenatedProductAndWarehouseId = [];
        $binLocationsById = [];
        foreach ($importElements->getElements() as $index => $importElement) {
            $normalizedRow = $normalizedRows[$index];

            $errors = $this->validateRowSchema($normalizedRow, $normalizedToOriginalColumnNameMapping);
            if ($this->failOnErrors($importElement->getId(), $errors, $context)) {
                continue;
            }

            $productId = $productNumberIdMapping[mb_strtolower($normalizedRow['productNumber'])] ?? null;
            if (!$productId) {
                $errors->addError(StockImportException::createProductNotFoundError($normalizedRow['productNumber']));
            }
            $location = $this->stockLocationReferenceFinder->findStockLocationReference([
                'binLocationCode' => $normalizedRow['binLocationCode'] ?? null,
                'warehouseCode' => $normalizedRow['warehouseCode'] ?? null,
                'warehouseName' => $normalizedRow['warehouseName'] ?? null,
            ], $context);
            if (!$location) {
                $errors->addError(StockImportException::createStockLocationNotFoundError());
            }

            if ($this->failOnErrors($importElement->getId(), $errors, $context)) {
                continue;
            }

            $hasStockValue = isset($normalizedRow['stock']) && $normalizedRow['stock'] !== '';
            $hasChangeValue = isset($normalizedRow['change']) && $normalizedRow['change'] !== '';

            try {
                if ($hasStockValue || $hasChangeValue) {
                    $this->updateStock(
                        $import,
                        $importElement,
                        $normalizedRow,
                        $location,
                        $productId,
                        $errors,
                        $context,
                    );
                }

                if ($this->failOnErrors($importElement->getId(), $errors, $context)) {
                    continue;
                }

                $hasReorderPointValue = isset($normalizedRow['reorderPoint']) && $normalizedRow['reorderPoint'] !== '';
                if ($hasReorderPointValue) {
                    $this->updateReorderPoint(
                        $normalizedRow,
                        $productId,
                        $pickwareProductsByProductId,
                        $context,
                    );
                }

                $hasDefaultBinLocationValue = isset($normalizedRow['defaultBinLocation'])
                    && $normalizedRow['defaultBinLocation'] !== '';
                if ($hasDefaultBinLocationValue) {
                    $this->updateDefaultBinLocation(
                        $importElement,
                        $normalizedRow,
                        $location,
                        $productId,
                        $binLocationsById,
                        $productWarehouseConfigurationsByConcatenatedProductAndWarehouseId,
                        $errors,
                        $context,
                    );
                }
            } catch (Throwable $exception) {
                throw ImportException::rowImportError($exception, $importElement->getRowNumber());
            }
        }

        $nextRowNumberToRead += $this->batchSize;

        return $nextRowNumberToRead;
    }

    private function updateStock(
        ImportExportEntity $import,
        ImportExportElementEntity $importElement,
        array $normalizedRow,
        array $location,
        string $productId,
        JsonApiErrors $errors,
        Context $context
    ): void {
        try {
            $this->entityManager->runInTransactionWithRetry(
                function () use ($location, $errors, $context, $import, $normalizedRow, $importElement, $productId): void {
                    $stockChange = $this->stockChangeCalculator->calculateStockChange(
                        $normalizedRow,
                        $productId,
                        $location,
                        $errors,
                        $context,
                    );

                    if ($this->failOnErrors($importElement->getId(), $errors, $context) || $stockChange === 0) {
                        return;
                    }

                    if ($location['type'] === StockLocationReferenceFinder::TYPE_WAREHOUSES) {
                        $this->lockProductStocks(
                            $productId,
                            $context,
                        );

                        if ($stockChange > 0) {
                            $stockingRequest = new StockingRequest(
                                [new ProductQuantity($productId, $stockChange)],
                                $location['warehouseIds'][0] ?? null,
                            );
                            $stockingSolution = $this->stockingStrategy->calculateStockingSolution(
                                $stockingRequest,
                                $context,
                            );
                            $stockMovements = $stockingSolution->createStockMovementsWithSource(
                                StockLocationReference::import(),
                                [
                                    'userId' => $import->getUserId(),
                                ],
                            );
                            $this->stockMovementService->moveStock($stockMovements, $context);
                        } else {
                            $pickingRequest = $this->pickingRequestSolver->solvePickingRequestInWarehouses(
                                new PickingRequest([
                                    new ProductPickingRequest(
                                        $productId,
                                        -1 * $stockChange,
                                    ),
                                ]),
                                $location['warehouseIds'],
                                $context,
                            );
                            if ($pickingRequest->isCompletelyPickable()) {
                                $stockMovements = $pickingRequest->createStockMovementsWithDestination(
                                    StockLocationReference::import(),
                                    [
                                        'userId' => $import->getUserId(),
                                    ],
                                );
                                $this->stockMovementService->moveStock($stockMovements, $context);
                            } else {
                                $errors->addError(StockImportException::createNotEnoughStockToPickError());
                            }
                        }
                    } elseif ($location['type'] === StockLocationReferenceFinder::TYPE_SPECIFIC_LOCATION) {
                        $stockMovement = StockMovement::create([
                            'productId' => $productId,
                            'source' => StockLocationReference::import(),
                            'destination' => $location['stockLocationReference'],
                            'quantity' => $stockChange,
                            'userId' => $import->getUserId(),
                        ]);

                        $this->stockMovementService->moveStock([$stockMovement], $context);
                    } else {
                        $errors->addError(StockImportException::createUnsupportedStockLocationError());
                    }
                },
            );
        } catch (StockMovementServiceValidationException $e) {
            $errors->addError($e->serializeToJsonApiError());
        }
    }

    private function updateReorderPoint(
        array $normalizedRow,
        string $productId,
        array &$pickwareProductsPerProductId,
        Context $context
    ): void {
        /** @var PickwareProductEntity $pickwareProduct */
        $pickwareProduct = $pickwareProductsPerProductId[$productId] ?? null;

        if (!$pickwareProduct) {
            $pickwareProduct = $this->entityManager->findOneBy(
                PickwareProductDefinition::class,
                [
                    'productId' => $productId,
                ],
                $context,
            );

            $pickwareProductsPerProductId[$productId] = $pickwareProduct;
        }

        $this->entityManager->upsert(
            PickwareProductDefinition::class,
            [[
                'id' => $pickwareProduct ? $pickwareProduct->getId() : Uuid::randomHex(),
                'productId' => $productId,
                'reorderPoint' => $normalizedRow['reorderPoint'],
            ],
            ],
            $context,
        );
    }

    private function updateDefaultBinLocation(
        ImportExportElementEntity $importElement,
        array $normalizedRow,
        array $location,
        string $productId,
        array &$binLocationsById,
        array &$productWarehouseConfigurationsByConcatenatedProductAndWarehouseId,
        JsonApiErrors $errors,
        Context $context
    ): void {
        /** @var StockLocationReference $stockLocationReference */
        $stockLocationReference = $location['stockLocationReference'] ?? null;
        $foundBinLocation = $location['type'] === StockLocationReferenceFinder::TYPE_SPECIFIC_LOCATION
            && $stockLocationReference
            && $stockLocationReference->getLocationTypeTechnicalName() === LocationTypeDefinition::TECHNICAL_NAME_BIN_LOCATION;
        $binLocationCode = $normalizedRow['binLocationCode'] ?? null;
        $isUnknownBinLocation = $binLocationCode === StockLocationReferenceFinder::BIN_LOCATION_CODE_UNKNOWN;
        if ($normalizedRow['defaultBinLocation'] && !$foundBinLocation) {
            $errors->addError(StockImportException::createBinLocationOrWarehouseForDefaultBinLocationMissing());
            $this->failOnErrors($importElement->getId(), $errors, $context);

            return;
        }

        if (!$isUnknownBinLocation && $foundBinLocation) {
            $concatenatedProductAndWarehouseId = $productId . $stockLocationReference->getPrimaryKey();
            /** @var BinLocationEntity $binLocation */
            $binLocation = $binLocationsById[$stockLocationReference->getPrimaryKey()] ?? null;
            /** @var ProductWarehouseConfigurationEntity $configuration */
            $configuration = $productWarehouseConfigurationsByConcatenatedProductAndWarehouseId[$concatenatedProductAndWarehouseId] ?? null;

            if (!$binLocation) {
                $binLocation = $this->entityManager->findByPrimaryKey(
                    BinLocationDefinition::class,
                    $stockLocationReference->getPrimaryKey(),
                    $context,
                );
                $binLocationsById[$stockLocationReference->getPrimaryKey()] = $binLocation;
            }

            if (!$configuration) {
                $configuration = $this->entityManager->findOneBy(
                    ProductWarehouseConfigurationDefinition::class,
                    [
                        'productId' => $productId,
                        'warehouseId' => $binLocation->getWarehouseId(),
                    ],
                    $context,
                );
                $productWarehouseConfigurationsByConcatenatedProductAndWarehouseId[$concatenatedProductAndWarehouseId] = $configuration;
            }

            $payload = null;
            if ($normalizedRow['defaultBinLocation']) {
                // Upsert product warehouse configuration with given default bin location
                $payload = [
                    'id' => $configuration ? $configuration->getId() : Uuid::randomHex(),
                    'productId' => $productId,
                    'warehouseId' => $binLocation->getWarehouseId(),
                    'defaultBinLocationId' => $binLocation->getId(),
                ];
            } elseif ($configuration && $configuration->getDefaultBinLocationId() === $binLocation->getId()) {
                // Remove current default bin location from the product warehouse configuration
                $payload = [
                    'id' => $configuration->getId(),
                    'defaultBinLocationId' => null,
                ];
            }

            if ($payload) {
                $this->entityManager->upsert(
                    ProductWarehouseConfigurationDefinition::class,
                    [$payload],
                    $context,
                );
            }
        }
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

    private function validateRowSchema(array $normalizedRow, array $normalizedToOriginalColumnNameMapping): JsonApiErrors
    {
        $errors = $this->validator->validateRow($normalizedRow, $normalizedToOriginalColumnNameMapping);

        if (isset($normalizedRow['binLocationCode'])
            && !isset($normalizedRow['warehouseCode'])
            && !isset($normalizedRow['warehouseName'])
        ) {
            $errors->addError(StockImportException::createWarehouseForBinLocationMissing());
        }

        return $errors;
    }

    private function lockProductStocks(string $productId, Context $context): void
    {
        $this->entityManager->lockPessimistically(StockDefinition::class, ['productId' => $productId], $context);
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
