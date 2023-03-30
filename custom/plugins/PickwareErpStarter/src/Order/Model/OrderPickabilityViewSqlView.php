<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Order\Model;

use Doctrine\DBAL\Connection;
use Pickware\InstallationLibrary\SqlView\SqlView;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Defaults;

/**
 * @deprecated will be removed with 3.0.0. Use the new order.pickwareErpOrderPickabilities extension instead.
 */
class OrderPickabilityViewSqlView extends SqlView
{
    public function __construct()
    {
        // The pickability status of an order is determined based on the following rules:
        // - only relevant order delivery positions are considered (definition below)
        // - a relevant order delivery position is considered to be "completely pickable" if its remaining quantity
        //   (initial quantity - already shipped quantity) is less or equal than the physical stock of the related
        //   product
        // - a relevant order delivery position is considered to be "partially pickable" if its remaining quantity
        //   is greater than the physical stock of its related product and the physical stock of the product is > 0
        // - a relevant order delivery position is considered to be "not pickable" if its related product is out of
        //   stock (physical stock = 0)
        // - a relevant order delivery position, which is not related to an existing product, is considered to be
        //   "completely pickable"
        // - an order is considered to be "completely pickable" if all relevant order delivery positions are "completely
        //   pickable"
        // - an order is considered to be "partially pickable" if at least one relevant order delivery position is
        //   not "completely pickable" and at least one is "partially pickable" (notice that "completely pickable"
        //   order delivery positions are always "partially pickable" as well)
        // - an order is considered to be "not pickable" if all relevant order delivery positions are "not pickable"
        // - orders with only non-relevant delivery positions are considered to be "completely pickable"
        // - orders with order status not equal to "open" or "in progress" are considered to be "cancelled or
        //   shipped"
        // - orders without at least one order delivery with status "open" or "partially shipped" are considered
        //   to be "cancelled or shipped" (especially orders without any existing order delivery are considered to be
        //   "cancelled or shipped")
        //
        // An order delivery position is said to be relevant iff all of the following conditions hold true:
        // - it is related to an order line item with type "product"
        // - its related order state is "open" or "in progress"
        // - its related delivery state is "open" or "partially shipped"
        // Please notice, that these conditions are the same that are used for determining the reserved stock of an
        // order
        parent::__construct(
            OrderPickabilityViewDefinition::ENTITY_NAME,
            <<<SELECT_STATEMENT
                SELECT
                    `order`.`id` AS id,
                    `order`.`id` AS `order_id`,
                    `order`.`version_id` AS `order_version_id`,
                    CASE
                        -- mark the order as "cancelled or shipped" if either:
                        --  - the order state does not satisy the reserved stock "states condition" OR
                        --  - the orders primary delivery does not satisfy our reserved stock "states condition" OR
                        --  - or all of the (relevant) order line items are already shipped (i.e. stock was moved into the order)
                        WHEN
                            `order_state`.`technical_name` NOT IN (:relevantOrderStates) OR
                            `order_delivery_state`.`technical_name` NOT IN (:relevantDeliveryStates) OR
                            SUM(IFNULL(`order_product_pickability`.`shipped`, 0)) = COUNT(*)
                        THEN :pickabilityStatusCancelledOrShipped
                        -- mark the order as "completely pickable" if all relevant order line items are completely
                        -- pickable. Please notice, that non-relevant order line items are considered to be
                        -- "completely pickable"
                        WHEN
                            SUM(IFNULL(`order_product_pickability`.`completely_pickable`, 1)) = COUNT(*)
                        THEN :pickabilityStatusCompletelyPickable
                        -- mark the order as "partially pickable" if at least one order line item is (partially)
                        -- pickable
                        WHEN
                            SUM(IFNULL(`order_product_pickability`.`pickable`, 0)) > 0
                        THEN :pickabilityStatusPartiallyPickable
                        -- otherwise mark the order as "not pickable"
                        ELSE :pickabilityStatusNotPickable
                    END AS `order_pickability_status`,
                    NOW() as updated_at,
                    NOW() as created_at
                FROM `order`
                LEFT JOIN `state_machine_state` AS `order_state`
                    ON `order`.`state_id` = `order_state`.`id`
                LEFT JOIN (
                    -- Select a single order delivery with the highest shippingCosts.unitPrice as the primary order
                    -- delivery for the order. This selection strategy is adapted from how order deliveries are selected
                    -- in the administration. See /administration/src/module/sw-order/view/sw-order-detail-base/index.js
                    SELECT
                        `order_id`,
                        `order_version_id`,
                        MAX(
                            JSON_UNQUOTE(
                                JSON_EXTRACT(`order_delivery`.`shipping_costs`, "$.unitPrice")
                            )
                        ) AS `unitPrice`
                        FROM `order_delivery`
                        GROUP BY `order_id`, `order_version_id`
                ) `primary_order_delivery_shipping_cost`
                    ON `primary_order_delivery_shipping_cost`.`order_id` = `order`.`id`
                    AND `primary_order_delivery_shipping_cost`.`order_version_id` = `order`.`version_id`
                LEFT JOIN `order_delivery`
                    ON `order_delivery`.`order_id` = `order`.`id`
                    AND `order_delivery`.`order_version_id` = `order`.`version_id`
                    AND JSON_UNQUOTE(JSON_EXTRACT(`order_delivery`.`shipping_costs`, "$.unitPrice")) = `primary_order_delivery_shipping_cost`.`unitPrice`
                LEFT JOIN `state_machine_state` AS `order_delivery_state`
                    ON `order_delivery_state`.`id` = `order_delivery`.`state_id`
                LEFT JOIN (
                    -- This groups all order line items by product and returns for each order the following booleans:
                    --      "completely_pickable" if:
                    --            a deleted product is referenced or
                    --            there is enough stock for the product to be picked to meet the orders product quantity
                    --      "pickable" if:
                    --            a deleted product is referenced or
                    --            there is stock for the product to be picked
                    --      "shipped" if:
                    --            the quantity of the products that needs to be picked is zero (quantity that is required and quantity in the order for the product are equal)
                    SELECT
                        `order`.`id` AS `order_id`,
                        `order`.`version_id` AS `order_version_id`,
                        -- order line items not related to an existing product are always considered as completely pickable
                        `product`.`stock` IS NULL OR `product`.`stock` >= GREATEST(0, `order_product_quantities`.`quantity` - IFNULL(SUM(`stock_in_order_by_product`.`quantity`), 0)) AS `completely_pickable`,
                        `product`.`stock` IS NULL OR `product`.`stock` > 0 AS `pickable`,
                        `order_product_quantities`.`quantity` <= IFNULL(SUM(`stock_in_order_by_product`.`quantity`), 0) AS `shipped`
                    FROM (
                        -- This groups all order line items by quantity that:
                        -- - Are part of the same order
                        -- - Reference the same product
                        SELECT
                            `order_line_item`.`order_id`,
                            `order_line_item`.`order_version_id`,
                            `order_line_item`.`product_id`,
                            `order_line_item`.`product_version_id`,
                            SUM(`order_line_item`.`quantity`) AS `quantity`
                        FROM `order_line_item`
                        WHERE
                              `order_line_item`.`version_id` = :liveVersionId
                              AND `order_line_item`.`type` IN (:relevantOrderLineItemTypes)
                        GROUP BY `order_line_item`.`order_id`,
                                 `order_line_item`.`order_version_id`,
                                 `order_line_item`.`product_id`,
                                 `order_line_item`.`product_version_id`
                    ) `order_product_quantities`
                    INNER JOIN `order`
                        ON `order`.`id` = `order_product_quantities`.`order_id`
                        AND `order`.`version_id` = `order_product_quantities`.`order_version_id`
                    INNER JOIN `state_machine_state` AS `order_state`
                        ON `order`.`state_id` = `order_state`.`id`
                    LEFT JOIN `product`
                        ON `product`.`id` = `order_product_quantities`.`product_id`
                        AND `product`.`version_id` = `order_product_quantities`.`product_version_id`
                    LEFT JOIN `pickware_erp_stock` `stock_in_order_by_product`
                        ON `stock_in_order_by_product`.`order_id` = `order`.`id`
                        AND `stock_in_order_by_product`.`order_version_id` = `order`.`version_id`
                        AND `stock_in_order_by_product`.`product_id` = `product`.`id`
                        AND `stock_in_order_by_product`.`product_version_id` = `product`.`version_id`
                    WHERE `order_state`.`technical_name` IN (:relevantOrderStates)
                    GROUP BY `order`.`id`,
                             `order`.`version_id`,
                             `product`.`id`,
                             `product`.`version_id`
                ) AS `order_product_pickability`
                    ON `order_product_pickability`.`order_id` = `order`.`id`
                    AND `order_product_pickability`.`order_version_id` = `order`.`version_id`
                WHERE `order`.`version_id` = :liveVersionId
                -- We are excluding some orders by state to improve performance. Therefore, these orders will NOT have a
                -- 'pickwareErpOrderPickabilityView' extension. Orders without such a 'pickwareErpOrderPickabilityView'
                -- are considered "cancelled_or_shipped".
                AND `order_state`.`technical_name` IN (:relevantOrderStates)
                AND `order_delivery_state`.`technical_name` IN (:relevantDeliveryStates)
                GROUP BY `order`.`id`, `order`.`version_id`
                SELECT_STATEMENT,
            [
                'pickabilityStatusCompletelyPickable' => OrderPickabilityViewDefinition::PICKABILITY_STATUS_COMPLETELY_PICKABLE,
                'pickabilityStatusPartiallyPickable' => OrderPickabilityViewDefinition::PICKABILITY_STATUS_PARTIALLY_PICKABLE,
                'pickabilityStatusNotPickable' => OrderPickabilityViewDefinition::PICKABILITY_STATUS_NOT_PICKABLE,
                'pickabilityStatusCancelledOrShipped' => OrderPickabilityViewDefinition::PICKABILITY_STATUS_CANCELLED_OR_SHIPPED,
                'relevantOrderStates' => [
                    OrderStates::STATE_OPEN,
                    OrderStates::STATE_IN_PROGRESS,
                ],
                'relevantDeliveryStates' => [
                    OrderDeliveryStates::STATE_OPEN,
                    OrderDeliveryStates::STATE_PARTIALLY_SHIPPED,
                ],
                'relevantOrderLineItemTypes' => [
                    LineItem::PRODUCT_LINE_ITEM_TYPE,
                ],
                'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
            ],
            [
                'relevantOrderStates' => Connection::PARAM_STR_ARRAY,
                'relevantDeliveryStates' => Connection::PARAM_STR_ARRAY,
                'relevantOrderLineItemTypes' => Connection::PARAM_STR_ARRAY,
            ],
        );
    }
}
