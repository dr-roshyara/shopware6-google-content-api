<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DalBundle;

use RuntimeException;
use Shopware\Core\Framework\Api\Response\ResponseFactoryRegistry;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

class EntityResponseService
{
    private CriteriaFactory $criteriaFactory;
    private ResponseFactoryRegistry $responseFactoryRegistry;
    private EntityManager $entityManager;
    private RequestStack $requestStack;

    public function __construct(
        CriteriaFactory $criteriaFactory,
        ResponseFactoryRegistry $responseFactoryRegistry,
        EntityManager $entityManager,
        RequestStack $requestStack
    ) {
        $this->criteriaFactory = $criteriaFactory;
        $this->responseFactoryRegistry = $responseFactoryRegistry;
        $this->entityManager = $entityManager;
        $this->requestStack = $requestStack;
    }

    /**
     * @param class-string<EntityDefinition> $entityDefinitionClass
     * @param string $entityPrimaryKey Usually the ID or the technical name of the entity to respond
     * @param Request|null $request The request to create the response for. If null the current request from the Symfony
     *     RequestStack is used
     * @param string[]|null $associations The associations to load for the entity. If null the parameter "associations"
     *     from the current request of the Symfony RequestStack is used
     */
    public function makeEntityDetailResponse(
        string $entityDefinitionClass,
        string $entityPrimaryKey,
        Context $context,
        ?Request $request = null,
        ?array $associations = null
    ): Response {
        $request = $request ?: $this->requestStack->getCurrentRequest();
        if ($request === null) {
            throw new RuntimeException(
                'No request found in Symfony RequestStack, please provide a Request in the arguments of this function.',
            );
        }
        $associations = $associations ?? $request->request->all()['associations'] ?? null;
        if ($associations === null) {
            throw new RuntimeException(
                'Neither the parameter $associations was passed to the method ' . __METHOD__ . ' nor does the ' .
                'request contain a parameter named "associations".',
            );
        }

        $criteria = $this->criteriaFactory->makeCriteriaForEntitiesIdentifiedByIdWithAssociations(
            $entityDefinitionClass,
            [$entityPrimaryKey],
            $associations,
        );

        return $this->responseFactoryRegistry
            ->getType($request)
            ->createDetailResponse(
                $criteria,
                $this->entityManager->getOneBy($entityDefinitionClass, $criteria, $context),
                $this->entityManager->getEntityDefinition($entityDefinitionClass),
                $request,
                $context,
            );
    }

    /**
     * @param class-string<EntityDefinition> $entityDefinitionClass
     * @param string[] $entityPrimaryKeys
     * @param Request|null $request The request to create the response for. If null the current request from the Symfony
     *     RequestStack is used
     * @param string[]|null $associations The associations to load for the entities. If null the parameter
     *      "associations" from the current request of the Symfony RequestStack is used
     */
    public function makeEntityListingResponse(
        string $entityDefinitionClass,
        array $entityPrimaryKeys,
        Context $context,
        ?Request $request = null,
        ?array $associations = null
    ): Response {
        $request = $request ?: $this->requestStack->getCurrentRequest();
        if ($request === null) {
            throw new RuntimeException(
                'No request found in Symfony RequestStack, please provide a Request in the arguments of this function.',
            );
        }
        $associations = $associations ?? $request->request->all()['associations'] ?? null;
        if ($associations === null) {
            throw new RuntimeException(
                'Neither the parameter $associations was passed to the method ' . __METHOD__ . ' nor does the ' .
                'request contain a parameter named "associations".',
            );
        }

        $criteria = $this->criteriaFactory->makeCriteriaForEntitiesIdentifiedByIdWithAssociations(
            $entityDefinitionClass,
            $entityPrimaryKeys,
            $associations,
        );
        $repository = $this->entityManager->getRepository($entityDefinitionClass);

        return $this->responseFactoryRegistry
            ->getType($request)
            ->createListingResponse(
                $criteria,
                $repository->search($criteria, $context),
                $this->entityManager->getEntityDefinition($entityDefinitionClass),
                $request,
                $context,
            );
    }
}
