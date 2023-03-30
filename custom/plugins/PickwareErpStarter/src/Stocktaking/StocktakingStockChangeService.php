<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stocktaking;

use Doctrine\DBAL\Connection;
use Pickware\DalBundle\EntityManager;
use Pickware\DalBundle\Sql\SqlUuid;
use Pickware\PickwareErpStarter\ImportExport\ImportExportScheduler;
use Pickware\PickwareErpStarter\ImportExport\ImportExportSchedulerMessage;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportDefinition;
use Pickware\PickwareErpStarter\Stock\ImportExportProfile\RelativeStockChange\RelativeStockChangeImporter;
use Pickware\PickwareErpStarter\StockApi\StockLocationReferenceFinder;
use Pickware\PickwareErpStarter\Stocktaking\Model\StocktakeDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

class StocktakingStockChangeService
{
    private EntityManager $entityManager;
    private ImportExportScheduler $importExportScheduler;
    private Connection $connection;

    public function __construct(
        EntityManager $entityManager,
        ImportExportScheduler $importExportScheduler,
        Connection $connection
    ) {
        $this->entityManager = $entityManager;
        $this->importExportScheduler = $importExportScheduler;
        $this->connection = $connection;
    }

    public function persistStocktakeStockChanges(string $stocktakeId, string $userId, Context $context): void
    {
        $importId = Uuid::randomHex();
        $this->entityManager->runInTransactionWithRetry(function () use ($stocktakeId, $userId, $importId, $context): void {
            $this->entityManager->create(
                ImportExportDefinition::class,
                [
                    [
                        'id' => $importId,
                        'type' => ImportExportDefinition::TYPE_IMPORT,
                        'profileTechnicalName' => RelativeStockChangeImporter::TECHNICAL_NAME,
                        'state' => ImportExportDefinition::STATE_PENDING,
                        'stateData' => [],
                        'config' => ['stocktakeId' => $stocktakeId],
                        'userId' => $userId,
                    ],
                ],
                $context,
            );

            $this->snapshotCountingProcessItemsAndCreateImportExportElements($stocktakeId, $importId);

            $this->entityManager->update(
                StocktakeDefinition::class,
                [
                    [
                        'id' => $stocktakeId,
                        'importExportId' => $importId,
                    ],
                ],
                $context,
            );
        });

        // Skip CSV file validation and read, as there is no CSV file to go with this import. Also do not execute this
        // inside the transaction as any errors occurring at this point should not roll back import creation.
        $this->importExportScheduler->scheduleImport($importId, $context, ImportExportSchedulerMessage::STATE_EXECUTE_IMPORT);
    }

    /**
     * "Snapshots" the current state of the counting process items (including the stock values and calculated stock
     * differences of the respective products) to "snapshot counting process items".
     * Based on these "snapshot counting process items", import export elements are created for the items that need to
     * log and stock changes (= their calculated stock difference is not 0).
     *
     * Note that the product summary does not exist anymore if the product was deleted _before_ the stocktake was
     * completed. In this case we assume 0 for all summary values.
     */
    private function snapshotCountingProcessItemsAndCreateImportExportElements(string $stocktakeId, string $importId): void
    {
        $this->connection->executeStatement(
            'INSERT INTO `pickware_erp_stocktaking_stocktake_snapshot_item` (
                 `id`,
                 `counting_process_item_id`,
                 `warehouse_stock`,
                 `total_counted`,
                 `total_stock_difference`,
                 `stock_location_stock`,
                 `counted`,
                 `stock_difference`,
                 `created_at`
            ) SELECT
                ' . SqlUuid::UUID_V4_GENERATION . ',
                countingProcessItem.`id`,
                IFNULL(warehouseStock.`quantity`, 0), -- Information gets lost if the product was deleted
                IFNULL(stocktakeProductSummary.`counted_stock`, 0), -- Information gets lost if the product was deleted
                IFNULL(stocktakeProductSummary.`absolute_stock_difference`, 0), -- Information gets lost if the product was deleted
                IF(
                    countingProcess.`bin_location_id` IS NULL,
                    IF(
                        countingProcess.`bin_location_snapshot` IS NULL,
                        IFNULL(stockInUnknownStockLocation.`quantity`, 0), -- Information gets lost if the product was deleted
                        0 -- Bin location was counted but has been deleted before the stocktake was completed
                    ),
                    IFNULL(stockInBinLocation.`quantity`, 0)
                ),
                countingProcessItem.`quantity`,
                countingProcessItem.`quantity` - IF(
                    countingProcess.`bin_location_id` IS NULL,
                    IF(
                        countingProcess.`bin_location_snapshot` IS NULL,
                        IFNULL(stockInUnknownStockLocation.`quantity`, 0), -- Information gets lost if the product was deleted
                        0 -- Bin location was counted but has been deleted before the stocktake was completed
                    ),
                    IFNULL(stockInBinLocation.`quantity`, 0)
                ),
                NOW()
            FROM `pickware_erp_stocktaking_stocktake_counting_process_item` countingProcessItem
            LEFT JOIN `pickware_erp_stocktaking_stocktake_counting_process` countingProcess
                ON countingProcess.`id` = countingProcessItem.`counting_process_id`
            LEFT JOIN `pickware_erp_stocktaking_stocktake` stocktake
                ON stocktake.`id` = countingProcess.`stocktake_id`
            LEFT JOIN `pickware_erp_stocktaking_stocktake_product_summary` stocktakeProductSummary
                ON stocktakeProductSummary.`product_id` = countingProcessItem.`product_id`
                AND stocktakeProductSummary.`product_version_id` = countingProcessItem.`product_version_id`
                AND stocktakeProductSummary.`stocktake_id` = stocktake.`id`
            LEFT JOIN pickware_erp_warehouse_stock warehouseStock
                ON warehouseStock.`product_id` = countingProcessItem.`product_id`
                AND warehouseStock.`product_version_id` = countingProcessItem.`product_version_id`
                AND warehouseStock.`warehouse_id` = stocktake.`warehouse_id`
            LEFT JOIN pickware_erp_stock stockInUnknownStockLocation
                ON stockInUnknownStockLocation.`product_id` = countingProcessItem.`product_id`
                AND stockInUnknownStockLocation.`product_version_id` = countingProcessItem.`product_version_id`
                AND stockInUnknownStockLocation.`warehouse_id` = stocktake.`warehouse_id`
                AND stockInUnknownStockLocation.`location_type_technical_name` = "warehouse"
            LEFT JOIN pickware_erp_stock stockInBinLocation
                ON stockInBinLocation.`product_id` = countingProcessItem.`product_id`
                AND stockInBinLocation.`product_version_id` = countingProcessItem.`product_version_id`
                AND stockInBinLocation.`bin_location_id` = countingProcess.`bin_location_id`
                AND stockInBinLocation.`location_type_technical_name` = "bin_location"

            WHERE countingProcess.`stocktake_id` = :stocktakeId
            AND (
                -- Support deleted products which in turn means there is no productSummary to join
                countingProcessItem.`product_version_id` IS NULL OR
                countingProcessItem.`product_version_id` = :liveVersionId
            )',
            [
                'stocktakeId' => hex2bin($stocktakeId),
                'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
            ],
        );

        // The row json body must match the relative stock change import csv format
        $this->connection->executeStatement(
            'INSERT INTO `pickware_erp_import_export_element` (
                `id`,
                `import_export_id`,
                `row_number`,
                `row_data`,
                `created_at`
            ) SELECT
                ' . SqlUuid::UUID_V4_GENERATION . ',
                :importExportId,
                @rowNumber:=@rowNumber+1,
                JSON_OBJECT(
                    \'product number\', product.`product_number`,
                    \'warehouse code\', warehouse.`code`,
                    -- Note that if the bin location is null, the stock was counted in the unknown stock location. We have
                    -- to use the magic "binLocationCodeUnknown" keyword to change the stock in the unknown stock location.
                    -- Otherwise the stock would be changes "in the warehouse" according to the picking/stocking strategy.
                    \'bin location\', IFNULL(binLocation.`code`, :binLocationCodeUnknown),
                    \'change\', SUM(snapshotCountingProcessItem.`stock_difference`)
                ),
                NOW()
            FROM `pickware_erp_stocktaking_stocktake_snapshot_item` snapshotCountingProcessItem
            LEFT JOIN `pickware_erp_stocktaking_stocktake_counting_process_item` countingProcessItem
                ON countingProcessItem.`id` = snapshotCountingProcessItem.`counting_process_item_id`
            LEFT JOIN `pickware_erp_stocktaking_stocktake_counting_process` countingProcess
                ON countingProcess.`id` = countingProcessItem.`counting_process_id`
            LEFT JOIN `pickware_erp_stocktaking_stocktake` stocktake
                ON stocktake.`id` = countingProcess.`stocktake_id`
            LEFT JOIN `pickware_erp_warehouse` warehouse
                ON warehouse.`id` = stocktake.`warehouse_id`
            LEFT JOIN `pickware_erp_bin_location` binLocation
                ON binLocation.`id` = countingProcess.`bin_location_id`
            LEFT JOIN `product` product
                ON product.`id` = countingProcessItem.`product_id`
                AND product.`version_id` = countingProcessItem.`product_version_id`
            JOIN (SELECT @rowNumber := 0) r

            WHERE countingProcess.`stocktake_id` = :stocktakeId
            AND snapshotCountingProcessItem.`stock_difference` <> 0
            AND countingProcessItem.`product_version_id` = :liveVersionId
            -- Filter out elements if their bin location was deleted before stocktake was completed
            AND (countingProcess.`bin_location_id` IS NOT NULL OR countingProcess.`bin_location_snapshot` IS NULL)
            GROUP BY countingProcessItem.`product_id`, countingProcess.`bin_location_id`',
            [
                'stocktakeId' => hex2bin($stocktakeId),
                'importExportId' => hex2bin($importId),
                'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
                'binLocationCodeUnknown' => StockLocationReferenceFinder::BIN_LOCATION_CODE_UNKNOWN,
            ],
        );
    }
}
