<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\MessageQueueMonitoring\Controller;

use Pickware\PickwareErpStarter\MessageQueueMonitoring\MessageQueueMonitoringService;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class MessageQueueMonitoringController
{
    private MessageQueueMonitoringService $messageQueueMonitoringService;

    public function __construct(MessageQueueMonitoringService $messageQueueMonitoringService)
    {
        $this->messageQueueMonitoringService = $messageQueueMonitoringService;
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route(
     *     "/api/_action/pickware-erp/message-queue-monitoring/status",
     *     methods={"GET"}
     * )
     */
    public function getStatus(): JsonResponse
    {
        return new JsonResponse(['state' => $this->messageQueueMonitoringService->getStatus()]);
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route(
     *     "/api/_action/pickware-erp/message-queue-monitoring/log-login",
     *     methods={"POST"}
     * )
     */
    public function logLogin(): JsonResponse
    {
        $this->messageQueueMonitoringService->logAdministrationLogin();

        return new JsonResponse();
    }
}
