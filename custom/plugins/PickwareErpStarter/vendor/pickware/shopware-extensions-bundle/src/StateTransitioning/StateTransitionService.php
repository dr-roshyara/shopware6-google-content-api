<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShopwareExtensionsBundle\StateTransitioning;

use Pickware\DalBundle\EntityManager;
use Pickware\ShopwareExtensionsBundle\StateTransitioning\ShortestPathCalculation\ShortestPathCalculator;
use Pickware\ShopwareExtensionsBundle\StateTransitioning\ShortestPathCalculation\WeightedEdge;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateDefinition;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionCollection;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionDefinition;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionEntity;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;

class StateTransitionService
{
    private EntityManager $entityManager;
    private StateMachineRegistry $stateMachineRegistry;
    private ShortestPathCalculator $shortestPathCalculator;

    public function __construct(
        EntityManager $entityManager,
        StateMachineRegistry $stateMachineRegistry,
        ShortestPathCalculator $shortestPathCalculator
    ) {
        $this->entityManager = $entityManager;
        $this->stateMachineRegistry = $stateMachineRegistry;
        $this->shortestPathCalculator = $shortestPathCalculator;
    }

    /**
     * @param string $entityName (e.g. 'order'. NOT the entity class name)
     */
    public function transitionState(
        string $entityName,
        string $entityId,
        string $transitionActionName,
        string $stateIdFieldName,
        Context $context
    ): void {
        $this->stateMachineRegistry->transition(
            new Transition($entityName, $entityId, $transitionActionName, $stateIdFieldName),
            $context,
        );
    }

    /**
     * Ensures an order is in the desired state by creating state transitions that result in the desired state if
     * such a combination of transitions exists.
     */
    public function ensureOrderState(
        string $orderId,
        string $desiredStateTechnicalName,
        Context $context
    ): void {
        $this->ensureState(
            $orderId,
            OrderDefinition::class,
            OrderDefinition::ENTITY_NAME,
            $desiredStateTechnicalName,
            $context,
        );
    }

    /**
     * Ensures an order delivery is in the desired state by creating state transitions that result in the desired state
     * if such a combination of transitions exists.
     */
    public function ensureOrderDeliveryState(
        string $orderDeliveryId,
        string $desiredStateTechnicalName,
        Context $context
    ): void {
        $this->ensureState(
            $orderDeliveryId,
            OrderDeliveryDefinition::class,
            OrderDeliveryDefinition::ENTITY_NAME,
            $desiredStateTechnicalName,
            $context,
        );
    }

    /**
     * Ensures an order transaction is in the desired state by creating state transitions that result in the desired
     * state if such a combination of transitions exists.
     */
    public function ensureOrderTransactionState(
        string $orderTransactionId,
        string $desiredStateTechnicalName,
        Context $context
    ): void {
        $this->ensureState(
            $orderTransactionId,
            OrderTransactionDefinition::class,
            OrderTransactionDefinition::ENTITY_NAME,
            $desiredStateTechnicalName,
            $context,
        );
    }

    /**
     * Helper function to reduce code duplication. Therefore, it works only for order state, order delivery state, order
     * transaction state. This is because we assume association names, getter functions and property names.
     */
    private function ensureState(
        string $entityId,
        string $classDefinitionName,
        string $entityName,
        string $desiredStateTechnicalName,
        Context $context
    ): void {
        $entity = $this->entityManager->getByPrimaryKey(
            $classDefinitionName,
            $entityId,
            $context,
            ['stateMachineState'],
        );

        $actions = $this->getFewestTransitionActionsFromStateToState(
            $entity->getStateMachineState(),
            $desiredStateTechnicalName,
            $context,
        );

        if ($actions === null) {
            // Should not be reached because in the default state machine that we are using (order state, order delivery
            // state, order transaction state) all transitions are possible (at least transitively)
            throw StateTransitionException::noTransitionPathToDestinationStateFound(
                $entity->getStateMachineState()->getTechnicalName(),
                $desiredStateTechnicalName,
                $classDefinitionName,
                $entityId,
            );
        }

        foreach ($actions as $action) {
            $this->stateMachineRegistry->transition(
                new Transition($entityName, $entityId, $action, 'stateId'),
                $context,
            );
        }
    }

    private function getFewestTransitionActionsFromStateToState(
        StateMachineStateEntity $fromState,
        string $toStateTechnicalName,
        Context $context
    ): ?array {
        /** @var StateMachineTransitionCollection $transitions */
        $transitions = $this->entityManager->findBy(
            StateMachineTransitionDefinition::class,
            ['stateMachineId' => $fromState->getStateMachineId()],
            $context,
        );
        /** @var StateMachineStateEntity $destinationState */
        $destinationState = $this->entityManager->getOneBy(
            StateMachineStateDefinition::class,
            [
                'technicalName' => $toStateTechnicalName,
                'stateMachineId' => $fromState->getStateMachineId(),
            ],
            $context,
        );

        if ($destinationState->getTechnicalName() === $fromState->getTechnicalName()) {
            // Start state is destination state, no actions needed.
            return [];
        }

        $edges = array_map(
            fn (StateMachineTransitionEntity $transition) => new WeightedEdge(
                $transition->getActionName(),
                $transition->getFromStateId(),
                $transition->getToStateId(),
                1,
            ),
            $transitions->getElements(),
        );

        $path = $this->shortestPathCalculator->calculateShortestPath(
            $edges,
            $fromState->getId(),
            $destinationState->getId(),
        );

        return $path ? array_map(fn (WeightedEdge $edge) => $edge->id, $path) : null;
    }
}
