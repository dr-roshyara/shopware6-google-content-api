<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\OrderPickability;

use Pickware\PickwareErpStarter\Stock\WarehouseStockUpdatedEvent;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseDefinition;
use Pickware\ShopwareExtensionsBundle\OrderConfiguration\OrderConfigurationUpdatedEvent;
use Shopware\Core\Checkout\Order\OrderEvents;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Handles WHEN the order pickability needs to be updated.
 * The actual calculation is done in the OrderPickabilityCalculator.
 */
class OrderPickabilityUpdater implements EventSubscriberInterface
{
    private OrderPickabilityCalculator $orderPickabilityCalculator;

    public function __construct(OrderPickabilityCalculator $orderPickabilityCalculator)
    {
        $this->orderPickabilityCalculator = $orderPickabilityCalculator;
    }

    public static function getSubscribedEvents(): array
    {
        /**
         * We need to recalculate the order pickability when:
         *   an order is created along with its referenced entities
         *   the order state changes (order entity is updated)
         *   the order delivery state changes (order configuration is updated)
         *   an order line item is written (created, updated or deleted)
         *   a warehouse is created
         *   WarehouseStock is written
         *
         * When an order is deleted, its referenced entities as well as the order pickability are deleted directly on
         * the database by the ON DELETE CASCADE flag. The same is true for warehouses
         */
        return [
            // Order create/update (this is actually unnecessary, but we need the written event for the following actions)
            // Order line item create/update/delete
            OrderEvents::ORDER_WRITTEN_EVENT => 'orderWritten',
            // Warehouse create
            WarehouseDefinition::ENTITY_WRITTEN_EVENT => 'warehouseWritten',
            // Order delivery state create/updates cause the OrderConfiguration (primary order delivery) to be updated
            // as well and, in turn, will trigger this subscriber here.
            OrderConfigurationUpdatedEvent::EVENT_NAME => 'onOrderConfigurationUpdated',
            // Warehouse stock written
            WarehouseStockUpdatedEvent::EVENT_NAME => 'onWarehouseStockWritten',
        ];
    }

    public function onOrderConfigurationUpdated(OrderConfigurationUpdatedEvent $event): void
    {
        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }
        $this->orderPickabilityCalculator->calculateOrderPickabilitiesForOrders(
            $event->getOrderIds(),
            $event->getContext(),
        );
    }

    public function onWarehouseStockWritten(WarehouseStockUpdatedEvent $event): void
    {
        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }
        $this->orderPickabilityCalculator->calculateOrderPickabilitiesForWarehousesAndProducts(
            $event->getWarehouseIds(),
            $event->getProductIds(),
            $event->getContext(),
        );
    }

    /**
     * This event subscriber is also (unnecessarily) triggered when an order delivery is written and, in turn, the order
     * configuration is updated (see onOrderConfigurationUpdated). But we need to use the orderWritten event to handle
     * changes of order line items
     */
    public function orderWritten(EntityWrittenEvent $event): void
    {
        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $orderIds = [];
        foreach ($event->getWriteResults() as $writeResult) {
            // When order is created or updated, the order id is in the payload
            $payload = $writeResult->getPayload();
            if (array_key_exists('id', $payload)) {
                $orderIds[] = $payload['id'];

                continue;
            }

            // When an order line item is created, updated or deleted, the order id is the primary key of the
            // write-result
            if (is_string($writeResult->getPrimaryKey())) {
                $orderIds[] = $writeResult->getPrimaryKey();
            }

            // If an order is deleted, this event is also triggered and there is no ID in the payload. The ON DELETE
            // CASCADE foreign key will delete the order pickability extension. So we can skip this case here.
        }
        $this->orderPickabilityCalculator->calculateOrderPickabilitiesForOrders($orderIds, $event->getContext());
    }

    public function warehouseWritten(EntityWrittenEvent $event): void
    {
        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $warehouseIds = [];
        foreach ($event->getWriteResults() as $writeResult) {
            // When a warehouse is written, only the INSERT is relevant to upsert order pickabilities. The UPDATE of a
            // warehouse will never affect the order pickability.
            if ($writeResult->getOperation() === EntityWriteResult::OPERATION_INSERT) {
                $warehouseIds[] = $writeResult->getPayload()['id'];
            }

            // When a warehouse is deleted, the ON DELETE CASCADE foreign key will delete the order pickability
            // extension. So we can skip this case here.
        }
        $this->orderPickabilityCalculator->calculateOrderPickabilitiesForWarehouses($warehouseIds, $event->getContext());
    }
}
