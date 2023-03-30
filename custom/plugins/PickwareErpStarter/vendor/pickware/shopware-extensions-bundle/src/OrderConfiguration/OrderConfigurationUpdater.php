<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShopwareExtensionsBundle\OrderConfiguration;

use Doctrine\DBAL\Connection;
use Pickware\DalBundle\RetryableTransaction;
use Pickware\DalBundle\Sql\SqlUuid;
use Pickware\ShopwareExtensionsBundle\OrderTransaction\OrderTransactionCollectionExtension;
use Shopware\Core\Checkout\Order\OrderEvents;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderConfigurationUpdater implements EventSubscriberInterface
{
    // Since this update does not rely on any other subscriber, it has a high priority, so it is executed before other
    // subscribers with default priority.
    private const PRIORITY = 10;

    private Connection $connection;
    private ?EventDispatcherInterface $eventDispatcher;

    /**
     * @deprecated next major version: Second argument "EventDispatcherInterface $eventDispatcher" will be non-optional
     */
    public function __construct(Connection $connection, ?EventDispatcherInterface $eventDispatcher = null)
    {
        $this->connection = $connection;
        $this->eventDispatcher = $eventDispatcher;
    }

    public static function getSubscribedEvents(): array
    {
        $subscribedFunctions = [
            OrderEvents::ORDER_WRITTEN_EVENT => 'orderWritten',
            OrderEvents::ORDER_DELIVERY_WRITTEN_EVENT => 'orderDeliveryWritten',
            OrderEvents::ORDER_DELIVERY_DELETED_EVENT => 'updateOrderConfigurationAfterDeliveryOrTransactionDeletion',
            OrderEvents::ORDER_TRANSACTION_WRITTEN_EVENT => 'orderTransactionWritten',
            OrderEvents::ORDER_TRANSACTION_DELETED_EVENT => 'updateOrderConfigurationAfterDeliveryOrTransactionDeletion',
        ];

        // All subscribes functions should receive the same priority
        $result = [];
        foreach ($subscribedFunctions as $eventName => $functionName) {
            $result[$eventName] = [
                $functionName,
                self::PRIORITY,
            ];
        }

        return $result;
    }

    /**
     * This subscriber method only ensures that the order configuration exists (without updating the actual primary
     * delivery state or primary transaction state) when an order is written. If an order is written _with_ a delivery
     * or transaction, the other events (ORDER_DELIVERY_WRITTEN_EVENT and/or ORDER_TRANSACTION_WRITTEN_EVENT) will be
     * triggered as well, which in turn will update the primary states of the order configuration.
     *
     * So in production this lone "ensure order configuration exists" subscriber is relevant for creating orders
     * without order deliveries or order transactions. This way we can ensure that the order configuration extension
     * always exists.
     */
    public function orderWritten(EntityWrittenEvent $event): void
    {
        $orderIds = [];
        foreach ($event->getWriteResults() as $writeResult) {
            $payload = $writeResult->getPayload();
            if (!array_key_exists('id', $payload)) {
                // If an order is deleted, this event is also triggered and there is no ID in the payload. The ON DELETE
                // CASCADE foreign key will delete the order configuration extension.
                continue;
            }
            $orderIds[] = $payload['id'];
        }
        $this->ensureOrderConfigurationsExist($orderIds);
    }

    /**
     * This event is dispatched when an order delivery is created or updated (not when it is deleted).
     */
    public function orderDeliveryWritten(EntityWrittenEvent $event): void
    {
        $orderIds = $this->getOrderIdsFromOrderAssociationWrittenEvent($event, 'order_delivery');
        if (count($orderIds) === 0) {
            return;
        }

        RetryableTransaction::retryable($this->connection, function () use ($orderIds, $event): void {
            $this->ensureOrderConfigurationsExist($orderIds);
            $this->updatePrimaryOrderDeliveries($orderIds);
            if ($this->eventDispatcher) {
                $this->eventDispatcher->dispatch(
                    new OrderConfigurationUpdatedEvent($orderIds, $event->getContext()),
                    OrderConfigurationUpdatedEvent::EVENT_NAME,
                );
            }
        });
    }

    /**
     * This event is dispatched when an order transaction is created or updated (not when it is deleted).
     */
    public function orderTransactionWritten(EntityWrittenEvent $event): void
    {
        $orderIds = $this->getOrderIdsFromOrderAssociationWrittenEvent($event, 'order_transaction');
        if (count($orderIds) === 0) {
            return;
        }

        RetryableTransaction::retryable($this->connection, function () use ($orderIds, $event): void {
            $this->ensureOrderConfigurationsExist($orderIds);
            $this->updatePrimaryOrderTransactions($orderIds);
            if ($this->eventDispatcher) {
                $this->eventDispatcher->dispatch(
                    new OrderConfigurationUpdatedEvent($orderIds, $event->getContext()),
                    OrderConfigurationUpdatedEvent::EVENT_NAME,
                );
            }
        });
    }

    private function getOrderIdsFromOrderAssociationWrittenEvent(
        EntityWrittenEvent $event,
        string $orderAssociationTableName
    ): array {
        $ids = [];
        foreach ($event->getWriteResults() as $writeResult) {
            $payload = $writeResult->getPayload();
            if (!array_key_exists('id', $payload)) {
                // Whenever an order delivery or order transaction is written (created or updated), the 'id' must be
                // present in the payload. We are not 100% sure when or how this scenario occurs when there is no id set
                // in the payload. But a customer reported it in this SCS Support Ticket 212711.
                continue;
            }
            $ids[] = $payload['id'];
        }
        if (count($ids) === 0) {
            return [];
        }

        return array_unique(array_values($this->connection->fetchFirstColumn(
            'SELECT LOWER(HEX(`order_id`)) FROM `' . $orderAssociationTableName . '` WHERE `id` IN (:ids)',
            ['ids' => array_map('hex2bin', $ids)],
            ['ids' => Connection::PARAM_STR_ARRAY],
        )));
    }

    /**
     * @param String[] $orderIds
     */
    public function updateOrderConfigurations(array $orderIds, Context $context): void
    {
        if (count($orderIds) === 0) {
            return;
        }

        RetryableTransaction::retryable($this->connection, function () use ($orderIds, $context): void {
            $this->ensureOrderConfigurationsExist($orderIds);
            $this->updatePrimaryOrderDeliveries($orderIds);
            $this->updatePrimaryOrderTransactions($orderIds);
            if ($this->eventDispatcher) {
                $this->eventDispatcher->dispatch(
                    new OrderConfigurationUpdatedEvent($orderIds, $context),
                    OrderConfigurationUpdatedEvent::EVENT_NAME,
                );
            }
        });
    }

    /**
     * @param String[] $orderIds
     */
    private function ensureOrderConfigurationsExist(array $orderIds): void
    {
        $this->connection->executeStatement(
            'INSERT INTO `pickware_shopware_extensions_order_configuration`
            (
                `id`,
                `version_id`,
                `order_id`,
                `order_version_id`,
                `created_at`
            ) SELECT
                ' . SqlUuid::UUID_V4_GENERATION . ',
                `version_id`,
                `id`,
                `version_id`,
                NOW(3)
            FROM `order`
            WHERE `order`.`id` IN (:orderIds)
            ON DUPLICATE KEY UPDATE `pickware_shopware_extensions_order_configuration`.`id` = `pickware_shopware_extensions_order_configuration`.`id`',
            ['orderIds' => array_map('hex2bin', $orderIds)],
            ['orderIds' => Connection::PARAM_STR_ARRAY],
        );
    }

    /**
     * The order transactions and order deliveries are referenced in the
     * `pickware_shopware_extensions_order_configuration` table. If such a reference is deleted, the respective
     * reference field is nulled due to the ON DELETE SET NULL foreign key. In the ENTITY_DELETED event we have no way
     * of knowing which order we have to update, because all references are gone at that point in time. Therefore, we
     * check every order that has a null reference on a primary order transaction or order delivery. These should be
     * only a few, ideally only the order of the recently deleted reference, because there should be no orders without
     * order deliveries or order transactions in production.
     *
     * It is also possible that a non-primary order delivery or non-primary order transaction was deleted when this
     * subscriber was triggered and this method will return early without any update.
     */
    public function updateOrderConfigurationAfterDeliveryOrTransactionDeletion(EntityWrittenEvent $event): void
    {
        RetryableTransaction::retryable($this->connection, function () use ($event): void {
            $orderIds = $this->connection->fetchFirstColumn(
                'SELECT LOWER(HEX(`order_id`)) FROM `pickware_shopware_extensions_order_configuration` orderConfiguration
                WHERE (
                    orderConfiguration.`primary_order_delivery_id` IS NULL
                    OR orderConfiguration.`primary_order_transaction_id` IS NULL
                )',
            );
            if (!$orderIds) {
                return;
            }
            $this->updatePrimaryOrderDeliveries($orderIds);
            $this->updatePrimaryOrderTransactions($orderIds);
            if ($this->eventDispatcher) {
                $this->eventDispatcher->dispatch(
                    new OrderConfigurationUpdatedEvent($orderIds, $event->getContext()),
                    OrderConfigurationUpdatedEvent::EVENT_NAME,
                );
            }
        });
    }

    /**
     * @param String[] $orderIds
     */
    private function updatePrimaryOrderDeliveries(array $orderIds): void
    {
        $this->connection->executeStatement(
            'UPDATE `pickware_shopware_extensions_order_configuration` orderConfiguration
            LEFT JOIN `order`
                ON `order`.`id` = orderConfiguration.`order_id`
                AND `order`.`version_id` = orderConfiguration.`order_version_id`

            -- Select a single order delivery with the highest shippingCosts.unitPrice as the primary order
            -- delivery for the order. This selection strategy is adapted from how order deliveries are selected
            -- in the administration. See /administration/src/module/sw-order/view/sw-order-detail-base/index.js
            LEFT JOIN (
                SELECT
                    `order_id`,
                    `order_version_id`,
                    MAX(
                        CAST(JSON_UNQUOTE(
                            JSON_EXTRACT(`order_delivery`.`shipping_costs`, "$.unitPrice")
                        ) AS DECIMAL)
                    ) AS `unitPrice`
                FROM `order_delivery`
                GROUP BY `order_id`, `order_version_id`
            ) `primary_order_delivery_shipping_cost`
                ON `primary_order_delivery_shipping_cost`.`order_id` = `order`.`id`
                AND `primary_order_delivery_shipping_cost`.`order_version_id` = `order`.`version_id`
            LEFT JOIN `order_delivery`
                ON `order_delivery`.`order_id` = `order`.`id`
                AND `order_delivery`.`order_version_id` = `order`.`version_id`
                AND CAST(JSON_UNQUOTE(JSON_EXTRACT(`order_delivery`.`shipping_costs`, "$.unitPrice")) AS DECIMAL) = `primary_order_delivery_shipping_cost`.`unitPrice`

            SET orderConfiguration.`primary_order_delivery_id` = `order_delivery`.`id`,
                orderConfiguration.`primary_order_delivery_version_id` = `order_delivery`.`version_id`

            WHERE orderConfiguration.`order_id` IN (:orderIds)',
            ['orderIds' => array_map('hex2bin', $orderIds)],
            ['orderIds' => Connection::PARAM_STR_ARRAY],
        );
    }

    /**
     * @param String[] $orderIds
     */
    private function updatePrimaryOrderTransactions(array $orderIds): void
    {
        $this->connection->executeStatement(
            'UPDATE `pickware_shopware_extensions_order_configuration` orderConfiguration
            LEFT JOIN `order`
                ON `order`.`id` = orderConfiguration.`order_id`
                AND `order`.`version_id` = orderConfiguration.`order_version_id`

            -- Select oldest order transaction that is not "cancelled" or "failed" else return the last order transaction.
            -- https://github.com/shopware/platform/blob/v6.4.8.1/src/Administration/Resources/app/administration/src/module/sw-order/view/sw-order-detail-base/index.js#L91-L98
            -- https://github.com/shopware/platform/blob/v6.4.8.1/src/Administration/Resources/app/administration/src/module/sw-order/view/sw-order-detail-base/index.js#L207
            LEFT JOIN `order_transaction`
                ON `order_transaction`.`id` = (
                    SELECT innerOrderTransaction.`id`
                    FROM `order_transaction` innerOrderTransaction
                    LEFT JOIN `state_machine_state` AS innerOrderTransactionState
                        ON innerOrderTransactionState.`id` = innerOrderTransaction.`state_id`
                    WHERE innerOrderTransaction.`order_id` = `order`.`id`
                    AND innerOrderTransaction.`version_id` = `order`.`version_id`
                    ORDER BY
                        IF(innerOrderTransactionState.`technical_name` IN (:ignoredStates), 0, 1) DESC,
                        innerOrderTransaction.created_at ASC
                    LIMIT 1
                ) AND `order_transaction`.`version_id` = `order`.`version_id`

            SET orderConfiguration.`primary_order_transaction_id` = `order_transaction`.`id`,
                orderConfiguration.`primary_order_transaction_version_id` = `order_transaction`.`version_id`

            WHERE orderConfiguration.`order_id` IN (:orderIds)',
            [
                'orderIds' => array_map('hex2bin', $orderIds),
                'ignoredStates' => OrderTransactionCollectionExtension::PRIMARY_TRANSACTION_IGNORED_STATES,
            ],
            [
                'orderIds' => Connection::PARAM_STR_ARRAY,
                'ignoredStates' => Connection::PARAM_STR_ARRAY,
            ],
        );
    }
}
