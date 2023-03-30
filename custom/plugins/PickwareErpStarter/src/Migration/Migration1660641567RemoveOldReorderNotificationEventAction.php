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

class Migration1660641567RemoveOldReorderNotificationEventAction extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1660641567;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'DELETE FROM `event_action` WHERE `event_name` = "pickware.erp.reorder.notification";',
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
