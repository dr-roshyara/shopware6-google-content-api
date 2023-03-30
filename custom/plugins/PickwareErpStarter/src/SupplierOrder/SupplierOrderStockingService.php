<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\SupplierOrder;

use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\StockApi\StockLocationReference;
use Pickware\PickwareErpStarter\StockApi\StockMovement;
use Pickware\PickwareErpStarter\StockApi\StockMovementService;
use Pickware\PickwareErpStarter\Stocking\ProductQuantity;
use Pickware\PickwareErpStarter\Stocking\StockingRequestService;
use Pickware\PickwareErpStarter\Stocking\StockingStrategy;
use Pickware\PickwareErpStarter\SupplierOrder\Model\SupplierOrderDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;

class SupplierOrderStockingService
{
    private EntityManager $entityManager;
    private StateMachineRegistry $stateMachineRegistry;
    private StockingRequestService $stockingRequestService;
    private StockingStrategy $stockingStrategy;
    private StockMovementService $stockMovementService;

    public function __construct(
        EntityManager $entityManager,
        StateMachineRegistry $stateMachineRegistry,
        StockingRequestService $stockingRequestService,
        StockingStrategy $stockingStrategy,
        StockMovementService $stockMovementService
    ) {
        $this->entityManager = $entityManager;
        $this->stateMachineRegistry = $stateMachineRegistry;
        $this->stockingRequestService = $stockingRequestService;
        $this->stockingStrategy = $stockingStrategy;
        $this->stockMovementService = $stockMovementService;
    }

    /**
     * Stocks the given supplier order into the respective warehouse of that order. Our strategy ist that we first
     * "stock" the order itself by moving stock from 'unknown' into the supplier order. And then move stock from the
     * supplier order into the warehouse.
     * Updates the supplier oder state to 'delivered'
     */
    public function stockSupplierOrder(string $supplierOrderId, Context $context): void
    {
        $this->entityManager->runInTransactionWithRetry(
            function () use ($context, $supplierOrderId, &$exceptionToThrow): void {
                $stockingRequest = $this->stockingRequestService->createStockingRequestForSupplierOrder(
                    $supplierOrderId,
                    $context,
                );

                // Create stock movements to move stock from unknown into the supplier order based on all the necessary
                // quantities that are stocked into the warehouse
                $stockMovementsIntoSupplierOrder = array_map(
                    fn (ProductQuantity $productQuantity) => StockMovement::create([
                        'productId' => $productQuantity->getProductId(),
                        'quantity' => $productQuantity->getQuantity(),
                        'source' => StockLocationReference::unknown(),
                        'destination' => StockLocationReference::supplierOrder($supplierOrderId),
                    ]),
                    $stockingRequest->getProductQuantities(),
                );

                // Create stock movements from the supplier order into the warehouse
                $stockMovementsIntoWarehouse = $this->stockingStrategy
                    ->calculateStockingSolution($stockingRequest, $context)
                    ->createStockMovementsWithSource(StockLocationReference::supplierOrder($supplierOrderId));

                $this->stockMovementService->moveStock(
                    array_values(array_merge(
                        $stockMovementsIntoSupplierOrder,
                        $stockMovementsIntoWarehouse,
                    )),
                    $context,
                );

                $this->stateMachineRegistry->transition(
                    new Transition(
                        SupplierOrderDefinition::ENTITY_NAME,
                        $supplierOrderId,
                        SupplierOrderStateMachine::TRANSITION_DELIVER,
                        'stateId',
                    ),
                    $context,
                );
            },
        );
    }
}
