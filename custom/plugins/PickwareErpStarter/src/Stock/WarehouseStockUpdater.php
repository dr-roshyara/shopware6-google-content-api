<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stock;

use Doctrine\DBAL\Connection;
use Pickware\PickwareErpStarter\Stock\Model\StockMovementDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class WarehouseStockUpdater implements EventSubscriberInterface
{
    private Connection $db;
    private ?EventDispatcherInterface $eventDispatcher;

    /**
     * @deprecated next major version: $eventDispatcher argument will be non-optional
     */
    public function __construct(Connection $db, ?EventDispatcherInterface $eventDispatcher = null)
    {
        $this->db = $db;
        $this->eventDispatcher = $eventDispatcher;
    }

    public static function getSubscribedEvents(): array
    {
        return [StockMovementDefinition::ENTITY_WRITTEN_EVENT => 'stockMovementWritten'];
    }

    public function stockMovementWritten(EntityWrittenEvent $entityWrittenEvent): void
    {
        if ($entityWrittenEvent->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $stockMovementIds = [];
        foreach ($entityWrittenEvent->getWriteResults() as $writeResult) {
            if ($writeResult->getExistence()->exists()) {
                // Updating stock movements is not supported yet
                // In case a stock location is deleted, this code path is also reached. This is because an
                // EntityWrittenEvent is triggered when an entity field gets null-ed because of a SET NULL constraint
                // of a FK.
                continue;
            }
            $payload = $writeResult->getPayload();
            $stockMovementIds[] = $payload['id'];
        }

        $this->indexStockMovements($stockMovementIds, $entityWrittenEvent->getContext());
    }

    public function indexStockMovements(array $stockMovementIds, Context $context): void
    {
        $stockMovementIds = array_values(array_unique($stockMovementIds));
        $stockMovements = $this->db->fetchAllAssociative(
            'SELECT
                LOWER(HEX(product_id)) AS productId,
                LOWER(HEX(product_version_id)) AS productVersionId,
                quantity,
                LOWER(HEX(COALESCE(
                    source_warehouse_id,
                    sourceBinLocation.warehouse_id
                ))) AS sourceWarehouseId,
                LOWER(HEX(COALESCE(
                    destination_warehouse_id,
                    destinationBinLocation.warehouse_id
                ))) AS destinationWarehouseId
            FROM pickware_erp_stock_movement stockMovement
            LEFT JOIN pickware_erp_bin_location sourceBinLocation ON sourceBinLocation.id = stockMovement.source_bin_location_id
            LEFT JOIN pickware_erp_bin_location destinationBinLocation ON destinationBinLocation.id = stockMovement.destination_bin_location_id
            WHERE stockMovement.id IN (:stockMovementIds) AND product_version_id = :liveVersionId',
            [
                'stockMovementIds' => array_map('hex2bin', $stockMovementIds),
                'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
            ],
            [
                'stockMovementIds' => Connection::PARAM_STR_ARRAY,
            ],
        );

        // Update warehouse stocks if stock was moved to or from a warehouse or bin location (in that warehouse) or
        // stock container (in that warehouse).
        $warehouseIds = [];
        $productIds = [];
        foreach ($stockMovements as $stockMovement) {
            $productIds = array_unique(array_merge(
                $productIds,
                [$stockMovement['productId']],
            ));
            if ($stockMovement['sourceWarehouseId']) {
                $this->persistWarehouseStockChange([
                    'productId' => $stockMovement['productId'],
                    'productVersionId' => $stockMovement['productVersionId'],
                    'warehouseId' => $stockMovement['sourceWarehouseId'],
                    'changeAmount' => -1 * $stockMovement['quantity'],
                ]);
            }
            if ($stockMovement['destinationWarehouseId']) {
                $this->persistWarehouseStockChange([
                    'productId' => $stockMovement['productId'],
                    'productVersionId' => $stockMovement['productVersionId'],
                    'warehouseId' => $stockMovement['destinationWarehouseId'],
                    'changeAmount' => 1 * $stockMovement['quantity'],
                ]);
            }

            // If the source and destination warehouse is identical (e.g. a stock move from one location in the
            // warehouse to another location in that warehouse), we do not need to track that warehouse id for the
            // warehouse stock change event. Because the stock in that warehouse did not change (stock ∓0).
            // If they are not identical (including that one of them may be null) track them for the warehouse stock
            // change event.
            if ($stockMovement['sourceWarehouseId'] !== $stockMovement['destinationWarehouseId']) {
                if ($stockMovement['sourceWarehouseId']) {
                    $warehouseIds[] = $stockMovement['sourceWarehouseId'];
                }
                if ($stockMovement['destinationWarehouseId']) {
                    $warehouseIds[] = $stockMovement['destinationWarehouseId'];
                }
            }
        }

        if ($this->eventDispatcher && (count($warehouseIds) > 0)) {
            $this->eventDispatcher->dispatch(
                new WarehouseStockUpdatedEvent(
                    array_values(array_unique($warehouseIds)),
                    $productIds,
                    $context,
                ),
                WarehouseStockUpdatedEvent::EVENT_NAME,
            );
        }
    }

    private function persistWarehouseStockChange(array $payload): void
    {
        $this->db->executeStatement(
            'INSERT INTO pickware_erp_warehouse_stock (
                id,
                product_id,
                product_version_id,
                quantity,
                warehouse_id,
                created_at
            ) VALUES (
                :id,
                :productId,
                :productVersionId,
                :changeAmount,
                :warehouseId,
                NOW(3)
            ) ON DUPLICATE KEY UPDATE
                quantity = quantity + VALUES(quantity),
                updated_at = NOW(3)',
            [
                'id' => Uuid::randomBytes(),
                'productId' => hex2bin($payload['productId']),
                'productVersionId' => hex2bin($payload['productVersionId']),
                'warehouseId' => hex2bin($payload['warehouseId']),
                'changeAmount' => $payload['changeAmount'],
            ],
        );
    }
}
