<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Config\Controller;

use Pickware\HttpUtils\ResponseFactory;
use Pickware\PickwareErpStarter\Config\Config;
use Pickware\PickwareErpStarter\Config\GlobalPluginConfig;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ConfigController
{
    private Config $config;
    private GlobalPluginConfig $globalPluginConfig;

    public function __construct(Config $config, GlobalPluginConfig $globalPluginConfig)
    {
        $this->config = $config;
        $this->globalPluginConfig = $globalPluginConfig;
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route(
     *     "/api/_action/pickware-erp/set-default-warehouse",
     *     name="api.action.pickware-erp.set-default-warehouse",
     *     methods={"POST"}
     * )
     */
    public function setDefaultWarehouse(Request $request): JsonResponse
    {
        $warehouseId = $request->get('warehouseId');
        if (!$warehouseId || !Uuid::isValid($warehouseId)) {
            return ResponseFactory::createUuidParameterMissingResponse('warehouseId');
        }

        $this->config->setDefaultWarehouseId($warehouseId);

        return new JsonResponse();
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route(
     *     "/api/_action/pickware-erp/get-stock-movement-comments",
     *     name="api.action.pickware-erp.get-stock-movement-comments",
     *     methods={"POST"}
     * )
     */
    public function getStockMovementComments(Request $request): JsonResponse
    {
        return new JsonResponse([
            'stockMovementComments' => $this->globalPluginConfig->getDefaultStockMovementComments(),
        ]);
    }
}
