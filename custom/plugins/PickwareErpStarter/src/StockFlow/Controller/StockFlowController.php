<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\StockFlow\Controller;

use Pickware\PickwareErpStarter\StockApi\StockLocationReference;
use Pickware\PickwareErpStarter\StockFlow\StockFlowService;
use Pickware\ValidationBundle\JsonValidator;
use Pickware\ValidationBundle\JsonValidatorException;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class StockFlowController
{
    private JsonValidator $jsonValidator;
    private StockFlowService $stockFlowService;

    public function __construct(StockFlowService $stockFlowService, JsonValidator $jsonValidator)
    {
        $this->stockFlowService = $stockFlowService;
        $this->jsonValidator = $jsonValidator;
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route(
     *     "/api/_action/pickware-erp/calculate-stock-flow-for-stock-location",
     *     name="api.action.pickware-erp.calculate-stock-flow-for-stock-location",
     *     methods={"POST"}
     * )
     */
    public function calculateStockFlowForStockLocation(Request $request): JsonResponse
    {
        $stockLocationJson = $request->getContent();
        try {
            $this->jsonValidator->validateJsonAgainstSchema($stockLocationJson, StockLocationReference::JSON_SCHEMA_FILE);
        } catch (JsonValidatorException $exception) {
            return $exception->serializeToJsonApiError()->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
        }
        $stockLocation = json_decode($stockLocationJson, true);

        return new JsonResponse($this->stockFlowService->getStockFlow(
            StockLocationReference::create($stockLocation),
        ));
    }
}
