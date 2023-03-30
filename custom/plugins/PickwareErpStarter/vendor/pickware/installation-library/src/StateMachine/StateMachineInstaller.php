<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\InstallationLibrary\StateMachine;

use Doctrine\DBAL\Connection;
use Exception;
use InvalidArgumentException;
use Pickware\DalBundle\EntityManager;
use Pickware\InstallationLibrary\IdLookUpService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineHistory\StateMachineHistoryDefinition;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateDefinition;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionDefinition;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionEntity;
use Shopware\Core\System\StateMachine\StateMachineDefinition;
use Shopware\Core\System\StateMachine\StateMachineEntity;

class StateMachineInstaller
{
    private ?Connection $db = null;
    private ?EntityManager $entityManager = null;
    private ?IdLookUpService $idLookUpService = null;
    private Context $context;

    public function __construct($argument, ?IdLookUpService $idLookUpService = null)
    {
        $this->context = Context::createDefaultContext();
        /**
         * @deprecated tag:next-major First constructor argument will be changed to only accept an EntityManager as
         * argument. Second argument will be removed
         */
        if ($argument instanceof Connection) {
            $this->db = $argument;
            if (!$idLookUpService) {
                throw new InvalidArgumentException(
                    'When first argument is of type %s, the second argument must be provided and be of type %s.',
                    Connection::class,
                    IdLookUpService::class,
                );
            }
            $this->idLookUpService = $idLookUpService;
        } elseif ($argument instanceof EntityManager) {
            $this->entityManager = $argument;
        } else {
            throw new InvalidArgumentException(
                'First constructor argument must be of type %s or %s.',
                Connection::class,
                EntityManager::class,
            );
        }
    }

    public function ensureStateMachine(StateMachine $stateMachine): void
    {
        if ($this->entityManager) {
            $this->ensureStateMachineWithEntityManager($stateMachine);

            return;
        }

        $stateMachineId = $this->upsertStateMachine(
            $stateMachine->getTechnicalName(),
            $stateMachine->getNameTranslations(),
        );
        $initialStateId = null;
        foreach ($stateMachine->getStates() as $state) {
            $stateId = $this->upsertStateMachineState(
                $stateMachineId,
                $state->getTechnicalName(),
                $state->getNameTranslations(),
            );
            if ($stateMachine->getInitialState()->getTechnicalName() === $state->getTechnicalName()) {
                $initialStateId = $stateId;
            }
        }
        $this->setInitialStateMachineState($stateMachineId, $initialStateId);
        foreach ($stateMachine->getStates() as $state) {
            foreach ($state->getTransitions() as $transitionName => $targetState) {
                $this->upsertStateTransition(
                    $stateMachine->getTechnicalName(),
                    $state->getTechnicalName(),
                    $targetState->getTechnicalName(),
                    $transitionName,
                );
            }
        }
    }

    public function removeStateMachine(StateMachine $stateMachine): void
    {
        // First remove all history entries because they restrict the deletion of the states via foreign key constraint
        $this->entityManager->deleteByCriteria(
            StateMachineHistoryDefinition::class,
            ['stateMachine.technicalName' => $stateMachine->getTechnicalName()],
            $this->context,
        );

        // Then remove all transitions because they also restrict the deletion of the states via foreign key constraint
        $this->entityManager->deleteByCriteria(
            StateMachineTransitionDefinition::class,
            ['stateMachine.technicalName' => $stateMachine->getTechnicalName()],
            $this->context,
        );

        $this->entityManager->deleteByCriteria(
            StateMachineDefinition::class,
            ['technicalName' => $stateMachine->getTechnicalName()],
            $this->context,
        );
    }

    private function ensureStateMachineWithEntityManager(StateMachine $stateMachine): void
    {
        /** @var StateMachineEntity|null $existingNumberRange */
        $existingStateMachine = $this->entityManager->findOneBy(
            StateMachineDefinition::class,
            ['technicalName' => $stateMachine->getTechnicalName()],
            $this->context,
        );
        $stateMachineId = $existingStateMachine ? $existingStateMachine->getId() : Uuid::randomHex();
        $this->entityManager->upsert(
            StateMachineDefinition::class,
            [
                [
                    'id' => $stateMachineId,
                    'technicalName' => $stateMachine->getTechnicalName(),
                    'name' => $stateMachine->getNameTranslations(),
                ],
            ],
            $this->context,
        );

        foreach ($stateMachine->getStates() as $state) {
            $stateMachineStateId = $this->ensureStateMachineStateWithEntityManager($state, $stateMachineId);

            // Also set initial state id if necessary
            if ($stateMachine->getInitialState()->getTechnicalName() === $state->getTechnicalName()) {
                $this->entityManager->upsert(
                    StateMachineDefinition::class,
                    [
                        [
                            'id' => $stateMachineId,
                            'initialStateId' => $stateMachineStateId,
                        ],
                    ],
                    $this->context,
                );
            }
        }

        // Install state transitions _after_ all states have been created
        $stateMachineStates = $this->entityManager->findBy(
            StateMachineStateDefinition::class,
            ['stateMachineId' => $stateMachineId],
            $this->context,
        )->getElements();
        $stateMachineStatesByTechnicalName = array_combine(
            array_map(fn (StateMachineStateEntity $state) => $state->getTechnicalName(), $stateMachineStates),
            $stateMachineStates,
        );

        foreach ($stateMachine->getStates() as $state) {
            foreach ($state->getTransitions() as $actionName => $targetState) {
                if (!array_key_exists($state->getTechnicalName(), $stateMachineStatesByTechnicalName) ||
                    !array_key_exists($targetState->getTechnicalName(), $stateMachineStatesByTechnicalName)
                ) {
                    throw new InvalidArgumentException(sprintf(
                        'Invalid state machine state transition definition. State transition from %s to %s not ' .
                        'possible because one of the state machine states does not exist.',
                        $state->getTechnicalName(),
                        $targetState->getTechnicalName(),
                    ));
                }

                $this->ensureStateMachineStateTransitionWithEntityManager(
                    $stateMachineId,
                    $stateMachineStatesByTechnicalName[$state->getTechnicalName()]->getId(),
                    $stateMachineStatesByTechnicalName[$targetState->getTechnicalName()]->getId(),
                    $actionName,
                );
            }
        }

        // Remove old states from DB
        /** @var StateMachineEntity $stateMachineInDb */
        $stateMachineInDb = $this->entityManager->getByPrimaryKey(
            StateMachineDefinition::class,
            $stateMachineId,
            $this->context,
            [
                'transitions.fromStateMachineState',
                'transitions.toStateMachineState',
            ],
        );
        $transitionIdsToBeDeleted = [];
        /** @var StateMachineTransitionEntity $transition */
        foreach ($stateMachineInDb->getTransitions() as $transition) {
            if (!$stateMachine->allowsTransitionFromStateToState(
                $transition->getFromStateMachineState()->getTechnicalName(),
                $transition->getToStateMachineState()->getTechnicalName(),
            )) {
                $transitionIdsToBeDeleted[] = $transition->getId();
            }
        }
        $this->entityManager->delete(
            StateMachineTransitionDefinition::class,
            $transitionIdsToBeDeleted,
            $this->context,
        );
    }

    private function ensureStateMachineStateWithEntityManager(StateMachineState $state, string $stateMachineId): string
    {
        $existingStateMachineState = $this->entityManager->findOneBy(
            StateMachineStateDefinition::class,
            [
                'stateMachineId' => $stateMachineId,
                'technicalName' => $state->getTechnicalName(),
            ],
            $this->context,
        );
        $stateMachineStateId = $existingStateMachineState ? $existingStateMachineState->getId() : Uuid::randomHex();
        $this->entityManager->upsert(
            StateMachineStateDefinition::class,
            [
                [
                    'id' => $stateMachineStateId,
                    'stateMachineId' => $stateMachineId,
                    'technicalName' => $state->getTechnicalName(),
                    'name' => $state->getNameTranslations(),
                ],
            ],
            $this->context,
        );

        return $stateMachineStateId;
    }

    private function ensureStateMachineStateTransitionWithEntityManager(
        string $stateMachineId,
        string $fromStateMachineStateId,
        string $toStateMachineStateId,
        string $actionName
    ): void {
        $existingTransition = $this->entityManager->findOneBy(
            StateMachineTransitionDefinition::class,
            [
                'stateMachineId' => $stateMachineId,
                'fromStateId' => $fromStateMachineStateId,
                'toStateId' => $toStateMachineStateId,
            ],
            $this->context,
        );
        $transitionId = $existingTransition ? $existingTransition->getId() : Uuid::randomHex();
        $this->entityManager->upsert(
            StateMachineTransitionDefinition::class,
            [
                [
                    'id' => $transitionId,
                    'stateMachineId' => $stateMachineId,
                    'fromStateId' => $fromStateMachineStateId,
                    'toStateId' => $toStateMachineStateId,
                    'actionName' => $actionName,
                ],
            ],
            $this->context,
        );
    }

    /**
     * Ensures that a given state machine exists.
     *
     * @param array $translations technical name translations for the locale codes de-DE and en-GB. Eg. [
     *      'de-DE' => 'MeineEntity Status',
     *      'en-GB' => 'CustomEntity State',
     *  ]
     * @return string id of the state machine
     * @deprecated tag:3.0.0 This method will be removed. Use self::ensureStateMachine() instead.
     */
    public function upsertStateMachine(string $technicalName, array $translations): string
    {
        if (!array_key_exists('de-DE', $translations) || !array_key_exists('en-GB', $translations)) {
            throw new Exception('State machine translations must support locale codes \'de-DE\' and \'en-GB\'');
        }

        $this->db->executeStatement(
            'INSERT INTO `state_machine` (
                `id`,
                `technical_name`,
                `created_at`
            ) VALUES (
                :id,
                :technicalName,
                NOW()
            ) ON DUPLICATE KEY UPDATE `technical_name` = `technical_name`',
            [
                'id' => Uuid::randomBytes(),
                'technicalName' => $technicalName,
            ],
        );
        /** @var string $stateMachineId */
        $stateMachineId = $this->db->fetchOne(
            'SELECT `id` FROM `state_machine` WHERE `technical_name` = :technicalName',
            [
                'technicalName' => $technicalName,
            ],
        );

        foreach ($translations as $localeCode => $translatedName) {
            $languageId = $this->idLookUpService->lookUpLanguageIdForLocaleCode($localeCode);
            if (!$languageId) {
                continue;
            }

            $this->db->executeStatement(
                'INSERT INTO `state_machine_translation` (
                    `language_id`,
                    `state_machine_id`,
                    `name`,
                    `created_at`
                ) VALUES (
                    :languageId,
                    :stateMachineId,
                    :translatedName,
                    NOW(3)
                ) ON DUPLICATE KEY UPDATE `state_machine_id` = `state_machine_id`',
                [
                    'languageId' => $languageId,
                    'stateMachineId' => $stateMachineId,
                    'translatedName' => $translatedName,
                ],
            );
        }

        return $stateMachineId;
    }

    /**
     * Ensures that the given state machine state exists.
     *
     * @param array $translations technical name translations for the locale codes de-DE and en-GB. Eg. [
     *      'de-DE' => 'Konkreter Status',
     *      'en-GB' => 'Specific State',
     *  ]
     * @return string id of the state machine state
     * @deprecated tag:3.0.0 This method will be removed. Use self::ensureStateMachine() instead.
     */
    public function upsertStateMachineState(string $stateMachineId, string $technicalName, array $translations): string
    {
        if (!array_key_exists('de-DE', $translations) || !array_key_exists('en-GB', $translations)) {
            throw new Exception('State machine state translations must support locale codes \'de-DE\' and \'en-GB\'');
        }

        $this->db->executeStatement(
            'INSERT INTO `state_machine_state` (
                `id`,
                `technical_name`,
                `state_machine_id`,
                `created_at`
            ) VALUES (
                :id,
                :technicalName,
                :stateMachineId,
                NOW()
            ) ON DUPLICATE KEY UPDATE `technical_name` = `technical_name`',
            [
                'id' => Uuid::randomBytes(),
                'technicalName' => $technicalName,
                'stateMachineId' => $stateMachineId,
            ],
        );
        /** @var string $stateMachineStateId */
        $stateMachineStateId = $this->db->fetchOne(
            'SELECT `id`
             FROM `state_machine_state`
             WHERE `technical_name` = :technicalName
             AND `state_machine_id` = :stateMachineId',
            [
                'technicalName' => $technicalName,
                'stateMachineId' => $stateMachineId,
            ],
        );

        foreach ($translations as $localeCode => $translatedName) {
            $languageId = $this->idLookUpService->lookUpLanguageIdForLocaleCode($localeCode);
            if (!$languageId) {
                continue;
            }

            $this->db->executeStatement(
                'INSERT INTO `state_machine_state_translation` (
                    `language_id`,
                    `state_machine_state_id`,
                    `name`,
                    `created_at`
                ) VALUES (
                    :languageId,
                    :stateMachineStateId,
                    :translatedName,
                    NOW(3)
                ) ON DUPLICATE KEY UPDATE `state_machine_state_id` = `state_machine_state_id`',
                [
                    'languageId' => $languageId,
                    'stateMachineStateId' => $stateMachineStateId,
                    'translatedName' => $translatedName,
                ],
            );
        }

        return $stateMachineStateId;
    }

    /**
     * @deprecated tag:3.0.0 This method will be removed. Use self::ensureStateMachine() instead.
     */
    public function upsertStateTransition(
        string $stateMachineTechnicalName,
        string $fromStateMachineStateTechnicalName,
        string $toStateMachineStateTechnicalName,
        string $actionName
    ): void {
        $this->db->executeStatement(
            'INSERT INTO `state_machine_transition` (
                `id`,
                `action_name`,
                `state_machine_id`,
                `from_state_id`,
                `to_state_id`,
                `created_at`
            )
            SELECT
                :id AS `id`,
                :actionName AS `action_name`,
                `state_machine`.`id` AS `state_machine_id`,
                `fromState`.`id` AS `from_state_id`,
                `toState`.`id` AS `to_state_id`,
                NOW(3) AS `created_at`
            FROM `state_machine`
            LEFT JOIN `state_machine_state` `fromState`
                ON `fromState`.`technical_name` = :fromStateMachineStateTechnicalName
                AND `fromState`.`state_machine_id` = `state_machine`.`id`
            LEFT JOIN `state_machine_state` `toState`
                ON `toState`.`technical_name` = :toStateMachineStateTechnicalName
                AND `toState`.`state_machine_id` = `state_machine`.`id`
            WHERE `state_machine`.`technical_name` = :stateMachineTechnicalName
            ON DUPLICATE KEY UPDATE `action_name` = `action_name`',
            [
                'id' => Uuid::randomBytes(),
                'stateMachineTechnicalName' => $stateMachineTechnicalName,
                'fromStateMachineStateTechnicalName' => $fromStateMachineStateTechnicalName,
                'toStateMachineStateTechnicalName' => $toStateMachineStateTechnicalName,
                'actionName' => $actionName,
            ],
        );
    }

    /**
     * @deprecated tag:3.0.0 This method will be removed. Use self::ensureStateMachine() instead.
     */
    public function setInitialStateMachineState(string $stateMachineId, string $stateMachineStateId): void
    {
        $this->db->executeStatement(
            'UPDATE `state_machine` SET `initial_state_id` = :stateMachineStateId WHERE `id` = :stateMachineId',
            [
                'stateMachineId' => $stateMachineId,
                'stateMachineStateId' => $stateMachineStateId,
            ],
        );
    }

    /**
     * Ensures that state machine state transitions TO the given state machine state exist from ALL POSSIBLE state
     * machine states of this state machine. All transitions will have the same action name.
     *
     * @deprecated tag:3.0.0 This method will be removed. Use
     * StateMachine::addAllowedTransitionFromAllStatesToState() instead.
     */
    public function upsertStateTransitionsFromAllPossibleStates(
        string $stateMachineTechnicalName,
        string $toStateMachineStateTechnicalName,
        string $actionNameForAllTransitions
    ): void {
        $fromStateMachineStateTechnicalNames = $this->db->fetchFirstColumn(
            'SELECT `state_machine_state`.`technical_name`
             FROM `state_machine_state`
             LEFT JOIN `state_machine` ON `state_machine`.`id` = `state_machine_state`.`state_machine_id`
             WHERE `state_machine_state`.`technical_name` <> :toStateMachineStateTechnicalName
             AND `state_machine`.`technical_name` = :stateMachineTechnicalName',
            [
                'toStateMachineStateTechnicalName' => $toStateMachineStateTechnicalName,
                'stateMachineTechnicalName' => $stateMachineTechnicalName,
            ],
        );

        foreach ($fromStateMachineStateTechnicalNames as $fromStateMachineStateTechnicalName) {
            $this->ensureStateMachineStateTransitionWithEntityManager(
                $stateMachineTechnicalName,
                $fromStateMachineStateTechnicalName,
                $toStateMachineStateTechnicalName,
                $actionNameForAllTransitions,
            );
        }
    }
}
