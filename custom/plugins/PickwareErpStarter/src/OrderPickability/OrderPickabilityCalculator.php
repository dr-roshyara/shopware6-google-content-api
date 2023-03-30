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

use Doctrine\DBAL\Connection;
use Pickware\DalBundle\DatabaseBulkInsertService;
use Pickware\DalBundle\EntityManager;
use Pickware\DalBundle\RetryableTransaction;
use Pickware\DalBundle\Sql\SqlUuid;
use Pickware\PickwareErpStarter\OrderPickability\Model\OrderPickabilityDefinition;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

class OrderPickabilityCalculator
{
    // For orders with any state in the deny-list, no pickability will be calculated and no pickability entity will be
    // created. This way we avoid updating any old (completed/canceled/..) orders with each stock changes. Which would
    // be a potentially large number of irrelevant orders.
    public const ORDER_STATE_IGNORE_LIST = [
        OrderStates::STATE_CANCELLED,
        OrderStates::STATE_COMPLETED,
    ];
    public const ORDER_DELIVERY_STATE_IGNORE_LIST = [
        OrderDeliveryStates::STATE_CANCELLED,
        OrderDeliveryStates::STATE_SHIPPED,
    ];

    private Connection $connection;
    private ?EntityManager $entityManager;
    private ?DatabaseBulkInsertService $bulkInsertWithUpdate;

    /**
     * @deprecated next major version: `EntityManager $entityManager` argument will be non-optional
     */
    public function __construct(
        Connection $connection,
        ?EntityManager $entityManager = null,
        ?DatabaseBulkInsertService $bulkInsertWithUpdate = null
    ) {
        $this->connection = $connection;
        $this->entityManager = $entityManager;
        $this->bulkInsertWithUpdate = $bulkInsertWithUpdate;
    }

    private function skipOrderPickabilityCalculation(): bool
    {
        return isset($_ENV['PICKWARE_ERP_STARTER__SKIP_ORDER_PICKABILITY_CALCULATION'])
            && $_ENV['PICKWARE_ERP_STARTER__SKIP_ORDER_PICKABILITY_CALCULATION'];
    }

    /**
     * @param string[] $orderIds
     */
    public function calculateOrderPickabilitiesForOrders(array $orderIds, Context $context): void
    {
        if (!$this->entityManager || !$this->bulkInsertWithUpdate) {
            // The properties were made optional for backwards compatibility in the constructor. Should not happen
            // during an actual request. Return early.
            return;
        }

        if (count($orderIds) === 0) {
            return;
        }

        RetryableTransaction::retryable($this->connection, function () use ($orderIds, $context): void {
            $warehouseIds = $this->connection->fetchFirstColumn(
                'SELECT LOWER(HEX(`id`)) FROM `pickware_erp_warehouse`;',
            );
            $this->entityManager->lockPessimistically(
                OrderDefinition::class,
                ['id' => $orderIds],
                $context,
            );

            // For performance reasons we pre-fetch the ignored state ids instead of adding another join when filtering
            $ignoredStateIds = $this->getIgnoreStateIds();

            $statusFilteredOrderIds = $this->connection->fetchFirstColumn(
                'SELECT DISTINCT
                    LOWER(HEX(`order`.`id`)) as `orderId`
                FROM `order`
                INNER JOIN `pickware_shopware_extensions_order_configuration` AS `orderConfiguration`
                    ON `order`.`id` = `orderConfiguration`.`order_id`
                    AND `order`.`version_id` = `orderConfiguration`.`order_version_id`
                INNER JOIN `order_delivery` as `primary_order_delivery`
                    ON `primary_order_delivery`.`id` = `orderConfiguration`.`primary_order_delivery_id`
                    AND `primary_order_delivery`.`version_id` = `orderConfiguration`.`primary_order_delivery_version_id`
                WHERE
                    `order`.`id` IN (:orderIds)
                    AND `order`.`version_id` = :liveVersionId
                    AND `order`.`state_id` NOT IN (:orderStateIgnoreList)
                    AND `primary_order_delivery`.`state_id` NOT IN (:orderDeliveryStateIgnoreList);',
                [
                    'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
                    'orderStateIgnoreList' => array_map('hex2bin', $ignoredStateIds['orderStateIgnoreList']),
                    'orderDeliveryStateIgnoreList' => array_map('hex2bin', $ignoredStateIds['orderDeliveryStateIgnoreList']),
                    'orderIds' => array_map('hex2bin', $orderIds),
                ],
                [
                    'orderStateIgnoreList' => Connection::PARAM_STR_ARRAY,
                    'orderDeliveryStateIgnoreList' => Connection::PARAM_STR_ARRAY,
                    'productIds' => Connection::PARAM_STR_ARRAY,
                    'orderIds' => Connection::PARAM_STR_ARRAY,
                ],
            );

            $this->recalculateOrderPickabilitiesForOrdersAndWarehouses(
                array_values($statusFilteredOrderIds),
                array_values($warehouseIds),
            );
            $this->deleteOrderPickabilitiesOfIgnoredOrders($orderIds);
        });
    }

    /**
     * @param string[] $warehouseIds
     */
    public function calculateOrderPickabilitiesForWarehouses(array $warehouseIds, Context $context): void
    {
        if (!$this->entityManager || !$this->bulkInsertWithUpdate) {
            // The properties were made optional for backwards compatibility in the constructor. Should not happen
            // during an actual request. Return early.
            return;
        }

        if (count($warehouseIds) === 0) {
            return;
        }

        RetryableTransaction::retryable($this->connection, function () use ($warehouseIds, $context): void {
            // For performance reasons we pre-fetch the ignored state ids instead of adding another join when filtering
            $ignoredStateIds = $this->getIgnoreStateIds();

            $orderIds = $this->connection->fetchFirstColumn(
                'SELECT DISTINCT
                    LOWER(HEX(`order`.`id`))
                FROM `order`
                INNER JOIN `pickware_shopware_extensions_order_configuration` AS `orderConfiguration`
                    ON `order`.`id` = `orderConfiguration`.`order_id`
                    AND `order`.`version_id` = `orderConfiguration`.`order_version_id`
                INNER JOIN `order_delivery` as `primary_order_delivery`
                    ON `primary_order_delivery`.`id` = `orderConfiguration`.`primary_order_delivery_id`
                    AND `primary_order_delivery`.`version_id` = `orderConfiguration`.`primary_order_delivery_version_id`
                WHERE
                    `order`.`version_id` = :liveVersionId
                    AND `order`.`state_id` NOT IN (:orderStateIgnoreList)
                    AND `primary_order_delivery`.`state_id` NOT IN (:orderDeliveryStateIgnoreList);',
                [
                    'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
                    'orderStateIgnoreList' => array_map('hex2bin', $ignoredStateIds['orderStateIgnoreList']),
                    'orderDeliveryStateIgnoreList' => array_map('hex2bin', $ignoredStateIds['orderDeliveryStateIgnoreList']),
                ],
                [
                    'orderStateIgnoreList' => Connection::PARAM_STR_ARRAY,
                    'orderDeliveryStateIgnoreList' => Connection::PARAM_STR_ARRAY,
                ],
            );

            $this->entityManager->lockPessimistically(
                OrderDefinition::class,
                ['id' => $orderIds],
                $context,
            );

            $this->recalculateOrderPickabilitiesForOrdersAndWarehouses(array_values($orderIds), $warehouseIds);
        });
    }

    /**
     * @param string[] $warehouseIds
     * @param string[] $productIds
     */
    public function calculateOrderPickabilitiesForWarehousesAndProducts(array $warehouseIds, array $productIds, Context $context): void
    {
        if (!$this->entityManager || !$this->bulkInsertWithUpdate) {
            // The properties were made optional for backwards compatibility in the constructor. Should not happen
            // during an actual request. Return early.
            return;
        }

        if (count($warehouseIds) === 0) {
            return;
        }

        RetryableTransaction::retryable($this->connection, function () use ($warehouseIds, $productIds, $context): void {
            $this->entityManager->lockPessimistically(
                ProductDefinition::class,
                ['id' => $productIds],
                $context,
            );

            // For performance reasons we pre-fetch the ignored state ids instead of adding another join when filtering
            $ignoredStateIds = $this->getIgnoreStateIds();

            $orderIds = $this->connection->fetchFirstColumn(
                'SELECT DISTINCT
                    HEX(`order`.`id`)
                FROM `order`
                LEFT JOIN `order_line_item`
                    ON `order`.`id` = `order_line_item`.`order_id`
                    AND `order`.`version_id` = `order_line_item`.`order_version_id`
                LEFT JOIN `pickware_shopware_extensions_order_configuration` AS `orderConfiguration`
                    ON `order`.`id` = `orderConfiguration`.`order_id`
                    AND `order`.`version_id` = `orderConfiguration`.`order_version_id`
                LEFT JOIN `order_delivery` as `primary_order_delivery`
                    ON `primary_order_delivery`.`id` = `orderConfiguration`.`primary_order_delivery_id`
                    AND `primary_order_delivery`.`version_id` = `orderConfiguration`.`primary_order_delivery_version_id`
                WHERE
                    `order`.`version_id` = :liveVersionId
                    AND `order_line_item`.`product_id` IN (:productIds)
                    AND `order`.`state_id` NOT IN (:orderStateIgnoreList)
                    AND `primary_order_delivery`.`state_id` NOT IN (:orderDeliveryStateIgnoreList);',
                [
                    'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
                    'orderStateIgnoreList' => array_map('hex2bin', $ignoredStateIds['orderStateIgnoreList']),
                    'orderDeliveryStateIgnoreList' => array_map('hex2bin', $ignoredStateIds['orderDeliveryStateIgnoreList']),
                    'productIds' => array_map('hex2bin', $productIds),
                ],
                [
                    'orderStateIgnoreList' => Connection::PARAM_STR_ARRAY,
                    'orderDeliveryStateIgnoreList' => Connection::PARAM_STR_ARRAY,
                    'productIds' => Connection::PARAM_STR_ARRAY,
                ],
            );

            $this->entityManager->lockPessimistically(
                OrderDefinition::class,
                ['id' => $orderIds],
                $context,
            );

            $this->recalculateOrderPickabilitiesForOrdersAndWarehouses(array_values($orderIds), $warehouseIds);
        });
    }

    /**
     * @param String[] $orderIds
     * @param String[] $warehouseIds
     */
    private function recalculateOrderPickabilitiesForOrdersAndWarehouses(array $orderIds, array $warehouseIds): void
    {
        if ($this->skipOrderPickabilityCalculation()) {
            $sqlStatement = 'INSERT INTO `pickware_erp_order_pickability`
            (
                `id`,
                `warehouse_id`,
                `order_id`,
                `order_version_id`,
                `order_pickability_status`,
                `created_at`
            ) SELECT
                ' . SqlUuid::UUID_V4_GENERATION . ',
                `pickware_erp_warehouse`.`id`,
                `order`.`id`,
                `order`.`version_id`,
                :pickabilityOverrideStatus,
                NOW(3)
            FROM `order`
            JOIN `pickware_erp_warehouse`
            WHERE
                `pickware_erp_warehouse`.`id` IN (:warehouseIds)
                AND `order`.`id` IN (:orderIds)
                AND `order`.`version_id` = :liveVersionId
            ON DUPLICATE KEY UPDATE
                `pickware_erp_order_pickability`.`id` = `pickware_erp_order_pickability`.`id`,
                `pickware_erp_order_pickability`.`order_pickability_status` = :pickabilityOverrideStatus';

            $this->connection->executeStatement(
                $sqlStatement,
                [
                    'orderIds' => array_map('hex2bin', $orderIds),
                    'warehouseIds' => array_map('hex2bin', $warehouseIds),
                    'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
                    'pickabilityOverrideStatus' => OrderPickabilityDefinition::PICKABILITY_STATUS_COMPLETELY_PICKABLE,
                ],
                [
                    'orderIds' => Connection::PARAM_STR_ARRAY,
                    'warehouseIds' => Connection::PARAM_STR_ARRAY,
                ],
            );

            return;
        }

        // Note that this query does not filter by order status/order delivery status anymore. These filters are
        // already applied when fetching the given $orderIds beforehand.
        $this->connection->executeStatement(
            'SELECT `id` FROM `pickware_erp_order_pickability` WHERE `order_id` IN (:orderIds) FOR UPDATE',
            ['orderIds' => array_map('hex2bin', $orderIds)],
            ['orderIds' => Connection::PARAM_STR_ARRAY],
        );

        // By splitting the SELECT and the UPDATE query we work-around a performance problem. If the
        // queries were executed in one UPDATE ... JOIN query the query time would rise unexpectedly.
        $orderPickabilityStatuses = $this->connection->fetchAllAssociative(
            'SELECT
                LOWER(HEX(`order`.`id`)) AS `order_id`,
                LOWER(HEX(`order_line_item_pickability`.`warehouse_id`)) AS `warehouse_id`,
                CASE
                    -- If all order line items are completely pickable, the order is "completely pickable"
                    WHEN SUM(
                        LEAST(
                            -- Regardless of their physical pickability, shipped order line items are ignored by
                            -- making them `completely pickable`
                            IFNULL(`order_line_item_pickability`.`completely_pickable`, 1) + IFNULL(`order_line_item_pickability`.`is_completely_shipped`, 1),
                            1
                        )
                    ) = COUNT(*) THEN :pickabilityStatusCompletelyPickable

                    -- If at least one order line item is partially pickable (which is also true for order line
                    -- items that are completely pickable), the order is "partially pickable"
                    WHEN SUM(
                        GREATEST(
                            -- shipped order line items are ignored by making them not `partially_pickable`
                            IFNULL(`order_line_item_pickability`.`partially_pickable`, 0) - IFNULL(`order_line_item_pickability`.`is_completely_shipped`, 0),
                            0
                        )
                    ) > 0 THEN :pickabilityStatusPartiallyPickable

                    -- Otherwise (not a single order line item is not even partially pickable) the order is
                    -- "not pickable"
                    ELSE :pickabilityStatusNotPickable
                END AS `order_pickability_status`,
                LOWER(HEX(`order`.`version_id`)) as `order_version_id`,
                NOW(3) AS `created_at`
            FROM `order`
            LEFT JOIN (
                -- This sub select calculates the pickability of each order line item per order.
                SELECT
                    `order_line_item`.`order_id` AS `order_id`,
                    `order_line_item`.`order_version_id` AS `order_version_id`,
                    `warehouse_stock`.`warehouse_id` AS `warehouse_id`,

                    -- Note that the same product can be part of an order multiple time (multiple order line items
                    -- can reference the same product). Whereas the same product can only have a single stock value
                    -- in each order.
                    IFNULL(SUM(`order_line_item`.`quantity`), 0) - IFNULL(MIN(`stock_in_order_by_product`.`quantity`), 0) <= 0 AS `is_completely_shipped`,

                    -- "completely pickable" if there is enough stock in the warehouse for the product in the order to
                    -- fulfill the quantity of the order (minus what is already stocked into the order).
                    --
                    -- This is also true for:
                    --      - there is no stock to pick any more (product in order is completely shipped)
                    --      - order line item references a deleted product (warehouse stock is NULL)
                    `warehouse_stock`.`quantity` IS NULL OR `warehouse_stock`.`quantity` >= GREATEST(
                        0,
                        IFNULL(SUM(`order_line_item`.`quantity`), 0) - IFNULL(SUM(`stock_in_order_by_product`.`quantity`), 0)
                    ) AS `completely_pickable`,

                    -- "partially pickable" if there is at least some stock in the warehouse to pick from
                    `warehouse_stock`.`quantity` IS NULL OR `warehouse_stock`.`quantity` > 0 AS `partially_pickable`
                FROM `order_line_item`
                LEFT JOIN `pickware_erp_warehouse_stock` `warehouse_stock`
                    ON `order_line_item`.`product_id` = `warehouse_stock`.`product_id`
                    -- Join via liveVersionId is (which we know is true because of the WHERE statement below), because
                    -- there is no index on the `order_line_item`.`product_version_id`, which makes this query slow
                    AND :liveVersionId = `warehouse_stock`.`product_version_id`
                LEFT JOIN `pickware_erp_stock` `stock_in_order_by_product`
                    ON `stock_in_order_by_product`.`order_id` = `order_line_item`.`order_id`
                    AND `stock_in_order_by_product`.`order_version_id` = `order_line_item`.`order_version_id`
                    AND `stock_in_order_by_product`.`product_id` = `order_line_item`.`product_id`
                    AND `stock_in_order_by_product`.`product_version_id` = :liveVersionId
                WHERE
                    `order_line_item`.`order_id` IN (:orderIds)
                    AND `order_line_item`.`order_version_id` = :liveVersionId
                    AND `order_line_item`.`version_id` = :liveVersionId
                    AND `order_line_item`.`type` IN (:orderLineItemTypeAllowList)
                    AND `warehouse_stock`.`warehouse_id` IN (:warehouseIds)
                GROUP BY
                    `order_line_item`.`order_id`,
                    `order_line_item`.`order_version_id`,
                    `order_line_item`.`product_id`,
                    `order_line_item`.`product_version_id`,
                    `warehouse_stock`.`warehouse_id`
            ) AS `order_line_item_pickability`
                ON `order_line_item_pickability`.`order_id` = `order`.`id`
                AND `order_line_item_pickability`.`order_version_id` = `order`.`version_id`
            WHERE
                `order`.`id` IN (:orderIds)
                AND `order`.`version_id` = :liveVersionId
            GROUP BY
                `order`.`id`,
                `order`.`version_id`,
                `order_line_item_pickability`.`warehouse_id`',
            [
                'pickabilityStatusCompletelyPickable' => OrderPickabilityDefinition::PICKABILITY_STATUS_COMPLETELY_PICKABLE,
                'pickabilityStatusPartiallyPickable' => OrderPickabilityDefinition::PICKABILITY_STATUS_PARTIALLY_PICKABLE,
                'pickabilityStatusNotPickable' => OrderPickabilityDefinition::PICKABILITY_STATUS_NOT_PICKABLE,
                'orderLineItemTypeAllowList' => [LineItem::PRODUCT_LINE_ITEM_TYPE],
                'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
                'orderIds' => array_map('hex2bin', $orderIds),
                'warehouseIds' => array_map('hex2bin', $warehouseIds),
            ],
            [
                'orderLineItemTypeAllowList' => Connection::PARAM_STR_ARRAY,
                'orderIds' => Connection::PARAM_STR_ARRAY,
                'warehouseIds' => Connection::PARAM_STR_ARRAY,
            ],
        );

        // Add warehouseId for every warehouse to orderPickabilities without warehouse which exist when no pickability
        // could be calculated.
        $updatedStatuses = [];
        foreach ($orderPickabilityStatuses as $orderPickabilityStatus) {
            if (!$orderPickabilityStatus['warehouse_id']) {
                foreach ($warehouseIds as $warehouseId) {
                    $converted = [
                        'id' => Uuid::randomBytes(),
                        'order_id' => hex2bin($orderPickabilityStatus['order_id']),
                        'warehouse_id' => hex2bin($warehouseId),
                        'order_version_id' => hex2bin($orderPickabilityStatus['order_version_id']),
                        'order_pickability_status' => $orderPickabilityStatus['order_pickability_status'] ? : OrderPickabilityDefinition::PICKABILITY_STATUS_COMPLETELY_PICKABLE,
                        'created_at' => $orderPickabilityStatus['created_at'],
                    ];
                    $updatedStatuses[] = $converted;
                }
            } else {
                $converted = [
                    'id' => Uuid::randomBytes(),
                    'order_id' => hex2bin($orderPickabilityStatus['order_id']),
                    'warehouse_id' => hex2bin($orderPickabilityStatus['warehouse_id']),
                    'order_version_id' => hex2bin($orderPickabilityStatus['order_version_id']),
                    'order_pickability_status' => $orderPickabilityStatus['order_pickability_status'] ? : OrderPickabilityDefinition::PICKABILITY_STATUS_COMPLETELY_PICKABLE,
                    'created_at' => $orderPickabilityStatus['created_at'],
                ];
                $updatedStatuses[] = $converted;
            }
        }

        // While testing optimizations on a larger shop system we saw that 5000 is a batch size which has great
        // performance while also having a size large enough that smaller shops can update everything in one go to
        // not waste performance on those systems.
        // Further references: https://github.com/pickware/shopware-plugins/issues/3324 and linked tickets
        $batches = array_chunk($updatedStatuses, 5000);
        foreach ($batches as $batch) {
            $this->bulkInsertWithUpdate->insertOnDuplicateKeyUpdate(
                'pickware_erp_order_pickability',
                $batch,
                [],
                ['order_pickability_status'],
            );
        }
    }

    /**
     * Pre-fetching of the state ids improves the performance for queries which use a state filter because of
     * in-performant join orders with the state machine and state machine state.
     */
    private function getIgnoreStateIds(): array
    {
        $orderStateIds = $this->connection->fetchFirstColumn(
            'SELECT
                LOWER(HEX(`state_machine_state`.`id`)) as `stateId`
            FROM state_machine_state
            INNER JOIN `state_machine`
                ON `state_machine`.`id` = `state_machine_state`.`state_machine_id`
            WHERE `state_machine_state`.`technical_name` IN (:states)
                AND `state_machine`.`technical_name` = :stateMaschineNames',
            [
                'states' => self::ORDER_STATE_IGNORE_LIST,
                'stateMaschineNames' => OrderStates::STATE_MACHINE,
            ],
            [
                'states' => Connection::PARAM_STR_ARRAY,
            ],
        );

        $orderDeliveryStateIds = $this->connection->fetchFirstColumn(
            'SELECT
                LOWER(HEX(`state_machine_state`.`id`)) as `stateId`
            FROM state_machine_state
            INNER JOIN `state_machine`
                ON `state_machine`.`id` = `state_machine_state`.`state_machine_id`
            WHERE `state_machine_state`.`technical_name` IN (:states)
                AND `state_machine`.`technical_name` = :stateMaschineNames',
            [
                'states' => self::ORDER_DELIVERY_STATE_IGNORE_LIST,
                'stateMaschineNames' => OrderDeliveryStates::STATE_MACHINE,
            ],
            [
                'states' => Connection::PARAM_STR_ARRAY,
            ],
        );

        return [
            'orderStateIgnoreList' => $orderStateIds,
            'orderDeliveryStateIgnoreList' => $orderDeliveryStateIds,
        ];
    }

    /**
     * @param string[] $orderIds
     */
    private function deleteOrderPickabilitiesOfIgnoredOrders(array $orderIds): void
    {
        $this->connection->executeStatement(
            'DELETE FROM `pickware_erp_order_pickability`
            WHERE `order_id` IN (
                SELECT
                    `order`.`id`
                FROM `order`
                LEFT JOIN `state_machine_state` AS `order_state`
                    ON `order`.`state_id` = `order_state`.`id`
                LEFT JOIN `pickware_shopware_extensions_order_configuration` AS `orderConfiguration`
                    ON `order`.`id` = `orderConfiguration`.`order_id`
                    AND `order`.`version_id` = `orderConfiguration`.`order_version_id`
                LEFT JOIN `order_delivery` as `primary_order_delivery`
                    ON `primary_order_delivery`.`id` = `orderConfiguration`.`primary_order_delivery_id`
                    AND `primary_order_delivery`.`version_id` = `orderConfiguration`.`primary_order_delivery_version_id`
                LEFT JOIN `state_machine_state` AS `orderDeliveryState`
                    ON `primary_order_delivery`.`state_id` = `orderDeliveryState`.`id`
                WHERE
                    (`order_state`.`technical_name` IN (:orderStateIgnoreList)
                    OR `orderDeliveryState`.`technical_name` IN (:orderDeliveryStateIgnoreList))
                    AND `order`.`id` IN (:orderIds)
            )',
            [
                'orderStateIgnoreList' => self::ORDER_STATE_IGNORE_LIST,
                'orderDeliveryStateIgnoreList' => self::ORDER_DELIVERY_STATE_IGNORE_LIST,
                'orderIds' => array_map('hex2bin', $orderIds),
            ],
            [
                'orderStateIgnoreList' => Connection::PARAM_STR_ARRAY,
                'orderDeliveryStateIgnoreList' => Connection::PARAM_STR_ARRAY,
                'orderIds' => Connection::PARAM_STR_ARRAY,
            ],
        );
    }
}
