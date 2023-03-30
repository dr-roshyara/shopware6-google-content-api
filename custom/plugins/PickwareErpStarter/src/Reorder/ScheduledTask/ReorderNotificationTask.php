<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Reorder\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class ReorderNotificationTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'pickware_erp_reorder_notification';
    }

    public static function getDefaultInterval(): int
    {
        return 24 * 60 * 60;
    }
}
