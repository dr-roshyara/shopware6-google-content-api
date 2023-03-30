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

class AlphanumericalPickingStrategy implements ProductOrthogonalPickingStrategy
{
    private ?WarehouseComparator $warehouseComparator;

    public function __construct(?WarehouseComparator $warehouseComparator = null)
    {
        $this->warehouseComparator = $warehouseComparator;
    }

    public function apply(PickingRequest $pickingRequest): void
    {
        /** @var ProductPickingRequest $productPickingRequest */
        foreach ($pickingRequest as $productPickingRequest) {
            $pickLocations = $productPickingRequest->getPickLocations();

            usort($pickLocations, function (PickLocation $pickLocationA, PickLocation $pickLocationB) {
                if ($this->warehouseComparator
                    && $pickLocationA->getPickLocationWarehouse()->getId() !== $pickLocationB->getPickLocationWarehouse()->getId()) {
                    // If a warehouse comparator was provided, sort the pick locations by warehouse primarily
                    return $this->warehouseComparator->compare(
                        $pickLocationA->getPickLocationWarehouse(),
                        $pickLocationB->getPickLocationWarehouse(),
                    );
                }

                // Any non-bin-location stock locations (i.e. warehouse stocks) are sorted to the end
                if (!$pickLocationB->getBinLocationCode() && !$pickLocationA->getBinLocationCode()) {
                    return 0;
                }
                if (!$pickLocationA->getBinLocationCode()) {
                    return 1;
                }
                if (!$pickLocationB->getBinLocationCode()) {
                    return -1;
                }

                // Bin location stocks of the same warehouse are sorted alphanumerically
                return strcmp($pickLocationA->getBinLocationCode(), $pickLocationB->getBinLocationCode());
            });

            $productPickingRequest->setPickLocations($pickLocations);
        }
    }

    /**
     * Distributed the quantity of each product by picking as much as possibly from the first stock location and then
     * moving on to the next stock location until all quantities of the product are picked.
     */
    public function assignQuantityToPick(PickingRequest $pickingRequest): void
    {
        /** @var ProductPickingRequest $productPickingRequest */
        foreach ($pickingRequest as $productPickingRequest) {
            $quantityLeftToPick = $productPickingRequest->getQuantity();
            foreach ($productPickingRequest->getPickLocations() as $pickLocation) {
                $quantity = min($pickLocation->getQuantityInStock(), $quantityLeftToPick);
                $pickLocation->setQuantityToPick($quantity);
                $quantityLeftToPick -= $quantity;

                if ($quantityLeftToPick === 0) {
                    break;
                }
            }
        }
    }
}
