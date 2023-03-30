<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\OrderShipping;

use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\Picking\PickingRequestService;
use Pickware\PickwareErpStarter\Stock\Model\StockDefinition;
use Pickware\PickwareErpStarter\Stock\OrderStockInitializer;
use Pickware\PickwareErpStarter\StockApi\StockLocationReference;
use Pickware\PickwareErpStarter\StockApi\StockMovementService;
use Pickware\PickwareErpStarter\Stocking\StockingRequestService;
use Pickware\PickwareErpStarter\Stocking\StockingStrategy;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseDefinition;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseEntity;
use Pickware\ShopwareExtensionsBundle\OrderDelivery\PickwareOrderDeliveryCollection;
use Psr\EventDispatcher\EventDispatcherInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;

class OrderShippingService
{
    private EntityManager $entityManager;
    private PickingRequestService $pickingRequestService;
    private StockMovementService $stockMovementService;
    private StockingRequestService $stockingRequestService;
    private StockingStrategy $stockingStrategy;
    private OrderStockInitializer $orderStockInitializer;
    private StateMachineRegistry $stateMachineRegistry;
    private EventDispatcherInterface $eventDispatcher;

    public function __construct(
        EntityManager $entityManager,
        PickingRequestService $pickingRequestService,
        StockMovementService $stockMovementService,
        StockingRequestService $stockingRequestService,
        StockingStrategy $stockingStrategy,
        OrderStockInitializer $orderStockInitializer,
        StateMachineRegistry $stateMachineRegistry,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->entityManager = $entityManager;
        $this->pickingRequestService = $pickingRequestService;
        $this->stockMovementService = $stockMovementService;
        $this->stockingRequestService = $stockingRequestService;
        $this->stockingStrategy = $stockingStrategy;
        $this->orderStockInitializer = $orderStockInitializer;
        $this->stateMachineRegistry = $stateMachineRegistry;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function shipMultipleOrdersCompletely(array $orderIds, string $warehouseId, Context $context): void
    {
        if (count($orderIds) === 0) {
            return;
        }

        $this->checkLiveVersion($context);

        /** @var PreOrderShippingValidationEvent $preOrderShippingValidationEvent */
        $preOrderShippingValidationEvent = $this->eventDispatcher->dispatch(
            new PreOrderShippingValidationEvent($context, $orderIds),
            PreOrderShippingValidationEvent::EVENT_NAME,
        );

        if (count($preOrderShippingValidationEvent->getErrors()) > 0) {
            throw OrderShippingException::preOrderShippingValidationErrors($preOrderShippingValidationEvent->getErrors());
        }

        /** @var WarehouseEntity $warehouse */
        $warehouse = $this->entityManager->getByPrimaryKey(
            WarehouseDefinition::class,
            $warehouseId,
            $context,
        );
        $orders = $this->getOrders($orderIds, $context, ['deliveries']);

        $this->entityManager->runInTransactionWithRetry(
            function () use ($context, $warehouse, $orders): void {
                foreach ($orders as $order) {
                    $this->lockProductStocks($order->getId(), $context);

                    $pickingRequest = $this->pickingRequestService->createAndSolvePickingRequestForOrderInWarehouses(
                        $order->getId(),
                        [$warehouse->getId()],
                        $context,
                    );

                    if (!$pickingRequest->isCompletelyPickable()) {
                        throw new NotEnoughStockException($warehouse, $order, $pickingRequest->getStockShortage());
                    }

                    $orderDelivery = PickwareOrderDeliveryCollection::createFrom($order->getDeliveries())
                        ->getPrimaryOrderDelivery();

                    if (!$orderDelivery) {
                        continue;
                    }

                    $this->stateMachineRegistry->transition(
                        new Transition(
                            OrderDeliveryDefinition::ENTITY_NAME,
                            $orderDelivery->getId(),
                            StateMachineTransitionActions::ACTION_SHIP,
                            'stateId',
                        ),
                        $context,
                    );

                    // Shipping orders (moving stock _from_ a warehouse) does not run in batches, as the picking
                    // strategy depends on the current stock distribution. Move stock after each application of the
                    // picking strategy.
                    $this->stockMovementService->moveStock(
                        $pickingRequest->createStockMovementsWithDestination(
                            StockLocationReference::order($order->getId()),
                        ),
                        $context,
                    );
                }
            },
        );
    }

    public function shipOrderCompletely(string $orderId, string $warehouseId, Context $context): void
    {
        $this->checkLiveVersion($context);

        /** @var PreOrderShippingValidationEvent $preOrderShippingValidationEvent */
        $preOrderShippingValidationEvent = $this->eventDispatcher->dispatch(
            new PreOrderShippingValidationEvent($context, [$orderId]),
            PreOrderShippingValidationEvent::EVENT_NAME,
        );

        if (count($preOrderShippingValidationEvent->getErrors()) > 0) {
            throw OrderShippingException::preOrderShippingValidationErrors(
                $preOrderShippingValidationEvent->getErrors(),
            );
        }

        /** @var WarehouseEntity $warehouse */
        $warehouse = $this->entityManager->getByPrimaryKey(
            WarehouseDefinition::class,
            $warehouseId,
            $context,
        );

        /** @var OrderEntity $order */
        $order = $this->entityManager->getByPrimaryKey(OrderDefinition::class, $orderId, $context);

        // Throw an exception after the transaction completed, because throwing inside would mark any parent transaction
        // as roll-back-only where committing is not possible anymore. This can be done as long as no data has been
        // modified up to the point where the exception would be thrown, as otherwise it would be committed.
        $exceptionToThrow = null;

        $this->entityManager->runInTransactionWithRetry(
            function () use ($context, $warehouse, $order, &$exceptionToThrow): void {
                $this->lockProductStocks($order->getId(), $context);

                $pickingRequest = $this->pickingRequestService->createAndSolvePickingRequestForOrderInWarehouses(
                    $order->getId(),
                    [$warehouse->getId()],
                    $context,
                );
                if (!$pickingRequest->isCompletelyPickable()) {
                    // Not all quantity of the order line items could be distributed among the pick locations
                    $exceptionToThrow = new NotEnoughStockException(
                        $warehouse,
                        $order,
                        $pickingRequest->getStockShortage(),
                    );

                    return;
                }

                $stockMovements = $pickingRequest->createStockMovementsWithDestination(
                    StockLocationReference::order($order->getId()),
                );
                $this->stockMovementService->moveStock($stockMovements, $context);
            },
        );

        if ($exceptionToThrow) {
            throw $exceptionToThrow;
        }
    }

    public function returnMultipleOrdersCompletely(array $orderIds, string $warehouseId, Context $context): void
    {
        if (count($orderIds) === 0) {
            return;
        }

        $this->checkLiveVersion($context);

        /** @var WarehouseEntity $warehouse */
        $warehouse = $this->entityManager->getByPrimaryKey(
            WarehouseDefinition::class,
            $warehouseId,
            $context,
        );
        $orders = $this->getOrders($orderIds, $context, ['deliveries']);

        $this->entityManager->runInTransactionWithRetry(
            function () use ($context, $warehouse, $orders): void {
                $stockMovements = [];

                foreach ($orders as $order) {
                    $this->lockProductStocks($order->getId(), $context);

                    $stockingRequest = $this->stockingRequestService->createStockingRequestForOrder(
                        $order->getId(),
                        $warehouse->getId(),
                        $context,
                    );

                    $stockingSolution = $this->stockingStrategy->calculateStockingSolution($stockingRequest, $context);
                    array_push($stockMovements, ...$stockingSolution->createStockMovementsWithSource(
                        StockLocationReference::order($order->getId()),
                    ));

                    $orderDelivery = PickwareOrderDeliveryCollection::createFrom($order->getDeliveries())
                        ->getPrimaryOrderDelivery();

                    if (!$orderDelivery) {
                        continue;
                    }

                    $this->stateMachineRegistry->transition(
                        new Transition(
                            OrderDeliveryDefinition::ENTITY_NAME,
                            $orderDelivery->getId(),
                            StateMachineTransitionActions::ACTION_RETOUR,
                            'stateId',
                        ),
                        $context,
                    );
                }

                // Returning order (move stock into a warehouse) does run in batches, as the stocking solution does not
                // depend on the current stock distribution. Therefore, you can stock multiple products multiple times
                // in the same batch execution. Execute all stock movements in a single batch.
                $this->stockMovementService->moveStock($stockMovements, $context);
            },
        );
    }

    public function returnOrderCompletely(string $orderId, string $warehouseId, Context $context): void
    {
        $this->checkLiveVersion($context);
        $this->ensureWarehouseExists($warehouseId, $context);
        $this->ensureOrderExists($orderId, $context);
        $this->orderStockInitializer->initializeOrderIfNecessary($orderId, $context);

        $this->entityManager->runInTransactionWithRetry(
            function () use ($warehouseId, $context, $orderId): void {
                $this->lockProductStocks($orderId, $context);

                $stockingRequest = $this->stockingRequestService->createStockingRequestForOrder(
                    $orderId,
                    $warehouseId,
                    $context,
                );
                $stockingSolution = $this->stockingStrategy->calculateStockingSolution($stockingRequest, $context);
                $stockMovements = $stockingSolution->createStockMovementsWithSource(
                    StockLocationReference::order($orderId),
                );
                $this->stockMovementService->moveStock($stockMovements, $context);
            },
        );
    }

    private function lockProductStocks(string $orderId, Context $context): void
    {
        $this->entityManager->lockPessimistically(
            StockDefinition::class,
            [
                'product.orderLineItems.order.id' => $orderId,
                'product.orderLineItems.type' => OrderStockInitializer::ORDER_STOCK_RELEVANT_LINE_ITEM_TYPES,
            ],
            $context,
        );
    }

    private function checkLiveVersion(Context $context): void
    {
        if ($context->getVersionId() !== Defaults::LIVE_VERSION) {
            throw OrderShippingException::notInLiveVersion();
        }
    }

    private function ensureWarehouseExists(string $warehouseId, Context $context): void
    {
        $this->entityManager->getByPrimaryKey(
            WarehouseDefinition::class,
            $warehouseId,
            $context,
        );
    }

    private function ensureOrderExists(string $orderId, Context $context): void
    {
        $this->entityManager->getByPrimaryKey(OrderDefinition::class, $orderId, $context);
    }

    /**
     * @return OrderEntity[]
     */
    private function getOrders(array $orderIds, Context $context, array $associations = []): array
    {
        return $this->entityManager->findBy(
            OrderDefinition::class,
            new Criteria($orderIds),
            $context,
            $associations,
        )->getElements();
    }
}
