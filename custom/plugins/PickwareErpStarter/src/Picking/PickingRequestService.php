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
use Shopware\Core\Framework\Context;

class PickingRequestService
{
    private PickingRequestFactory $pickingRequestFactory;
    private PickingRequestSolver $pickingRequestSolver;

    /**
     * @deprecated tag:next-major First param will accept PickingRequestFactory only, second parameter will accept
     *     PickingRequestSolver only, third param will be removed
     * @param PickingRequestFactory|EntityManager $pickingRequestFactoryOrEntityManager
     * @param PickingRequestSolver|PickingStrategy $pickingRequestSolverOrPickingStrategy
     */
    public function __construct(
        $pickingRequestFactoryOrEntityManager,
        $pickingRequestSolverOrPickingStrategy,
        ?RoutingStrategy $routingStrategy = null
    ) {
        if ($pickingRequestFactoryOrEntityManager instanceof PickingRequestFactory) {
            $this->pickingRequestFactory = $pickingRequestFactoryOrEntityManager;
        }
        if ($pickingRequestSolverOrPickingStrategy instanceof PickingRequestSolver) {
            $this->pickingRequestSolver = $pickingRequestSolverOrPickingStrategy;
        }
        // Since we need to be backwards compatible for the DI container only and not for the actual functionality,
        // this service just does not work when deprecated parameters are passed.
    }

    /**
     * @param string[] $warehouseIds list of warehouse IDs in which the picking may occur
     */
    public function createAndSolvePickingRequestForOrderInWarehouses(
        string $orderId,
        array $warehouseIds,
        Context $context
    ): PickingRequest {
        $pickingRequest = $this->pickingRequestFactory->createPickingRequestsForOrder($orderId, $context);

        return $this->pickingRequestSolver->solvePickingRequestInWarehouses($pickingRequest, $warehouseIds, $context);
    }

    /**
     * @deprecated tag:next-major Method will be removed, use createAndSolvePickingRequestForOrderInWarehouses instead
     * @param string[] $warehouseIds list of warehouse IDs in which the picking may occur
     */
    public function createPickingRequestForOrder(
        array $warehouseIds,
        string $orderId,
        Context $context
    ): PickingRequest {
        return $this->createAndSolvePickingRequestForOrderInWarehouses($orderId, $warehouseIds, $context);
    }

    /**
     * @deprecated tag:next-major Method will be removed, use
     *     PickingRequestSolver::solvePickingRequestInWarehouses instead
     * @param ProductPickingRequest[] $productPickingRequests
     * @param string[]|null $warehouseIds (optional) list of warehouse IDs in which the picking may occur
     */
    public function createPickingRequestForProductPickingRequests(
        array $productPickingRequests,
        ?array $warehouseIds,
        Context $context
    ): PickingRequest {
        $pickingRequest = new PickingRequest($productPickingRequests);

        return $this->pickingRequestSolver->solvePickingRequestInWarehouses($pickingRequest, $warehouseIds, $context);
    }

    /**
     * @deprecated tag:next-major Method will be removed, use
     *     PickingRequestFactory::createPickingRequestsForOrder instead
     * @return ProductPickingRequest[]
     */
    public function createProductPickingRequestsFromOrder(string $orderId, Context $context): array
    {
        return array_values(
            $this->pickingRequestFactory->createPickingRequestsForOrder($orderId, $context)->getElements(),
        );
    }

    /**
     * @deprecated tag:next-major Method will be removed, use
     *     PickingRequestFactory::usesProductOrthogonalPickingStrategy instead
     */
    public function usesProductOrthogonalStockingStrategy(): bool
    {
        return $this->pickingRequestSolver->usesProductOrthogonalPickingStrategy();
    }
}
