<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stocktaking\Controller;

use Pickware\DalBundle\EntityManager;
use Pickware\DalBundle\EntityResponseService;
use Pickware\PickwareErpStarter\Stocktaking\Model\StocktakeCountingProcessDefinition;
use Pickware\PickwareErpStarter\Stocktaking\StocktakingException;
use Pickware\PickwareErpStarter\Stocktaking\StocktakingService;
use Pickware\ValidationBundle\Annotation\JsonValidation;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class StocktakingController
{
    private EntityManager $entityManager;
    private StocktakingService $stocktakingService;
    private EntityResponseService $entityResponseService;

    public function __construct(
        EntityManager $entityManager,
        StocktakingService $stocktakingService,
        EntityResponseService $entityResponseService
    ) {
        $this->entityManager = $entityManager;
        $this->stocktakingService = $stocktakingService;
        $this->entityResponseService = $entityResponseService;
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route(
     *     "/api/_action/pickware-erp/stocktaking/create-counting-processes",
     *     methods={"PUT"},
     *     name="api.action.pickware-erp.stocktaking.create-counting-processes"
     * )
     * @JsonValidation(schemaFilePath="create-counting-processes-payload.schema.json")
     */
    public function createCountingProcesses(Request $request, Context $context): Response
    {
        $payload = $request->request->all();
        foreach ($payload['countingProcesses'] as &$countingProcessPayload) {
            $countingProcessPayload['userId'] = $context->getSource()->getUserId();
        }

        try {
            $countingProcessIds = $this->entityManager->runInTransactionWithRetry(
                function () use ($payload, $context) {
                    // Before the counting process creation, delete any existing counting process for the same bin location
                    // iff the 'overwrite' flag was set.
                    if (($payload['overwrite'] ?? false) === true) {
                        $countingProcessFilter = new OrFilter();
                        foreach ($payload['countingProcesses'] as $countingProcessPayload) {
                            if (isset($countingProcessPayload['binLocationId'])) {
                                $countingProcessFilter->addQuery(
                                    new AndFilter([
                                        new EqualsFilter('stocktakeId', $countingProcessPayload['stocktakeId']),
                                        new EqualsFilter('binLocationId', $countingProcessPayload['binLocationId']),
                                    ]),
                                );
                            }
                        }

                        if (count($countingProcessFilter->getQueries()) > 0) {
                            $this->entityManager->deleteByCriteria(
                                StocktakeCountingProcessDefinition::class,
                                (new Criteria())->addFilter($countingProcessFilter),
                                $context,
                            );
                        }
                    }

                    return $this->stocktakingService->createCountingProcesses(
                        $payload['countingProcesses'],
                        $context,
                    );
                },
            );
        } catch (StocktakingException $e) {
            if ($e->getCode() === StocktakingException::EXCEPTION_CODE_STOCKTAKE_NOT_ACTIVE
                || $e->getCode() === StocktakingException::EXCEPTION_CODE_AT_LEAST_ONE_BIN_LOCATION_ALREADY_COUNTED
            ) {
                return $e->serializeToJsonApiError()->setStatus(Response::HTTP_PRECONDITION_FAILED)->toJsonApiErrorResponse();
            }

            throw $e;
        }

        return $this->entityResponseService->makeEntityListingResponse(
            StocktakeCountingProcessDefinition::class,
            $countingProcessIds,
            $context,
        );
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route(
     *     "/api/_action/pickware-erp/stocktaking/complete-stocktake",
     *     methods={"PUT"},
     *     name="api.action.pickware-erp.stocktaking.complete-stocktake"
     * )
     * @JsonValidation(schemaFilePath="complete-stocktake-payload.schema.json")
     */
    public function completeStocktake(Request $request, Context $context): Response
    {
        $payload = $request->request->all();
        try {
            $this->stocktakingService->completeStocktake($payload['stocktakeId'], $context->getSource()->getUserId(), $context);
        } catch (StocktakingException $e) {
            if ($e->getCode() === StocktakingException::EXCEPTION_CODE_STOCKTAKE_ALREADY_COMPLETED) {
                return $e->serializeToJsonApiError()->setStatus(Response::HTTP_PRECONDITION_FAILED)->toJsonApiErrorResponse();
            }

            throw $e;
        }

        return new Response('', Response::HTTP_OK);
    }
}
