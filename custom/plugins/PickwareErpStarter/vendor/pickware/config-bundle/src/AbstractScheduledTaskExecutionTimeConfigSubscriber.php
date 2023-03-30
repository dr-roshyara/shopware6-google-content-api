<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ConfigBundle;

use DateTime;
use DateTimeZone;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Use this subscriber to allow setting the execution time (not datetime!) of a scheduled task via plugin configuration.
 */
abstract class AbstractScheduledTaskExecutionTimeConfigSubscriber implements EventSubscriberInterface
{
    private ScheduledTaskExecutionTimeUpdater $scheduledTaskExecutionTimeUpdater;
    private string $configurationKey;
    private string $scheduledTaskClassName;

    public function __construct(
        ScheduledTaskExecutionTimeUpdater $scheduledTaskExecutionTimeUpdater,
        string $configurationKey,
        string $scheduledTaskClassName
    ) {
        $this->scheduledTaskExecutionTimeUpdater = $scheduledTaskExecutionTimeUpdater;
        $this->configurationKey = $configurationKey;
        $this->scheduledTaskClassName = $scheduledTaskClassName;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'system_config.written' => 'afterSystemConfigWritten',
        ];
    }

    /**
     * Listens to configuration changes and uses the time (not datetime!) input of a config field to update the next
     * execution time of the scheduled task.
     */
    public function afterSystemConfigWritten(EntityWrittenEvent $event): void
    {
        $writeResults = $event->getWriteResults();
        foreach ($writeResults as $writeResult) {
            $payload = $writeResult->getPayload();

            // Since scheduled tasks are unique, we only support a single global configuration (i.e. not for a specific
            // sales channel) for scheduled task configuration. Ignore configurations that have a sales channel id.
            $isSalesChannelConfiguration = $payload['salesChannelId'] !== null;
            if ($isSalesChannelConfiguration || $payload['configurationKey'] !== $this->configurationKey) {
                continue;
            }

            $nextExecutionTimeInUTC = DateTime::createFromFormat('H:i:s', $payload['configurationValue'], new DateTimeZone('UTC'));
            $this->scheduledTaskExecutionTimeUpdater->updateExecutionTimeOfScheduledTask(
                $this->scheduledTaskClassName,
                $nextExecutionTimeInUTC,
                $event->getContext(),
            );
        }
    }
}
