<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\StockApi\Controller;

use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\PickwareErpStarter\StockApi\StockMovementParser;
use Pickware\PickwareErpStarter\StockApi\StockMovementService;
use Pickware\PickwareErpStarter\StockApi\StockMovementServiceValidationException;
use Pickware\ValidationBundle\JsonValidatorException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class StockMovementController
{
    private StockMovementService $stockMovementService;
    private StockMovementParser $stockMovementParser;

    public function __construct(StockMovementService $stockMovementService, StockMovementParser $stockMovementParser)
    {
        $this->stockMovementService = $stockMovementService;
        $this->stockMovementParser = $stockMovementParser;
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route(
     *     "/api/_action/pickware-erp/stock/move",
     *     name="api.action.pickware-erp.stock.move",
     *     methods={"POST"}
     * )
     */
    public function stockMove(Request $request, Context $context): Response
    {
        try {
            $stockMovements = $this->stockMovementParser->parseStockMovementsFromJson($request->getContent());
        } catch (JsonValidatorException $e) {
            return (new JsonApiError([
                'status' => Response::HTTP_BAD_REQUEST,
                'title' => 'Request payload invalid',
                'detail' => $e->getMessage(),
            ]))->toJsonApiErrorResponse();
        }

        try {
            $this->stockMovementService->moveStock($stockMovements, $context);
        } catch (StockMovementServiceValidationException $e) {
            return $e->serializeToJsonApiError()->setStatus(Response::HTTP_CONFLICT)->toJsonApiErrorResponse();
        }

        return new Response('', Response::HTTP_CREATED);
    }
}
