<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Picking;

use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\Stock\Model\LocationTypeDefinition;
use Pickware\PickwareErpStarter\Stock\Model\StockCollection;
use Pickware\PickwareErpStarter\Stock\Model\StockDefinition;
use Pickware\PickwareErpStarter\Stock\Model\StockEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;

class PickingRequestSolver
{
    private EntityManager $entityManager;
    private PickingStrategy $pickingStrategy;
    private RoutingStrategy $routingStrategy;

    public function __construct(
        EntityManager $entityManager,
        PickingStrategy $pickingStrategy,
        RoutingStrategy $routingStrategy
    ) {
        $this->entityManager = $entityManager;
        $this->pickingStrategy = $pickingStrategy;
        $this->routingStrategy = $routingStrategy;
    }

    public function solvePickingRequestInWarehouses(
        PickingRequest $pickingRequest,
        ?array $warehouseIds,
        Context $context
    ): PickingRequest {
        $this->addPickLocationsToPickingRequest($pickingRequest, $warehouseIds, $context);
        $this->pickingStrategy->apply($pickingRequest);
        $this->pickingStrategy->assignQuantityToPick($pickingRequest);
        $this->routingStrategy->apply($pickingRequest);

        return $pickingRequest;
    }

    /**
     * @param string[]|null $warehouseIds (optional) list of warehouse ids to filter the pick locations
     */
    private function addPickLocationsToPickingRequest(
        PickingRequest $pickingRequest,
        ?array $warehouseIds,
        Context $context
    ): void {
        $stocks = $this->getStocksInWarehouses($pickingRequest->getProductIds(), $warehouseIds, $context);

        array_map(function (ProductPickingRequest $productPickingRequest) use ($stocks): void {
            $stocks = array_values(
                array_filter($stocks->getElements(), fn (StockEntity $stock) => $stock->getProductId() === $productPickingRequest->getProductId()),
            );

            $pickLocations = array_map(
                function (StockEntity $stock) {
                    // We fetched stock in warehouses and bin locations. See self::getStocksInWarehouses()
                    $warehouse = $stock->getWarehouse() ?: $stock->getBinLocation()->getWarehouse();
                    $pickLocationWarehouse = new PickLocationWarehouse(
                        $warehouse->getId(),
                        $warehouse->getName(),
                        $warehouse->getCode(),
                        $warehouse->getIsDefault(),
                        $warehouse->getCreatedAt(),
                    );

                    $pickLocation = new PickLocation(
                        $stock->getId(),
                        $stock->getLocationTypeTechnicalName(),
                        $pickLocationWarehouse,
                        $stock->getQuantity(),
                    );

                    if ($pickLocation->getLocationTypeTechnicalName() === LocationTypeDefinition::TECHNICAL_NAME_BIN_LOCATION) {
                        $pickLocation->setBinLocationId($stock->getBinLocationId());
                        $pickLocation->setBinLocationCode($stock->getBinLocation()->getCode());
                    }

                    return $pickLocation;
                },
                $stocks,
            );

            $productPickingRequest->setPickLocations($pickLocations);
        }, $pickingRequest->getElements());
    }

    /**
     * @param string[] $productIds
     * @param string[]|null $warehouseIds (optional) warehouse filter
     * @return StockCollection
     */
    private function getStocksInWarehouses(
        array $productIds,
        ?array $warehouseIds,
        Context $context
    ): EntityCollection {
        $stockCriteria = new Criteria();
        $stockCriteria->addFilter(
            new EqualsAnyFilter(
                'locationType.technicalName',
                [
                    LocationTypeDefinition::TECHNICAL_NAME_WAREHOUSE,
                    LocationTypeDefinition::TECHNICAL_NAME_BIN_LOCATION,
                ],
            ),
            new RangeFilter('quantity', ['gt' => 0]),
            new EqualsAnyFilter('productId', $productIds),
        );

        if ($warehouseIds) {
            $stockCriteria->addFilter(
                new MultiFilter('OR', [
                    new EqualsAnyFilter('warehouseId', $warehouseIds),
                    new EqualsAnyFilter('binLocation.warehouseId', $warehouseIds),
                ]),
            );
        }

        return $this->entityManager->findBy(
            StockDefinition::class,
            $stockCriteria,
            $context,
            [
                'locationType',
                'warehouse',
                'binLocation.warehouse',
            ],
        );
    }

    public function usesProductOrthogonalPickingStrategy(): bool
    {
        return ($this->pickingStrategy instanceof ProductOrthogonalPickingStrategy);
    }
}
