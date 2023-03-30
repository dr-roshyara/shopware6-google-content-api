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

use Closure;
use Pickware\PickwareErpStarter\StockApi\StockLocationReference;
use Pickware\PickwareErpStarter\StockApi\StockMovement;
use Pickware\PickwareErpStarter\Stocking\ProductQuantity;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @method ProductPickingRequest first()
 * @property ProductPickingRequest[] $elements
 */
class PickingRequest extends Collection
{
    public function getExpectedClass(): string
    {
        return ProductPickingRequest::class;
    }

    public function getProductIds(): array
    {
        return array_map(fn (ProductPickingRequest $productPickingRequest): string => $productPickingRequest->getProductId(), $this->elements);
    }

    /**
     * @return ProductPickingRequest[]
     */
    public function getProductPickingRequestsForProduct(string $productId): array
    {
        return array_values(array_filter(
            $this->elements,
            fn (ProductPickingRequest $productPickingRequest) => $productPickingRequest->getProductId() === $productId,
        ));
    }

    public function isCompletelyPickable(): bool
    {
        foreach ($this->elements as $productPickingRequest) {
            if (!$productPickingRequest->isEnoughStockAvailable()) {
                return false;
            }
        }

        return true;
    }

    /**
     * A picking request is considered empty if there is nothing to pick (i.e. there are no products with a quantity > 0
     * that needs picking). Note that this has nothing to do with actual pickability (see ::isCompletelyPickable()).
     */
    public function isEmpty(): bool
    {
        return count(
            array_filter(
                $this->elements,
                fn (ProductPickingRequest $productPickingRequest) => $productPickingRequest->getQuantity() > 0,
            ),
        ) === 0;
    }

    public function createStockMovementsWithDestination(
        StockLocationReference $destinationLocation,
        $stockMovementMetaData = []
    ): array {
        $stockMovements = [];

        foreach ($this->elements as $productPickingRequest) {
            foreach ($productPickingRequest->getPickLocations() as $pickLocation) {
                if ($pickLocation->getQuantityToPick() === 0) {
                    continue;
                }

                $stockMovements[] = StockMovement::create(array_merge(
                    $stockMovementMetaData,
                    [
                        'productId' => $productPickingRequest->getProductId(),
                        'source' => $pickLocation->getStockLocationReference(),
                        'destination' => $destinationLocation,
                        'quantity' => $pickLocation->getQuantityToPick(),
                    ],
                ));
            }
        }

        return $stockMovements;
    }

    /**
     * @return ProductQuantity[]
     */
    public function getStockShortage(): array
    {
        $shortage = [];
        foreach ($this->elements as $element) {
            if ($element->isEnoughStockAvailable()) {
                continue;
            }
            $shortage[] = new ProductQuantity($element->getProductId(), $element->getStockShortage());
        }

        return $shortage;
    }

    public function sort(Closure $closure): void
    {
        // parent::sort() keeps the index associations when sorting. As this class uses numeric indices this could lead
        // to unexpected results. As an example ['B', 'A'] would lead to  [1 => 'B', 0 => 'A']. To avoid that we
        // don't keep the index associations by just using usort instead of uasort.
        usort($this->elements, $closure);
    }
}
