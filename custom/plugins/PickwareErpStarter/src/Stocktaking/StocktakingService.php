<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stocktaking;

use DateTime;
use InvalidArgumentException;
use Pickware\DalBundle\EntityManager;
use Pickware\DalBundle\ExceptionHandling\UniqueIndexHttpException;
use Pickware\PickwareErpStarter\Product\ProductNameFormatterService;
use Pickware\PickwareErpStarter\Stock\Model\StockCollection;
use Pickware\PickwareErpStarter\Stock\Model\StockDefinition;
use Pickware\PickwareErpStarter\Stock\Model\StockEntity;
use Pickware\PickwareErpStarter\Stocktaking\Model\StocktakeCollection;
use Pickware\PickwareErpStarter\Stocktaking\Model\StocktakeCountingProcessDefinition;
use Pickware\PickwareErpStarter\Stocktaking\Model\StocktakeDefinition;
use Pickware\PickwareErpStarter\Stocktaking\Model\StocktakeEntity;
use Pickware\PickwareErpStarter\Warehouse\Model\BinLocationCollection;
use Pickware\PickwareErpStarter\Warehouse\Model\BinLocationDefinition;
use Pickware\PickwareErpStarter\Warehouse\Model\BinLocationEntity;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Shopware\Core\System\User\UserCollection;
use Shopware\Core\System\User\UserDefinition;

class StocktakingService
{
    private EntityManager $entityManager;
    private NumberRangeValueGeneratorInterface $numberRangeValueGenerator;
    private StocktakingStockChangeService $stocktakingStockChangeService;
    private ProductNameFormatterService $productNameFormatterService;

    public function __construct(
        EntityManager $entityManager,
        NumberRangeValueGeneratorInterface $numberRangeValueGenerator,
        StocktakingStockChangeService $stocktakingStockChangeService,
        ProductNameFormatterService $productNameFormatterService
    ) {
        $this->entityManager = $entityManager;
        $this->numberRangeValueGenerator = $numberRangeValueGenerator;
        $this->stocktakingStockChangeService = $stocktakingStockChangeService;
        $this->productNameFormatterService = $productNameFormatterService;
    }

    /**
     * @param array $countingProcessPayloads multiple payloads of counting process entities
     * @return string[] ids of the created counting process entities
     */
    public function createCountingProcesses(array $countingProcessPayloads, Context $context): array
    {
        /** @var StocktakeCollection $stocktakes */
        $stocktakes = $this->entityManager->findBy(
            StocktakeDefinition::class,
            ['id' => array_unique(array_column($countingProcessPayloads, 'stocktakeId'))],
            $context,
        );

        // Note that (non-null) binLocationIds can be empty if the user saves counting processes for only the unknown
        // stock location in the warehouse.
        $binLocationIds = array_filter(array_unique(array_column($countingProcessPayloads, 'binLocationId')));
        $binLocations = new BinLocationCollection([]);
        if (count($binLocationIds) > 0) {
            $binLocations = $this->entityManager->findBy(
                BinLocationDefinition::class,
                ['id' => $binLocationIds],
                $context,
                ['warehouse'],
            );
        }

        /** @var UserCollection $users */
        $users = $this->entityManager->findBy(
            UserDefinition::class,
            ['id' => array_unique(array_column($countingProcessPayloads, 'userId'))],
            $context,
        );

        $stockInBinLocations = new StockCollection([]);
        if (count($binLocationIds) > 0) {
            /** @var StockCollection $stockInBinLocations */
            $stockInBinLocations = $this->entityManager->findBy(
                StockDefinition::class,
                ['binLocationId' => $binLocationIds],
                $context,
            );
        }

        $productsById = [];
        $updatedCountingProcessPayloads = [];
        foreach ($countingProcessPayloads as $countingProcessPayload) {
            if (isset($countingProcessPayload['stocktake'])) {
                throw new InvalidArgumentException('A stocktake can be passed by ID only.');
            }
            $stocktakeId = $countingProcessPayload['stocktakeId'] ?? null;
            if ($stocktakeId === null) {
                throw new InvalidArgumentException('Missing key "stocktakeId".');
            }

            $stocktake = $stocktakes->get($stocktakeId);
            if (!$stocktake) {
                throw new InvalidArgumentException(sprintf('No stocktake found with id %s', $stocktakeId));
            }
            if (!$stocktake->isActive()) {
                throw StocktakingException::stocktakeNotActive($stocktakeId, $stocktake->getTitle());
            }

            if (!isset($countingProcessPayload['items'])) {
                $countingProcessPayload['items'] = [];
            }

            if (isset($countingProcessPayload['binLocation'])) {
                throw new InvalidArgumentException('A bin location can be passed by ID only.');
            }
            if (isset($countingProcessPayload['binLocationId'])) {
                $binLocationId = $countingProcessPayload['binLocationId'];
                $binLocation = $binLocations->get($binLocationId);
                if (!$binLocation) {
                    throw new InvalidArgumentException(sprintf('No bin location found with id %s', $binLocationId));
                };
                $countingProcessPayload['binLocationSnapshot'] = [
                    'code' => $binLocation->getCode(),
                    'warehouseName' => $binLocation->getWarehouse()->getName(),
                    'warehouseCode' => $binLocation->getWarehouse()->getCode(),
                ];

                // If a bin location was counted (not the unknown stock location in warehouse), _all_ products will be
                // part of the stocktake. So if there are products that have stock in that bin location, that are _not_
                // part of the counting processes, they will be added as counting process item with quantity 0.
                // In other words: Products that are not explicitly counted, will be counted with quantity 0.
                $countedProductIds = array_column($countingProcessPayload['items'], 'productId');
                $uncountedStocksInBinLocation = $stockInBinLocations->filter(fn (StockEntity $stock) => (
                    $stock->getBinLocationId() === $binLocationId &&
                    !in_array($stock->getProductId(), $countedProductIds)
                ));
                foreach ($uncountedStocksInBinLocation as $uncountedStockInBinLocation) {
                    $countingProcessPayload['items'][] = [
                        'id' => Uuid::randomHex(),
                        'productId' => $uncountedStockInBinLocation->getProductId(),
                        'quantity' => 0,
                    ];
                }
            } else {
                $countingProcessPayload['binLocationSnapshot'] = null;
            }
            if (isset($countingProcessPayload['user'])) {
                throw new InvalidArgumentException('A user can be passed by ID only.');
            }
            if (isset($countingProcessPayload['userId'])) {
                $user = $users->get($countingProcessPayload['userId']);
                if (!$user) {
                    throw new InvalidArgumentException(sprintf('No user found with id %s', $countingProcessPayload['userId']));
                }
                $countingProcessPayload['userSnapshot'] = [
                    'firstName' => $user->getFirstName(),
                    'lastName' => $user->getLastName(),
                    'username' => $user->getUsername(),
                    'email' => $user->getEmail(),
                ];
            } else {
                $countingProcessPayload['userSnapshot'] = [];
            }

            // Fetch and format all product names in batches for each counting process. We cannot do this for _all_
            // counting processes at the beginning, because some products are added dynamically in the loop.
            $productIds = [];
            foreach ($countingProcessPayload['items'] as $countingProcessItem) {
                if (isset($countingProcessItem['productId'])) {
                    $productIds[] = $countingProcessItem['productId'];
                }
            }
            $productNamesByProductId = $this->productNameFormatterService->getFormattedProductNames($productIds, [], $context);

            foreach ($countingProcessPayload['items'] as &$countingProcessItem) {
                if (isset($countingProcessItem['productId'])) {
                    if (!isset($productsById[$countingProcessItem['productId']])) {
                        $productsById[$countingProcessItem['productId']] = $this->entityManager->findByPrimaryKey(
                            ProductDefinition::class,
                            $countingProcessItem['productId'],
                            $context,
                        );
                    }

                    /** @var ProductEntity $product */
                    $product = $productsById[$countingProcessItem['productId']];
                    if (!$product) {
                        throw new InvalidArgumentException(sprintf('No product found with id %s', $countingProcessItem['productId']));
                    }
                    $countingProcessItem['productSnapshot'] = [
                        'id' => $product->getId(),
                        'productNumber' => $product->getProductNumber(),
                        'name' => $productNamesByProductId[$product->getId()],
                    ];
                } else {
                    $countingProcessItem['productSnapshot'] = [];
                }
                unset($countingProcessItem);
            }

            $countingProcessPayload['id'] = $countingProcessPayload['id'] ?? Uuid::randomHex();
            $countingProcessPayload['number'] = $countingProcessPayload['number'] ?? $this->numberRangeValueGenerator->getValue(
                StocktakeCountingProcessNumberRange::TECHNICAL_NAME,
                $context,
                null,
            );
            $updatedCountingProcessPayloads[] = $countingProcessPayload;
        }

        try {
            $this->entityManager->createIfNotExists(
                StocktakeCountingProcessDefinition::class,
                $updatedCountingProcessPayloads,
                $context,
            );
        } catch (UniqueIndexHttpException $e) {
            if ($e->getErrorCode() !== CountingProcessUniqueIndexExceptionHandler::ERROR_CODE_STOCKTAKE_DUPLICATE_BIN_LOCATION) {
                throw $e;
            }

            throw StocktakingException::countingProcessForAtLeastOneBinLocationAlreadyExists(
                $binLocations->map(fn(BinLocationEntity $binLocation) => $binLocation->getCode()),
            );
        }

        return array_column($updatedCountingProcessPayloads, 'id');
    }

    public function completeStocktake(string $stocktakeId, string $userId, Context $context): void
    {
        /** @var StocktakeEntity $stocktake */
        $stocktake = $this->entityManager->getByPrimaryKey(StocktakeDefinition::class, $stocktakeId, $context);
        if ($stocktake->getImportExportId()) {
            throw StocktakingException::stocktakeAlreadyCompleted(
                $stocktakeId,
                $stocktake->getTitle(),
                $stocktake->getNumber(),
                $stocktake->getImportExportId(),
            );
        }

        $this->stocktakingStockChangeService->persistStocktakeStockChanges($stocktakeId, $userId, $context);
        $this->entityManager->update(
            StocktakeDefinition::class,
            [
                [
                    'id' => $stocktakeId,
                    'completedAt' => new DateTime(),
                ],
            ],
            $context,
        );
    }
}
