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
use Pickware\DalBundle\DatabaseBulkInsertService;
use Pickware\DalBundle\RetryableTransaction;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * The update of `product.sales` is not time critical: it does not need to be calculated immediately. That is why we
 * queue all product sales udpates and handle them periodically instead of in-place in each request. This is a
 * performance improvement e.g. when picking products with a lot of orders.
 * See issue: https://github.com/pickware/shopware-plugins/issues/3408
 */
class ProductSalesUpdater extends ScheduledTaskHandler
{
    private Connection $connection;
    private ?DatabaseBulkInsertService $bulkInsertWithUpdate;

    /**
     * @deprecated next-major: all constructor arguments will be non-optional in next major release. The constructor
     * arguments are kept backwards-compatible right now.
     */
    public function __construct(
        Connection $db,
        ?DatabaseBulkInsertService $bulkInsertWithUpdate = null,
        ?EntityRepositoryInterface $scheduledTaskRepository = null
    ) {
        if ($scheduledTaskRepository) {
            parent::__construct($scheduledTaskRepository);
        }

        $this->connection = $db;
        $this->bulkInsertWithUpdate = $bulkInsertWithUpdate;
    }

    public static function getHandledMessages(): iterable
    {
        return [ProductSalesUpdateTask::class];
    }

    public function run(): void
    {
        // We limit this query to 10.000 to not run in any time-out issues. If more than 10.000 products are in the
        // queue, they are handled in the next iteration of the scheduled task (the `created_at` sorting ensures that).
        // This limit number is an educated guess.
        $productIds = $this->connection->fetchFirstColumn(
            'SELECT HEX(`product_id`)
            FROM `pickware_erp_product_sales_update_queue`
            ORDER BY `created_at` ASC
            LIMIT 10000',
        );

        if (count($productIds) === 0) {
            return;
        }
        $this->updateSales(array_values($productIds));
        $this->connection->executeStatement(
            'DELETE FROM `pickware_erp_product_sales_update_queue` WHERE `product_id` IN (:productIds)',
            ['productIds' => array_map('hex2bin', $productIds)],
            ['productIds' => Connection::PARAM_STR_ARRAY],
        );
    }

    /**
     * @param String[] $productIds
     */
    public function addProductsToUpdateQueue(array $productIds): void
    {
        $insertValues = array_map(
            fn (string $productId) => [
                'id' => Uuid::randomBytes(),
                'product_id' => hex2bin($productId),
                'product_version_id' => hex2bin(Defaults::LIVE_VERSION),
            ],
            $productIds,
        );

        if ($this->bulkInsertWithUpdate) {
            $this->bulkInsertWithUpdate->insertOnDuplicateKeyUpdate(
                'pickware_erp_product_sales_update_queue',
                $insertValues,
                [],
                ['id'],
            );
        }
    }

    /**
     * @param String[] $productIds
     */
    public function updateSales(array $productIds): void
    {
        if (!$this->bulkInsertWithUpdate) {
            // The property was made optional for backwards compatibility in the constructor. Should not happen
            // during an actual request. Return early.
            return;
        }

        if (empty($productIds)) {
            return;
        }

        RetryableTransaction::retryable($this->connection, function () use ($productIds): void {
            $this->connection->executeStatement(
                'SELECT `id` FROM `product` WHERE `id` IN (:productIds) FOR UPDATE',
                ['productIds' => array_map('hex2bin', $productIds)],
                ['productIds' => Connection::PARAM_STR_ARRAY],
            );

            // By splitting the SELECT and the UPDATE query we work-around a performance problem. If the
            // queries were executed in one UPDATE ... JOIN query the query time would rise unexpectedly.
            $productSales = $this->connection->fetchAllAssociative(
                'SELECT
                    LOWER(HEX(`order_line_item`.`product_id`)) as `id`,
                    LOWER(HEX(`order_line_item`.`product_version_id`)) as `version_id`,
                    SUM(`order_line_item`.`quantity`) as `sales`,
                    0 as `stock`,
                    NOW(3) as `updated_at`
                FROM `order_line_item`
                    INNER JOIN `order`
                        ON `order`.`id` = `order_line_item`.`order_id`
                        AND `order`.`version_id` = `order_line_item`.`order_version_id`
                    INNER JOIN `state_machine_state`
                        ON `state_machine_state`.`id` = `order`.state_id
                        AND `state_machine_state`.`technical_name` = :completeStateTechnicalName
                WHERE `order_line_item`.`product_id` IN (:productIds)
                    AND `order_line_item`.`type` = :type
                    AND `order_line_item`.`version_id` = :liveVersionId
                    AND `order_line_item`.`product_id` IS NOT NULL
                GROUP BY `order_line_item`.`product_id`',
                [
                    'productIds' => array_map('hex2bin', $productIds),
                    'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
                    'completeStateTechnicalName' => OrderStates::STATE_COMPLETED,
                    'type' => LineItem::PRODUCT_LINE_ITEM_TYPE,
                ],
                [
                    'productIds' => Connection::PARAM_STR_ARRAY,
                ],
            );

            $convertedValues = [];
            foreach ($productSales as $productSale) {
                $productSale['id'] = hex2bin($productSale['id']);
                $productSale['version_id'] = hex2bin($productSale['version_id']);

                $convertedValues[] = $productSale;
            }

            if ($this->bulkInsertWithUpdate) {
                $this->bulkInsertWithUpdate->insertOnDuplicateKeyUpdate(
                    'product',
                    $convertedValues,
                    [],
                    ['sales'],
                );
            }
        });
    }
}
