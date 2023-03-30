<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\DemandPlanning\AnalyticsProfile;

use Doctrine\DBAL\Connection;
use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\Analytics\AnalyticsAggregator;
use Pickware\PickwareErpStarter\Analytics\DependencyInjection\AnalyticsAggregatorConfigFactoryRegistry;
use Pickware\PickwareErpStarter\Analytics\Model\AnalyticsAggregationSessionDefinition;
use Pickware\PickwareErpStarter\Analytics\Model\AnalyticsAggregationSessionEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;

class DemandPlanningAnalyticsAggregator implements AnalyticsAggregator
{
    public const ITEM_TABLE_NAME = 'pickware_erp_analytics_aggregation_item_demand_planning';

    private EntityManager $entityManager;
    private AnalyticsAggregatorConfigFactoryRegistry $aggregatorConfigFactoryRegistry;
    private Connection $connection;

    public function __construct(
        EntityManager $entityManager,
        AnalyticsAggregatorConfigFactoryRegistry $aggregatorConfigFactoryRegistry,
        Connection $connection
    ) {
        $this->entityManager = $entityManager;
        $this->aggregatorConfigFactoryRegistry = $aggregatorConfigFactoryRegistry;
        $this->connection = $connection;
    }

    public function aggregate(string $aggregationSessionId, Context $context): void
    {
        /** @var AnalyticsAggregationSessionEntity $aggregationSession */
        $aggregationSession = $this->entityManager->getByPrimaryKey(
            AnalyticsAggregationSessionDefinition::class,
            $aggregationSessionId,
            $context,
        );

        /** @var DemandPlanningAnalyticsAggregatorConfig $config */
        $config = $this->aggregatorConfigFactoryRegistry
            ->getAnalyticsAggregatorConfigFactoryByAggregationTechnicalName($this->getAggregationTechnicalName())
            ->createAggregatorConfigFromArray($aggregationSession->getConfig());

        $additionalFilter = '';
        if ($config->showOnlyStockAtOrBelowReorderPoint) {
            // When a product has no reorder point, this filter should remove it from the result. And since (n <= NULL)
            // is evaluated NULL in SQL, we can use IFNULL(.., FALSE) here.
            $additionalFilter .= 'AND IFNULL(`product`.`stock` <= `pickwareProduct`.`reorder_point`, FALSE)';
        }

        // Create demand planning list items with new configuration
        $salesCalculation = 'IFNULL(SUM(`orderLineItemsInSalesInterval`.`quantity`), 0)';
        $salesPredictionCalculation = '(CEIL(' . $salesCalculation . ' * :referenceSalesToPredictionFactor))';
        $reservedStockCalculation = '(`product`.stock - `product`.available_stock)';
        $incomingStock = 'IFNULL(`pickwareProduct`.`incoming_stock`, 0)';
        $purchaseSuggestionCalculation = 'GREATEST(
            0,
            (IFNULL(`pickwareProduct`.`reorder_point`, 0) + (' . $reservedStockCalculation . ' * :considerOpenOrdersInPurchaseSuggestion) - `product`.`stock` - ' . $incomingStock . '),
            (' . $salesPredictionCalculation . ' + (' . $reservedStockCalculation . ' * :considerOpenOrdersInPurchaseSuggestion) - `product`.`stock` - ' . $incomingStock . ')
        )';

        $this->connection->executeStatement(
            'INSERT INTO `pickware_erp_analytics_aggregation_item_demand_planning` (
                `aggregation_session_id`,
                `product_id`,
                `product_version_id`,
                `sales`,
                `sales_prediction`,
                `reserved_stock`,
                `stock`,
                `reorder_point`,
                `incoming_stock`,
                `purchase_suggestion`
            ) SELECT
                :sessionId,
                `product`.`id`,
                `product`.`version_id`,
                ' . $salesCalculation . ',
                ' . $salesPredictionCalculation . ',
                ' . $reservedStockCalculation . ',
                `product`.`stock`,
                `pickwareProduct`.`reorder_point`,
                ' . $incomingStock . ',
                ' . $purchaseSuggestionCalculation . '
            FROM `product`
            LEFT JOIN `pickware_erp_pickware_product` AS `pickwareProduct`
                ON `pickwareProduct`.`product_id` = `product`.`id`
                AND `pickwareProduct`.`product_version_id` = `product`.`version_id`
            LEFT JOIN (
                SELECT
                    `order_line_item`.`quantity`,
                    `order_line_item`.`product_id`,
                    `order_line_item`.`product_version_id`
                FROM `order_line_item`
                INNER JOIN `order`
                    ON `order`.`id` = `order_line_item`.`order_id`
                    AND `order`.`version_id` = `order_line_item`.`order_version_id`
                    AND `order`.`order_date` >= :fromDate
                    AND `order`.`order_date` <= :toDate
                    AND `order`.`version_id` = :liveVersionId
                    AND `order_line_item`.version_id = :liveVersionId
            ) AS `orderLineItemsInSalesInterval`
                ON `orderLineItemsInSalesInterval`.`product_id` = `product`.`id`
                AND `orderLineItemsInSalesInterval`.`product_version_id` = `product`.`version_id`
            WHERE `product`.`version_id` = :liveVersionId
            ' . $additionalFilter . '
            GROUP BY `product`.id',
            [
                'sessionId' => hex2bin($aggregationSessionId),
                'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
                'fromDate' => $config->salesReferenceIntervalFromDate->format('Y-m-d'),
                'toDate' => $config->salesReferenceIntervalToDate->format('Y-m-d'),
                'referenceSalesToPredictionFactor' => $config->getReferenceSalesToPredictionFactor(),
                'considerOpenOrdersInPurchaseSuggestion' => $config->considerOpenOrdersInPurchaseSuggestion ? 1 : 0,
            ],
        );
    }

    public function getAggregationTechnicalName(): string
    {
        return DemandPlanningAnalyticsAggregation::TECHNICAL_NAME;
    }

    public function getAggregationItemsTableName(): string
    {
        return self::ITEM_TABLE_NAME;
    }
}
