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
use Doctrine\DBAL\Exception;
use LogicException;
use Pickware\DalBundle\RetryableTransaction;
use Pickware\PickwareErpStarter\Stock\Model\LocationTypeDefinition;
use Pickware\PickwareErpStarter\Stock\Model\StockMovementDefinition;
use Pickware\PickwareErpStarter\Warehouse\Model\ProductWarehouseConfigurationDefinition;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductStreamUpdater;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\ChangeSetAware;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductStockUpdater implements EventSubscriberInterface
{
    private Connection $db;
    private EventDispatcherInterface $eventDispatcher;
    private ?WarehouseStockUpdater $warehouseStockUpdater;
    private ?ProductStreamUpdater $productStreamUpdater;

    /**
     * @deprecated next major version: $warehouseStockUpdater argument will be non-optional
     */
    public function __construct(Connection $db, EventDispatcherInterface $eventDispatcher, ?WarehouseStockUpdater $warehouseStockUpdater = null, ?ProductStreamUpdater $productStreamUpdater = null)
    {
        $this->db = $db;
        $this->eventDispatcher = $eventDispatcher;
        $this->warehouseStockUpdater = $warehouseStockUpdater;
        $this->productStreamUpdater = $productStreamUpdater;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'preWriteValidation',
            StockMovementDefinition::ENTITY_WRITTEN_EVENT => 'stockMovementWritten',
            ProductWarehouseConfigurationDefinition::ENTITY_WRITTEN_EVENT => 'productWarehouseConfigurationWritten',
        ];
    }

    public function preWriteValidation(PreWriteValidationEvent $event): void
    {
        foreach ($event->getCommands() as $command) {
            if ($command instanceof ChangeSetAware) {
                $command->requestChangeSet();
            }
        }
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

        $this->recalculateStockFromStockMovements($stockMovementIds, $entityWrittenEvent->getContext());
    }

    public function recalculateStockFromStockMovementsForProducts(array $productIds, Context $context): void
    {
        if (!$this->warehouseStockUpdater) {
            throw new LogicException(sprintf(
                'The method "%s" cannot be called when the WarehouseStockUpdater is not initialized.',
                __METHOD__,
            ));
        }

        // Reset the stock for the product to 0 by removing all stock entries for it
        $this->db->executeStatement(
            'DELETE FROM `pickware_erp_stock`
                WHERE `product_id` IN (:productIds)
                AND `product_version_id` = :liveVersionId',
            [
                'productIds' => array_map('hex2bin', $productIds),
                'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
            ],
            [
                'productIds' => Connection::PARAM_STR_ARRAY,
            ],
        );
        // Reset the warehouse stock to 0 by updating the table so we keep rows with quantity 0
        $this->db->executeStatement(
            'UPDATE `pickware_erp_warehouse_stock`
                SET quantity = 0
                WHERE `product_id` IN (:productIds)
                AND `product_version_id` = :liveVersionId',
            [
                'productIds' => array_map('hex2bin', $productIds),
                'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
            ],
            [
                'productIds' => Connection::PARAM_STR_ARRAY,
            ],
        );

        // Rebuild the stock index for the product by re-indexing all stock movements related to it
        $stockMovements = $this->db->fetchAllAssociative(
            'SELECT
                LOWER(HEX(`id`)) AS `id`,
                LOWER(HEX(`product_id`)) AS `productId`
                FROM `pickware_erp_stock_movement`
                WHERE `product_id` IN (:productIds)
                AND `product_version_id` = :liveVersionId',
            [
                'productIds' => array_map('hex2bin', $productIds),
                'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
            ],
            [
                'productIds' => Connection::PARAM_STR_ARRAY,
            ],
        );

        $productIdsWithStockMovements = array_column($stockMovements, 'productId');
        $productIdsWithoutStockMovements = array_diff($productIds, $productIdsWithStockMovements);
        if (count($productIdsWithoutStockMovements) > 0) {
            // Reset the product stock to 0 as this product does not have any stock movements
            $this->db->executeStatement(
                'UPDATE `product`
                    SET stock = 0,
                        available_stock = 0
                WHERE `id` IN (:productIds)
                    AND `version_id` = :liveVersionId',
                [
                    'productIds' => array_map('hex2bin', $productIdsWithoutStockMovements),
                    'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
                ],
                [
                    'productIds' => Connection::PARAM_STR_ARRAY,
                ],
            );
        }

        $stockMovementIds = array_column($stockMovements, 'id');
        $this->recalculateStockFromStockMovements($stockMovementIds, $context);
        $this->warehouseStockUpdater->indexStockMovements($stockMovementIds, $context);
    }

    public function recalculateStockFromStockMovements(array $stockMovementIds, Context $context = null): void
    {
        $stockMovementIds = array_values(array_unique($stockMovementIds));
        $stockMovements = $this->db->fetchAllAssociative(
            'SELECT
                LOWER(HEX(product_id)) AS productId,
                LOWER(HEX(product_version_id)) AS productVersionId,
                source_location_type_technical_name AS sourceLocationTypeTechnicalName,
                LOWER(HEX(source_warehouse_id)) AS sourceWarehouseId,
                LOWER(HEX(source_bin_location_id)) AS sourceBinLocationId,
                LOWER(HEX(source_order_id)) AS sourceOrderId,
                LOWER(HEX(source_order_version_id)) AS sourceOrderVersionId,
                LOWER(HEX(source_supplier_order_id)) AS sourceSupplierOrderId,
                LOWER(HEX(source_stock_container_id)) AS sourceStockContainerId,
                LOWER(HEX(source_return_order_id)) AS sourceReturnOrderId,
                LOWER(HEX(source_return_order_version_id)) AS sourceReturnOrderVersionId,
                source_special_stock_location_technical_name AS sourceSpecialStockLocationTechnicalName,
                destination_location_type_technical_name AS destinationLocationTypeTechnicalName,
                LOWER(HEX(destination_warehouse_id)) AS destinationWarehouseId,
                LOWER(HEX(destination_bin_location_id)) AS destinationBinLocationId,
                LOWER(HEX(destination_order_id)) AS destinationOrderId,
                LOWER(HEX(destination_order_version_id)) AS destinationOrderVersionId,
                LOWER(HEX(destination_supplier_order_id)) AS destinationSupplierOrderId,
                LOWER(HEX(destination_stock_container_id)) AS destinationStockContainerId,
                LOWER(HEX(destination_return_order_id)) AS destinationReturnOrderId,
                LOWER(HEX(destination_return_order_version_id)) AS destinationReturnOrderVersionId,
                destination_special_stock_location_technical_name AS destinationSpecialStockLocationTechnicalName,
                SUM(quantity) AS quantity
            FROM pickware_erp_stock_movement
            WHERE id IN (:stockMovementIds) AND product_version_id = :liveVersionId
            GROUP BY
                `product_id`,
                `source_location_type_technical_name`,
                `source_warehouse_id`,
                `source_bin_location_id`,
                `source_order_id`,
                `source_order_version_id`,
                `source_supplier_order_id`,
                `source_stock_container_id`,
                `source_return_order_id`,
                `source_return_order_version_id`,
                `source_special_stock_location_technical_name`,
                `destination_location_type_technical_name`,
                `destination_warehouse_id`,
                `destination_bin_location_id`,
                `destination_order_id`,
                `destination_order_version_id`,
                `destination_supplier_order_id`,
                `destination_stock_container_id`,
                `destination_return_order_id`,
                `destination_return_order_version_id`,
                `destination_special_stock_location_technical_name`',
            [
                'stockMovementIds' => array_map('hex2bin', $stockMovementIds),
                'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
            ],
            ['stockMovementIds' => Connection::PARAM_STR_ARRAY],
        );

        RetryableTransaction::retryable($this->db, function () use ($stockMovements, $context): void {
            $productIds = [];
            foreach ($stockMovements as $stockMovement) {
                $this->persistStockChange(
                    [
                        'productId' => $stockMovement['productId'],
                        'productVersionId' => $stockMovement['productVersionId'],
                        'locationTypeTechnicalName' => $stockMovement['sourceLocationTypeTechnicalName'],
                        'warehouseId' => $stockMovement['sourceWarehouseId'] ?? null,
                        'binLocationId' => $stockMovement['sourceBinLocationId'] ?? null,
                        'orderId' => $stockMovement['sourceOrderId'] ?? null,
                        'orderVersionId' => $stockMovement['sourceOrderVersionId'] ?? null,
                        'supplierOrderId' => $stockMovement['sourceSupplierOrderId'] ?? null,
                        'stockContainerId' => $stockMovement['sourceStockContainerId'] ?? null,
                        'returnOrderId' => $stockMovement['sourceReturnOrderId'] ?? null,
                        'returnOrderVersionId' => $stockMovement['sourceReturnOrderVersionId'] ?? null,
                        'specialStockLocationTechnicalName' => $stockMovement['sourceSpecialStockLocationTechnicalName'] ?? null,
                        'changeAmount' => -1 * $stockMovement['quantity'],
                    ],
                );
                $this->persistStockChange(
                    [
                        'productId' => $stockMovement['productId'],
                        'productVersionId' => $stockMovement['productVersionId'],
                        'locationTypeTechnicalName' => $stockMovement['destinationLocationTypeTechnicalName'],
                        'warehouseId' => $stockMovement['destinationWarehouseId'] ?? null,
                        'binLocationId' => $stockMovement['destinationBinLocationId'] ?? null,
                        'orderId' => $stockMovement['destinationOrderId'] ?? null,
                        'orderVersionId' => $stockMovement['destinationOrderVersionId'] ?? null,
                        'supplierOrderId' => $stockMovement['destinationSupplierOrderId'] ?? null,
                        'stockContainerId' => $stockMovement['destinationStockContainerId'] ?? null,
                        'returnOrderId' => $stockMovement['destinationReturnOrderId'] ?? null,
                        'returnOrderVersionId' => $stockMovement['destinationReturnOrderVersionId'] ?? null,
                        'specialStockLocationTechnicalName' => $stockMovement['destinationSpecialStockLocationTechnicalName'] ?? null,
                        'changeAmount' => 1 * $stockMovement['quantity'],
                    ],
                );
                $productIds[] = $stockMovement['productId'];
            }

            $this->cleanUpStocks($productIds);

            $this->recalculateProductStock($productIds);

            if ($this->productStreamUpdater) {
                // Product streams can use the stock as a filter. Because of this we need to update the product stream
                // mappings via the productStreamUpdater to make sure dynamic product groups are updated.
                // For further reference see https://github.com/pickware/shopware-plugins/issues/3232
                $this->productStreamUpdater->updateProducts($productIds, $context);
            }
        });

        $this->eventDispatcher->dispatch(
            new StockUpdatedForStockMovementsEvent($stockMovements),
            StockUpdatedForStockMovementsEvent::EVENT_NAME,
        );
    }

    public function productWarehouseConfigurationWritten(EntityWrittenEvent $entityWrittenEvent): void
    {
        if ($entityWrittenEvent->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $writeResults = $entityWrittenEvent->getWriteResults();
        $oldProductBinLocations = [];
        $newProductBinLocations = [];
        foreach ($writeResults as $writeResult) {
            $changeSet = $writeResult->getChangeSet();
            $payload = $writeResult->getPayload();
            if ($changeSet && $changeSet->hasChanged('default_bin_location_id')) {
                $productId = $changeSet->getBefore('product_id');
                $oldDefaultBinLocationId = $changeSet->getBefore('default_bin_location_id');
                if ($oldDefaultBinLocationId) {
                    $oldProductBinLocations[] = new ProductBinLocation(bin2hex($productId), bin2hex($oldDefaultBinLocationId));
                }

                $newDefaultBinLocationId = $changeSet->getAfter('default_bin_location_id');
                if ($newDefaultBinLocationId) {
                    $newProductBinLocations[] = new ProductBinLocation(bin2hex($productId), bin2hex($newDefaultBinLocationId));
                }
            } elseif ($writeResult->getOperation() === EntityWriteResult::OPERATION_INSERT) {
                $defaultBinLocationId = $payload['defaultBinLocationId'] ?? null;
                if ($defaultBinLocationId) {
                    $newProductBinLocations[] = new ProductBinLocation($payload['productId'], $defaultBinLocationId);
                }
            }
        }

        $this->deleteStockEntriesForOldDefaultBinLocations($oldProductBinLocations);
        $this->upsertStockEntriesForDefaultBinLocations($newProductBinLocations);
    }

    public function upsertStockEntriesForDefaultBinLocationsOfProducts(array $productIds): void
    {
        $configurations = $this->db->fetchAllAssociative(
            'SELECT
                LOWER(HEX(product_id)) AS productId,
                LOWER(HEX(default_bin_location_id)) AS binLocationId
            FROM pickware_erp_product_warehouse_configuration
            WHERE product_id IN (:productIds)
                AND product_version_id = :liveVersionId
                AND default_bin_location_id IS NOT NULL',
            [
                'productIds' => array_map('hex2bin', $productIds),
                'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
            ],
            [
                'productIds' => Connection::PARAM_STR_ARRAY,
            ],
        );

        $productBinLocations = array_map(static fn (array $configuration) => new ProductBinLocation($configuration['productId'], $configuration['binLocationId']), $configurations);

        $this->upsertStockEntriesForDefaultBinLocations($productBinLocations);
    }

    /**
     * @param ProductBinLocation[] $productBinLocations
     * @throws Exception
     */
    private function upsertStockEntriesForDefaultBinLocations(array $productBinLocations): void
    {
        if (count($productBinLocations) > 0) {
            $tuples = implode(', ', array_map(static function (ProductBinLocation $productBinLocation) {
                return sprintf(
                    '(UNHEX(\'%s\'), UNHEX(\'%s\'), UNHEX(\'%s\'), "%s", UNHEX(\'%s\'), 0, NOW())',
                    Uuid::randomHex(),
                    $productBinLocation->getProductId(),
                    Defaults::LIVE_VERSION,
                    LocationTypeDefinition::TECHNICAL_NAME_BIN_LOCATION,
                    $productBinLocation->getBinLocationId(),
                );
            }, $productBinLocations));

            $query = sprintf(
                'INSERT IGNORE INTO `pickware_erp_stock`
                (
                    `id`,
                    `product_id`,
                    `product_version_id`,
                    `location_type_technical_name`,
                    `bin_location_id`,
                    `quantity`,
                    `created_at`
                ) VALUES %s',
                $tuples,
            );

            $this->db->executeStatement($query);
        }
    }

    /**
     * Deletes stock entries for the given default bin location and products if it has no stock.
     *
     * @param ProductBinLocation[] $productBinLocations
     * @throws Exception
     */
    private function deleteStockEntriesForOldDefaultBinLocations(array $productBinLocations): void
    {
        if (count($productBinLocations) > 0) {
            $tuples = implode(', ', array_map(static function (ProductBinLocation $productBinLocation) {
                return sprintf(
                    '(UNHEX(\'%s\'), UNHEX(\'%s\'))',
                    $productBinLocation->getProductId(),
                    $productBinLocation->getBinLocationId(),
                );
            }, $productBinLocations));

            $query = sprintf(
                'DELETE `pickware_erp_stock` FROM `pickware_erp_stock`
                WHERE `pickware_erp_stock`.`quantity` = 0
                AND `pickware_erp_stock`.`product_version_id` = :liveVersionId
                AND (`pickware_erp_stock`.`product_id`, `pickware_erp_stock`.`bin_location_id`) IN (%s)',
                $tuples,
            );

            $this->db->executeStatement(
                $query,
                ['liveVersionId' => hex2bin(Defaults::LIVE_VERSION)],
            );
        }
    }

    private function persistStockChange(array $payload): void
    {
        $this->db->executeStatement(
            'INSERT INTO pickware_erp_stock (
                id,
                product_id,
                product_version_id,
                quantity,
                location_type_technical_name,
                warehouse_id,
                bin_location_id,
                order_id,
                order_version_id,
                supplier_order_id,
                stock_container_id,
                return_order_id,
                return_order_version_id,
                special_stock_location_technical_name,
                created_at
            ) VALUES (
                :id,
                :productId,
                :productVersionId,
                :changeAmount,
                :locationTypeTechnicalName,
                :warehouseId,
                :binLocationId,
                :orderId,
                :orderVersionId,
                :supplierOrderId,
                :stockContainerId,
                :returnOrderId,
                :returnOrderVersionId,
                :specialStockLocationTechnicalName,
                NOW(3)
            ) ON DUPLICATE KEY UPDATE
                quantity = quantity + VALUES(quantity),
                updated_at = NOW(3)',
            [
                'id' => Uuid::randomBytes(),
                'locationTypeTechnicalName' => $payload['locationTypeTechnicalName'],
                'productId' => hex2bin($payload['productId']),
                'productVersionId' => hex2bin($payload['productVersionId']),
                'warehouseId' => $payload['warehouseId'] ? hex2bin($payload['warehouseId']) : null,
                'binLocationId' => $payload['binLocationId'] ? hex2bin($payload['binLocationId']) : null,
                'orderId' => $payload['orderId'] ? hex2bin($payload['orderId']) : null,
                'orderVersionId' => $payload['orderVersionId'] ? hex2bin($payload['orderVersionId']) : null,
                'supplierOrderId' => $payload['supplierOrderId'] ? hex2bin($payload['supplierOrderId']) : null,
                'stockContainerId' => $payload['stockContainerId'] ? hex2bin($payload['stockContainerId']) : null,
                'returnOrderId' => $payload['returnOrderId'] ? hex2bin($payload['returnOrderId']) : null,
                'returnOrderVersionId' => $payload['returnOrderVersionId'] ? hex2bin($payload['returnOrderVersionId']) : null,
                'specialStockLocationTechnicalName' => $payload['specialStockLocationTechnicalName'],
                'changeAmount' => $payload['changeAmount'],
            ],
        );
    }

    /**
     * Clears (deletes) stock values that are irrelevant. These are stocks that
     *   - have quantity 0 or
     *   - are in any non-special stock location that was deleted
     */
    private function cleanUpStocks(array $productIds): void
    {
        $this->db->executeStatement(
            'DELETE `stock`
            FROM `pickware_erp_stock` AS `stock`
            LEFT JOIN `pickware_erp_product_warehouse_configuration` AS `product_warehouse_configuration`
                ON `stock`.`product_id` = `product_warehouse_configuration`.product_id
                    AND `stock`.`bin_location_id` = `product_warehouse_configuration`.`default_bin_location_id`
            WHERE
                (
                    `stock`.`quantity` = 0 OR
                    (`stock`.`location_type_technical_name` = "warehouse" AND `stock`.`warehouse_id` IS NULL) OR
                    (`stock`.`location_type_technical_name` = "bin_location" AND `stock`.`bin_location_id` IS NULL) OR
                    (`stock`.`location_type_technical_name` = "order" AND `stock`.`order_id` IS NULL) OR
                    (`stock`.`location_type_technical_name` = "supplier_order" AND `stock`.`supplier_order_id` IS NULL) OR
                    (`stock`.`location_type_technical_name` = "stock_container" AND `stock`.`stock_container_id` IS NULL) OR
                    (`stock`.`location_type_technical_name` = "return_order" AND `stock`.`return_order_id` IS NULL)
                )
            AND `stock`.`product_version_id` = :liveVersionId
            AND `stock`.`product_id` IN (:productIds)
            AND `product_warehouse_configuration`.`default_bin_location_id` IS NULL
            ',
            [
                'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
                'productIds' => array_map('hex2bin', $productIds),
            ],
            [
                'productIds' => Connection::PARAM_STR_ARRAY,
            ],
        );
    }

    private function recalculateProductStock(array $productIds): void
    {
        $query = '
            UPDATE `product`
            LEFT JOIN (
                SELECT
                    `stock`.`product_id` as `product_id`,
                    `stock`.`product_version_id` as `product_version_id`,
                    SUM(`stock`.`quantity`) AS `quantity`
                FROM `pickware_erp_stock` `stock`
                LEFT JOIN `pickware_erp_location_type` AS `location_type`
                    ON `stock`.`location_type_technical_name` = `location_type`.`technical_name`
                WHERE `location_type`.`internal` = 1
                AND `stock`.`product_id` IN (:productIds) AND `stock`.`product_version_id` = :liveVersionId
                GROUP BY
                    `stock`.`product_id`,
                    `stock`.`product_version_id`
            ) AS `totalStocks`
                ON
                    `totalStocks`.`product_id` = `product`.`id`
                    AND `totalStocks`.`product_version_id` = `product`.`version_id`
            SET
                -- The following "term" updates the stock and the available stock such that the "reserved stock" stays
                -- constant.
                `product`.`available_stock` =
                    -- <=> stock - reserved stock
                    IFNULL(`totalStocks`.`quantity`, 0) - (`product`.`stock` - `product`.`available_stock`),
                `product`.`stock` = IFNULL(`totalStocks`.`quantity`, 0)
            WHERE `product`.`version_id` = :liveVersionId
            AND `product`.`id` IN (:productIds)';

        $params = [
            'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
            'productIds' => array_map('hex2bin', $productIds),
        ];
        $paramTypes = [
            'productIds' => Connection::PARAM_STR_ARRAY,
        ];
        $this->db->executeStatement($query, $params, $paramTypes);

        $this->eventDispatcher->dispatch(
            new ProductAvailableStockUpdatedEvent($productIds),
            ProductAvailableStockUpdatedEvent::EVENT_NAME,
        );
    }
}
