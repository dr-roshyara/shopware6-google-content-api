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
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1645190845FixProductVersionIdMissingInSupplierOrderLineItem extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1645190845;
    }

    public function update(Connection $connection): void
    {
        $connection->transactional(function (Connection $connection): void {
            // First null all broken references (because the product was deleted)
            $connection->executeStatement(
                'UPDATE `pickware_erp_supplier_order_line_item` `line_item`
                LEFT JOIN `product`
                    ON `line_item`.`product_id` = `product`.`id` AND `product`.`version_id` = :liveVersionId
                SET `line_item`.`product_id` = NULL
                WHERE `product`.`id` IS NULL',
                ['liveVersionId' => hex2bin(Defaults::LIVE_VERSION)],
            );

            $connection->executeStatement(
                'UPDATE `pickware_erp_supplier_order_line_item`
                SET `product_version_id` = :liveVersionId
                WHERE `product_id` IS NOT NULL',
                ['liveVersionId' => hex2bin(Defaults::LIVE_VERSION)],
            );
        });
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
