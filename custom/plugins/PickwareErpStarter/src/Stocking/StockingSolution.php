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

use Pickware\PickwareErpStarter\StockApi\StockLocationReference;
use Pickware\PickwareErpStarter\StockApi\StockMovement;

class StockingSolution
{
    /**
     * @var ProductQuantityLocation[]
     */
    private $productQuantityLocations;

    public function __construct(array $productQuantityLocations)
    {
        $this->productQuantityLocations = $productQuantityLocations;
    }

    public function getProductQuantityLocations(): array
    {
        return $this->productQuantityLocations;
    }

    /**
     * @param array $stockMovementMetaData
     * @return StockMovement[]
     */
    public function createStockMovementsWithSource(
        StockLocationReference $sourceLocation,
        $stockMovementMetaData = []
    ): array {
        $stockMovements = [];

        foreach ($this->productQuantityLocations as $productQuantityLocation) {
            $stockMovements[] = StockMovement::create(array_merge(
                $stockMovementMetaData,
                [
                    'productId' => $productQuantityLocation->getProductQuantity()->getProductId(),
                    'quantity' => $productQuantityLocation->getProductQuantity()->getQuantity(),
                    'source' => $sourceLocation,
                    'destination' => $productQuantityLocation->getStockLocationReference(),
                ],
            ));
        }

        return $stockMovements;
    }
}
