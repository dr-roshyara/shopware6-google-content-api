<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\StockApi;

use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\Picking\PickingRequest;
use Pickware\PickwareErpStarter\Picking\PickingRequestService;
use Pickware\PickwareErpStarter\Picking\PickingRequestSolver;
use Pickware\PickwareErpStarter\Picking\ProductPickingRequest;
use Pickware\PickwareErpStarter\Stock\Model\StockDefinition;
use Pickware\PickwareErpStarter\Stocking\ProductOrthogonalStockingStrategy;
use Pickware\PickwareErpStarter\Stocking\ProductQuantity;
use Pickware\PickwareErpStarter\Stocking\StockingRequest;
use Pickware\PickwareErpStarter\Stocking\StockingStrategy;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\SumAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\Bucket;

class TotalStockWriter
{
    private EntityManager $entityManager;
    private PickingRequestSolver $pickingRequestSolver;
    private StockMovementService $stockMovementService;
    private StockingStrategy $stockingStrategy;

    /**
     * @deprecated tag:next-major $pickingRequestServiceOrPickingRequestSolver will accept PickingRequestSolver only
     * @param PickingRequestSolver|PickingRequestService $pickingRequestServiceOrPickingRequestSolver
     */
    public function __construct(
        EntityManager $entityManager,
        $pickingRequestServiceOrPickingRequestSolver,
        StockMovementService $stockMovementService,
        StockingStrategy $stockingStrategy
    ) {
        if ($pickingRequestServiceOrPickingRequestSolver instanceof PickingRequestSolver) {
            $this->pickingRequestSolver = $pickingRequestServiceOrPickingRequestSolver;
        }
        // Since we need to be backwards compatible for the DI container only and not for the actual functionality,
        // this service just does not work when no PickingRequestSolver is passed

        $this->entityManager = $entityManager;
        $this->stockMovementService = $stockMovementService;
        $this->stockingStrategy = $stockingStrategy;
    }

    /**
     * @param array $productStocks Associative array with [productId] => newStock
     * @param StockLocationReference $externalLocation External location where the corresponding stock gets
     *        removed/added
     */
    public function setTotalStockForProducts(
        array $productStocks,
        StockLocationReference $externalLocation,
        Context $context
    ): void {
        if (count($productStocks) === 0) {
            return;
        }
        if ($context->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        if (min($productStocks) < 0) {
            throw TotalStockWriterException::negativeStockNotAllowed();
        }

        $this->entityManager->runInTransactionWithRetry(
            function () use ($externalLocation, $productStocks, $context): void {
                $criteria = EntityManager::createCriteriaFromArray([
                    'locationType.internal' => true,
                    'productId' => array_keys($productStocks),
                ]);
                $this->entityManager->lockPessimistically(StockDefinition::class, $criteria, $context);
                $criteria->addAggregation(
                    new TermsAggregation(
                        'products',
                        'productId',
                        null,
                        null,
                        new SumAggregation('quantity', 'quantity'),
                    ),
                );
                $aggregationResult = $this->entityManager->getRepository(
                    StockDefinition::class,
                )->aggregate($criteria, $context);
                /** @var Bucket[] $productStocks */
                $buckets = $aggregationResult->get('products')->getBuckets();
                $currentProductStockByProductId = [];
                // There is one $bucket for each product
                foreach ($buckets as $bucket) {
                    // key of the $bucket is the product ID
                    // $bucket->getResult() is the sum of stocks
                    $currentProductStockByProductId[$bucket->getKey()] = (int) $bucket->getResult()->getSum();
                }

                $stockMovements = [];
                foreach ($productStocks as $productId => $stock) {
                    if (array_key_exists($productId, $currentProductStockByProductId)) {
                        $currentStock = $currentProductStockByProductId[$productId];
                    } else {
                        $currentStock = 0;
                    }
                    $stockChange = $stock - $currentStock;

                    if ($stockChange > 0) {
                        $stockingRequest = new StockingRequest([new ProductQuantity($productId, $stockChange)], null);
                        $stockingSolution = $this->stockingStrategy->calculateStockingSolution($stockingRequest, $context);
                        $stockMovements[] = $stockingSolution->createStockMovementsWithSource($externalLocation);
                    } elseif ($stockChange < 0) {
                        $pickingRequest = $this->pickingRequestSolver->solvePickingRequestInWarehouses(
                            new PickingRequest([
                                new ProductPickingRequest(
                                    $productId,
                                    -1 * $stockChange,
                                ),
                            ]),
                            null,
                            $context,
                        );
                        if (!$pickingRequest->isCompletelyPickable()) {
                            throw TotalStockWriterException::notEnoughStock($productId);
                        }
                        $stockMovements[] = $pickingRequest->createStockMovementsWithDestination($externalLocation);
                    } else {
                        continue;
                    }

                    // Performance optimization:
                    // In case both strategies (for picking and stocking) are product-orthogonal we can collect all
                    // emerging stock-movements and write them together in one call of moveStock.
                    // Otherwise we need to write each emerging stock movement immediately.
                    if (!($this->stockingStrategy instanceof ProductOrthogonalStockingStrategy)
                        || !$this->pickingRequestSolver->usesProductOrthogonalPickingStrategy()
                    ) {
                        $this->stockMovementService->moveStock(array_merge(...$stockMovements), $context);
                        $stockMovements = [];
                    }
                }
                if (count($stockMovements) > 0) {
                    $this->stockMovementService->moveStock(array_merge(...$stockMovements), $context);
                }
            },
        );
    }
}
