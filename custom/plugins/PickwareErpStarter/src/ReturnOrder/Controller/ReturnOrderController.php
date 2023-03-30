<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\ReturnOrder\Controller;

use Pickware\DalBundle\EntityResponseService;
use Pickware\PickwareErpStarter\ReturnOrder\ReturnOrderException;
use Pickware\PickwareErpStarter\ReturnOrder\ReturnOrderService;
use Pickware\ValidationBundle\Annotation\JsonValidation;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ReturnOrderController
{
    private ReturnOrderService $returnOrderService;
    private EntityResponseService $entityResponseService;

    public function __construct(
        ReturnOrderService $returnOrderService,
        EntityResponseService $entityResponseService
    ) {
        $this->returnOrderService = $returnOrderService;
        $this->entityResponseService = $entityResponseService;
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route(
     *     "/api/_action/pickware-erp/place-return-order",
     *     name="api.action.pickware-erp.place-return-order",
     *     methods={"POST"}
     * )
     * @JsonValidation(schemaFilePath="payload-place-return-order.schema.json")
     */
    public function placeReturnOrder(Context $context, Request $request): Response
    {
        $returnOrderPayload = $request->get('returnOrder');

        try {
            $this->returnOrderService->placeReturnOrder($returnOrderPayload, $context->getSource()->getUserId(), $context);
        } catch (ReturnOrderException $exception) {
            return $exception
                ->serializeToJsonApiError()
                ->setStatus(Response::HTTP_BAD_REQUEST)
                ->toJsonApiErrorResponse();
        }

        return new Response('', Response::HTTP_OK);
    }
}
