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

use Pickware\PickwareErpStarter\Stock\Model\LocationTypeDefinition;

class AlphanumericalRoutingStrategy implements RoutingStrategy
{
    public function apply(PickingRequest $pickingRequest): void
    {
        $pickingRequest->sort(
            function (
                ProductPickingRequest $productPickingRequestA,
                ProductPickingRequest $productPickingRequestB
            ): int {
                $pickLocationsA = $productPickingRequestA->getPickLocations();
                $pickLocationsB = $productPickingRequestB->getPickLocations();

                $filterBinLocationPickLocations = fn (PickLocation $pickLocation) => $pickLocation->getLocationTypeTechnicalName() === LocationTypeDefinition::TECHNICAL_NAME_BIN_LOCATION;
                $binLocationPickLocationsA = array_filter($pickLocationsA, $filterBinLocationPickLocations);
                $binLocationPickLocationsB = array_filter($pickLocationsB, $filterBinLocationPickLocations);

                $numberOfBinLocationsA = count($binLocationPickLocationsA);
                $numberOfBinLocationsB = count($binLocationPickLocationsB);

                $productNumberA = $productPickingRequestA->getProductSnapshot()['productNumber'] ?? '';
                $productNumberB = $productPickingRequestB->getProductSnapshot()['productNumber'] ?? '';

                // Sort picking requests without bin location stock locations to the front
                if ($numberOfBinLocationsA === 0 && $numberOfBinLocationsB === 0) {
                    return strcmp($productNumberA, $productNumberB);
                }

                if ($numberOfBinLocationsA === 0 || $numberOfBinLocationsB === 0) {
                    return $numberOfBinLocationsA - $numberOfBinLocationsB;
                }

                $binLocationCodeA = array_shift($binLocationPickLocationsA)->getBinLocationCode();
                $binLocationCodeB = array_shift($binLocationPickLocationsB)->getBinLocationCode();

                // If the first bin location stock location is identical, sort by product number, alphanumerically
                if ($binLocationCodeA === $binLocationCodeB) {
                    return strcmp($productNumberA, $productNumberB);
                }

                // Sort picking requests by their first bin location stock location code, alphanumerically
                return strcmp($binLocationCodeA, $binLocationCodeB);
            },
        );
    }
}
