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

use Exception;
use Pickware\PickwareErpStarter\Logger\PickwareErpEvents;
use Pickware\PickwareErpStarter\Reorder\ReorderNotificationService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;

class ReorderNotificationHandler extends ScheduledTaskHandler
{
    private ReorderNotificationService $reorderNotificationService;
    private LoggerInterface $logger;

    public function __construct(
        EntityRepositoryInterface $scheduledTaskRepository,
        ReorderNotificationService $reorderNotificationService,
        LoggerInterface $logger
    ) {
        parent::__construct($scheduledTaskRepository);

        $this->reorderNotificationService = $reorderNotificationService;
        $this->logger = $logger;
    }

    public static function getHandledMessages(): iterable
    {
        return [ReorderNotificationTask::class];
    }

    public function run(): void
    {
        try {
            $this->reorderNotificationService->sendReorderNotification(Context::createDefaultContext());
            $this->logger->info(
                PickwareErpEvents::SCHEDULED_TASK_SUCCESSFUL,
                [
                    'taskName' => ReorderNotificationTask::getTaskName(),
                ],
            );
        } catch (Exception $exception) {
            // Catch exceptions of the handler and log the result so the task does not crash in the worker cycle.
            $this->logger->error(
                PickwareErpEvents::SCHEDULED_TASK_ERROR,
                [
                    'message' => $exception->getMessage(),
                    'stackTrace' => $exception->getTraceAsString(),
                ],
            );
        }
    }
}
