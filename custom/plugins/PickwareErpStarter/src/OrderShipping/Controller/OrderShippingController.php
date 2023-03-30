<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\OrderShipping\Controller;

use Pickware\HttpUtils\ResponseFactory;
use Pickware\PickwareErpStarter\OrderShipping\NotEnoughStockException;
use Pickware\PickwareErpStarter\OrderShipping\OrderShippingException;
use Pickware\PickwareErpStarter\OrderShipping\OrderShippingService;
use Pickware\PickwareErpStarter\StockApi\StockMovementServiceValidationException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class OrderShippingController
{
    private OrderShippingService $orderShippingService;

    public function __construct(OrderShippingService $orderShippingService)
    {
        $this->orderShippingService = $orderShippingService;
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route(
     *     "/api/_action/pickware-erp/bulk-ship-order-completely",
     *     name="api.action.pickware-erp.bulk-ship-order-completely",
     *     methods={"POST"}
     * )
     */
    public function bulkShipOrderCompletely(Request $request, Context $context): JsonResponse
    {
        $orderIds = $request->get('orderIds', []);
        if (count($orderIds) === 0) {
            return ResponseFactory::createParameterMissingResponse('orderIds');
        }
        $warehouseId = $request->get('warehouseId');
        if (!$warehouseId || !Uuid::isValid($warehouseId)) {
            return ResponseFactory::createUuidParameterMissingResponse('warehouseId');
        }

        try {
            $this->orderShippingService->shipMultipleOrdersCompletely(
                $request->get('orderIds', []),
                $request->get('warehouseId'),
                $context,
            );
        } catch (NotEnoughStockException $exception) {
            return $exception->serializeToJsonApiError()
                ->toJsonApiErrorResponse()
                ->setStatusCode(Response::HTTP_PRECONDITION_FAILED);
        }

        return new JsonResponse();
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route(
     *     "/api/_action/pickware-erp/ship-order-completely",
     *     name="api.action.pickware-erp.ship-order-completely",
     *     methods={"POST"}
     * )
     */
    public function shipOrderCompletely(Request $request, Context $context): Response
    {
        $warehouseId = $request->get('warehouseId');
        if (!$warehouseId || !Uuid::isValid($warehouseId)) {
            return ResponseFactory::createUuidParameterMissingResponse('warehouseId');
        }
        $orderId = $request->get('orderId');
        if (!$orderId || !Uuid::isValid($orderId)) {
            return ResponseFactory::createUuidParameterMissingResponse('orderId');
        }

        try {
            $this->orderShippingService->shipOrderCompletely($orderId, $warehouseId, $context);
        } catch (OrderShippingException $e) {
            return $e->serializeToJsonApiError()->setStatus(Response::HTTP_CONFLICT)->toJsonApiErrorResponse();
        }

        return new JsonResponse();
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route(
     *     "/api/_action/pickware-erp/bulk-return-order-completely",
     *     name="api.action.pickware-erp.bulk-return-order-completely",
     *     methods={"POST"}
     * )
     */
    public function bulkReturnOrderCompletely(Request $request, Context $context): JsonResponse
    {
        $orderIds = $request->get('orderIds', []);
        if (count($orderIds) === 0) {
            return ResponseFactory::createParameterMissingResponse('orderIds');
        }
        $warehouseId = $request->get('warehouseId');
        if (!$warehouseId || !Uuid::isValid($warehouseId)) {
            return ResponseFactory::createUuidParameterMissingResponse('warehouseId');
        }

        $this->orderShippingService->returnMultipleOrdersCompletely(
            $request->get('orderIds', []),
            $request->get('warehouseId'),
            $context,
        );

        return new JsonResponse();
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route(
     *     "/api/_action/pickware-erp/return-order-completely",
     *     name="api.action.pickware-erp.return-order-completely",
     *     methods={"POST"}
     * )
     */
    public function returnOrderCompletely(Request $request, Context $context): Response
    {
        $warehouseId = $request->get('warehouseId');
        if (!$warehouseId || !Uuid::isValid($warehouseId)) {
            return ResponseFactory::createUuidParameterMissingResponse('warehouseId');
        }
        $orderId = $request->get('orderId');
        if (!$orderId || !Uuid::isValid($orderId)) {
            return ResponseFactory::createUuidParameterMissingResponse('orderId');
        }

        try {
            $this->orderShippingService->returnOrderCompletely($orderId, $warehouseId, $context);
        } catch (StockMovementServiceValidationException $stockMovementServiceValidationException) {
            return $stockMovementServiceValidationException->serializeToJsonApiError()->toJsonApiErrorResponse();
        }

        return new JsonResponse();
    }
}
