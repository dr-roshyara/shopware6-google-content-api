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

class Migration1642690994UpdateDemandPlanningListItemSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1642690994;
    }

    public function update(Connection $connection): void
    {
        $connection->exec(
            'ALTER TABLE `pickware_erp_demand_planning_list_item`
            ADD COLUMN `stock` INT(11) DEFAULT 0 NOT NULL AFTER `reserved_stock`,
            ADD COLUMN `reorder_point` INT(11) NULL AFTER `stock`,
            ADD COLUMN `incoming_stock` INT(11) DEFAULT 0 NOT NULL AFTER `reorder_point`;',
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
