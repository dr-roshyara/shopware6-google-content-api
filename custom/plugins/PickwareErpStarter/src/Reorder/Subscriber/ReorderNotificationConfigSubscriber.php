<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Reorder\Subscriber;

use Pickware\ConfigBundle\AbstractScheduledTaskExecutionTimeConfigSubscriber;
use Pickware\ConfigBundle\ScheduledTaskExecutionTimeUpdater;
use Pickware\PickwareErpStarter\Reorder\ScheduledTask\ReorderNotificationTask;

class ReorderNotificationConfigSubscriber extends AbstractScheduledTaskExecutionTimeConfigSubscriber
{
    public function __construct(ScheduledTaskExecutionTimeUpdater $scheduledTaskExecutionTimeUpdater)
    {
        parent::__construct(
            $scheduledTaskExecutionTimeUpdater,
            'PickwareErpStarter.global-plugin-config.reorderNotificationTime',
            ReorderNotificationTask::class,
        );
    }
}
