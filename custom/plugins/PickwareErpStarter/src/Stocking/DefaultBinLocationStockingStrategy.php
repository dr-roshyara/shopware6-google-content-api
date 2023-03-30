<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stocking;

use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\Config\Config;
use Pickware\PickwareErpStarter\StockApi\StockLocationReference;
use Pickware\PickwareErpStarter\Warehouse\Model\ProductWarehouseConfigurationDefinition;
use Pickware\PickwareErpStarter\Warehouse\Model\ProductWarehouseConfigurationEntity;
use Shopware\Core\Framework\Context;

class DefaultBinLocationStockingStrategy implements ProductOrthogonalStockingStrategy
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var EntityManager
     */
    private $entityManager;

    public function __construct(Config $config, EntityManager $entityManager)
    {
        $this->config = $config;
        $this->entityManager = $entityManager;
    }

    public function calculateStockingSolution(StockingRequest $stockingRequest, Context $context): StockingSolution
    {
        $warehouseId = $stockingRequest->getWarehouseId() ?: $this->config->getDefaultWarehouseId();
        $productIds = $stockingRequest->getProductIds();
        $productWarehouseConfigurations = $this->entityManager->findBy(
            ProductWarehouseConfigurationDefinition::class,
            [
                'productId' => $productIds,
                'warehouseId' => $warehouseId,
            ],
            $context,
        );
        $productDefaultBinLocationMapping = array_combine(
            $productWarehouseConfigurations->map(
                fn (ProductWarehouseConfigurationEntity $productWarehouseConfiguration) => $productWarehouseConfiguration->getProductId(),
            ),
            $productWarehouseConfigurations->map(
                fn (ProductWarehouseConfigurationEntity $productWarehouseConfiguration) => $productWarehouseConfiguration->getDefaultBinLocationId(),
            ),
        );

        $productQuantityLocations = array_map(
            function (ProductQuantity $productQuantity) use ($warehouseId, $productDefaultBinLocationMapping) {
                $defaultBinLocationId = $productDefaultBinLocationMapping[$productQuantity->getProductId()] ?? null;
                if ($defaultBinLocationId) {
                    $location = StockLocationReference::binLocation($defaultBinLocationId);
                } else {
                    $location = StockLocationReference::warehouse($warehouseId);
                }

                return new ProductQuantityLocation(
                    $location,
                    $productQuantity,
                );
            },
            $stockingRequest->getProductQuantities(),
        );

        return new StockingSolution($productQuantityLocations);
    }
}
