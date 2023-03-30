<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\StockFlow;

use Doctrine\DBAL\Connection;
use Pickware\PickwareErpStarter\Stock\Model\StockMovementDefinition;
use Pickware\PickwareErpStarter\StockApi\StockLocationReference;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\Uuid\Uuid;

class StockFlowService
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @return StockFlow[]
     */
    public function getStockFlow(StockLocationReference $stockLocationReference): array
    {
        $incoming = $this->calculateStockFlow($stockLocationReference, StockLocationReference::POSITION_DESTINATION);
        $outgoing = $this->calculateStockFlow($stockLocationReference, StockLocationReference::POSITION_SOURCE);
        $productIds = array_unique(array_merge(array_keys($incoming), array_keys($outgoing)));
        $stockFlow = [];
        foreach ($productIds as $productId) {
            if (!array_key_exists($productId, $stockFlow)) {
                $stockFlow[$productId] = new StockFlow(0, 0);
            }
            if (array_key_exists($productId, $incoming)) {
                $stockFlow[$productId]->incoming = (int) $incoming[$productId]['quantity'];
            }
            if (array_key_exists($productId, $outgoing)) {
                $stockFlow[$productId]->outgoing = (int) $outgoing[$productId]['quantity'];
            }
        }

        return $stockFlow;
    }

    /**
     * @param string $stockLocationPosition 'source', 'destination'
     */
    private function calculateStockFlow(
        StockLocationReference $stockLocation,
        string $stockLocationPosition
    ): array {
        $stockLocationPrimaryKey = $stockLocation->getPrimaryKey();
        $referencingPrimaryKeyFieldName = $stockLocation->getDatabasePrimaryKeyFieldName($stockLocationPosition);

        $queryBuilder = new QueryBuilder($this->connection);
        $queryBuilder
            ->addSelect([
                'LOWER(HEX(product_id)) AS productId',
                'SUM(quantity) AS quantity',
            ])
            ->from(StockMovementDefinition::ENTITY_NAME)
            ->andWhere(
                'product_version_id = :liveVersionId',
                sprintf('%s = :stockLocationPrimaryKey', $referencingPrimaryKeyFieldName),
            )
            ->groupBy('product_id')
            ->setParameter('liveVersionId', hex2bin(Defaults::LIVE_VERSION))
            ->setParameter(
                'stockLocationPrimaryKey',
                Uuid::isValid($stockLocationPrimaryKey) ? hex2bin($stockLocationPrimaryKey) : $stockLocationPrimaryKey,
            );

        $referencingVersionFieldName = $stockLocation->getDatabaseVersionFieldName($stockLocationPosition);
        if ($referencingVersionFieldName) {
            // Not all non-special stock locations reference a version field
            $queryBuilder->andWhere(sprintf('%s = :liveVersionId', $referencingVersionFieldName));
        }

        $stockFlow = $queryBuilder->execute()->fetchAllAssociative();

        // Return stock flow by product id
        return array_combine(array_column($stockFlow, 'productId'), $stockFlow);
    }
}
