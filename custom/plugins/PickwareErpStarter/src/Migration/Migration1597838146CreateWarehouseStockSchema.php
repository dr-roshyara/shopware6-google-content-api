<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1597838146CreateWarehouseStockSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1597838146;
    }

    public function update(Connection $connection): void
    {
        $connection->exec(
            'CREATE TABLE IF NOT EXISTS `pickware_erp_warehouse_stock` (
                `id` BINARY(16) NOT NULL,
                `quantity` INT(11) NOT NULL,
                `product_id` BINARY(16) NOT NULL,
                `product_version_id` BINARY(16) NOT NULL,
                `warehouse_id` BINARY(16) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE INDEX `pickware_erp_warehouse_stock.uidx.product.warehouse` (`product_id`, `product_version_id`, `warehouse_id`),
                INDEX `pickware_erp_warehouse_stock.idx.product` (`product_id`,`product_version_id`),
                INDEX `pickware_erp_warehouse_stock.idx.warehouse` (`warehouse_id`),
                CONSTRAINT `pickware_erp_warehouse_stock.fk.product`
                    FOREIGN KEY (`product_id`,`product_version_id`)
                    REFERENCES `product` (`id`,`version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `pickware_erp_warehouse_stock.fk.warehouse`
                    FOREIGN KEY (`warehouse_id`)
                    REFERENCES `pickware_erp_warehouse` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
