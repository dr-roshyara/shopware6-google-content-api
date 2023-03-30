<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Picklist;

use Pickware\PickwareErpStarter\Picking\PickingRequest;
use Pickware\PickwareErpStarter\Picking\PickingRequestService;
use Pickware\PickwareErpStarter\Picking\PickLocation;
use Pickware\PickwareErpStarter\Picking\ProductPickingRequest;
use Pickware\PickwareErpStarter\Stock\Model\LocationTypeDefinition;
use Shopware\Core\Framework\Context;

class PicklistGenerator
{
    /**
     * @var PickingRequestService
     */
    private $pickingRequestService;

    public function __construct(PickingRequestService $pickingRequestService)
    {
        $this->pickingRequestService = $pickingRequestService;
    }

    public function generatePicklist(string $warehouseId, string $orderId, Context $context): PickingRequest
    {
        $pickingRequest = $this->pickingRequestService->createAndSolvePickingRequestForOrderInWarehouses(
            $orderId,
            [$warehouseId],
            $context,
        );

        return $this->filterNonBinLocationPickLocations($pickingRequest);
    }

    private function filterNonBinLocationPickLocations(
        PickingRequest $pickingRequest
    ): PickingRequest {
        /** @var ProductPickingRequest $productPickingRequest */
        foreach ($pickingRequest as $productPickingRequest) {
            $binLocationPickLocations = array_values(array_filter(
                $productPickingRequest->getPickLocations(),
                fn (PickLocation $pickLocation) => $pickLocation->getLocationTypeTechnicalName() === LocationTypeDefinition::TECHNICAL_NAME_BIN_LOCATION,
            ));

            $productPickingRequest->setPickLocations($binLocationPickLocations);
        }

        return $pickingRequest;
    }
}
