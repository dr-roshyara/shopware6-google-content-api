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

use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Rule\RuleDefinition;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateDefinition;
use Shopware\Core\System\Country\CountryDefinition;
use Shopware\Core\System\Currency\CurrencyDefinition;
use Shopware\Core\System\Locale\LocaleDefinition;
use Shopware\Core\System\Salutation\SalutationDefinition;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateDefinition;
use Shopware\Core\System\StateMachine\StateMachineDefinition;

/**
 * Service to resolve system-specific IDs for entities with unique identifiers that are the same on every system.
 * Example: Order State, Country, ...
 *
 * Do not refactor this service to return entities instead of IDs. We don't want entities to be returned by services and
 * also for returning entities, a Context would be required. This service's methods should not have a context parameter.
 */
class EntityIdResolver
{
    public const DEFAULT_RULE_NAME = 'Always valid (Default)';

    private EntityManager $entityManager;
    private Context $context;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
        $this->context = Context::createDefaultContext();
    }

    /**
     * The country iso code is not unique among the countries. Select the oldest country that matches instead.
     */
    public function resolveIdForCountry(string $isoCountryCode): string
    {
        $criteria = EntityManager::createCriteriaFromArray(['iso' => $isoCountryCode]);
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::ASCENDING));

        return $this->entityManager->getFirstBy(
            CountryDefinition::class,
            $criteria,
            Context::createDefaultContext(),
        )->getId();
    }

    public function resolveIdForOrderState(string $technicalName): string
    {
        return $this->resolveIdForStateMachineState(OrderStates::STATE_MACHINE, $technicalName);
    }

    public function resolveIdForOrderDeliveryState(string $technicalName): string
    {
        return $this->resolveIdForStateMachineState(OrderDeliveryStates::STATE_MACHINE, $technicalName);
    }

    public function resolveIdForOrderTransactionState(string $technicalName): string
    {
        return $this->resolveIdForStateMachineState(OrderTransactionStates::STATE_MACHINE, $technicalName);
    }

    public function resolveIdForStateMachineState(string $stateMachineTechnicalName, string $stateTechnicalName): string
    {
        return $this->entityManager->getOneBy(
            StateMachineStateDefinition::class,
            [
                'stateMachine.technicalName' => $stateMachineTechnicalName,
                'technicalName' => $stateTechnicalName,
            ],
            $this->context,
        )->getId();
    }

    public function resolveIdForDocumentType(string $technicalName): string
    {
        return $this->entityManager->getOneBy(DocumentTypeDefinition::class, [
            'technicalName' => $technicalName,
        ], $this->context)->getId();
    }

    /**
     * The country state short code is not unique among the country states. Select the oldest country state that matches
     * instead.
     */
    public function resolveIdForCountryState(string $isoCountryStateCode): string
    {
        $criteria = EntityManager::createCriteriaFromArray(['shortCode' => $isoCountryStateCode]);
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::ASCENDING));

        return $this->entityManager->getFirstBy(
            CountryStateDefinition::class,
            $criteria,
            Context::createDefaultContext(),
        )->getId();
    }

    public function resolveIdForSalutation(string $salutationKey): string
    {
        return $this->entityManager->getOneBy(
            SalutationDefinition::class,
            ['salutationKey' => $salutationKey],
            Context::createDefaultContext(),
        )->getId();
    }

    public function resolveIdForCurrency(string $isoCurrencyCode): string
    {
        return $this->entityManager->getOneBy(
            CurrencyDefinition::class,
            ['isoCode' => $isoCurrencyCode],
            Context::createDefaultContext(),
        )->getId();
    }

    public function resolveIdForLocale(string $code): string
    {
        return $this->entityManager->getOneBy(
            LocaleDefinition::class,
            ['code' => $code],
            Context::createDefaultContext(),
        )->getId();
    }

    /**
     * There is no single root category in Shopware. We select "a" root category that is the oldest instead.
     */
    public function getRootCategoryId(): string
    {
        $criteria = EntityManager::createCriteriaFromArray(['parentId' => null]);
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::ASCENDING));

        return $this->entityManager->getFirstBy(
            CategoryDefinition::class,
            $criteria,
            Context::createDefaultContext(),
        )->getId();
    }

    /**
     * Returns the ID of the (a) rule named 'Always valid (Default)'.
     *
     * It is not guaranteed that this rule exists and also not guaranteed that there is only one rule with this name.
     */
    public function getDefaultRuleId(): ?string
    {
        /** @var null|RuleEntity $defaultRule */
        $defaultRule = $this->entityManager->findFirstBy(
            RuleDefinition::class,
            new FieldSorting('createdAt', FieldSorting::ASCENDING),
            $this->context,
            ['name' => self::DEFAULT_RULE_NAME],
        );

        return $defaultRule ? $defaultRule->getId() : null;
    }

    public function resolveIdForStateMachine(string $technicalName): string
    {
        return $this->entityManager
            ->getOneBy(
                StateMachineDefinition::class,
                [
                    'technicalName' => $technicalName,
                ],
                $this->context,
            )
            ->getId();
    }
}
