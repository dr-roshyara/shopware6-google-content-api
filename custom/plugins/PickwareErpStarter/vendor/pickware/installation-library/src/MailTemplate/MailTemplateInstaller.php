<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\InstallationLibrary\MailTemplate;

use Doctrine\DBAL\Connection;
use Exception;
use InvalidArgumentException;
use LogicException;
use Pickware\DalBundle\EntityManager;
use Pickware\InstallationLibrary\IdLookUpService;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeDefinition;
use Shopware\Core\Content\MailTemplate\MailTemplateActions;
use Shopware\Core\Content\MailTemplate\MailTemplateDefinition;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\CountAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\CountResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Event\EventAction\Aggregate\EventActionRule\EventActionRuleDefinition;
use Shopware\Core\Framework\Event\EventAction\EventActionDefinition;
use Shopware\Core\Framework\Event\EventAction\EventActionEntity;
use Shopware\Core\Framework\Uuid\Uuid;

class MailTemplateInstaller
{
    private ?Connection $db = null;
    private ?EntityManager $entityManager = null;
    private ?IdLookUpService $idLookUpService = null;

    public function __construct($argument, ?IdLookUpService $idLookUpService = null)
    {
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

    public function installMailTemplate(MailTemplate $mailTemplate, ?LoggerInterface $logger = null): void
    {
        // New code will have instantiated {@see MailTemplateInstaller} with an {@see EntityManager}. Use the new logic
        // in this case.
        if ($this->entityManager) {
            // To not break the previous API without the $logger argument, it is optional and we use a null object if
            // none is supplied (e.g., from old plugins that have not been adjusted to use the new API).
            $logger ??= new NullLogger();

            $this->installMailTemplateTypeWithEntityManager($mailTemplate, $logger);

            return;
        }

        // Old plugin versions may still call this {@see MailTemplateInstaller} without an {@see EntityManager}.
        // In this case, execute the old logic:

        $mailTemplateTypeId = $this->ensureMailTemplateType(
            $mailTemplate->getTechnicalName(),
            $mailTemplate->getTypeNameTranslations(),
            $mailTemplate->getAvailableTemplateVariables(),
        );
        $mailTemplateId = $this->ensureMailTemplate($mailTemplateTypeId);
        foreach ($mailTemplate->getTranslations() as $translation) {
            $this->ensureMailTemplateTranslation($mailTemplateId, $translation);
        }
        foreach ($mailTemplate->getActionEvents() as $actionEvent) {
            $this->ensureMailActionEvent($mailTemplateTypeId, $mailTemplateId, $actionEvent);
        }
    }

    private function installMailTemplateTypeWithEntityManager(MailTemplate $mailTemplate, LoggerInterface $logger): void
    {
        $this->entityManager->runInTransactionWithRetry(function () use ($mailTemplate, $logger): void {
            $mailTemplateTypeId = $this->ensureMailTemplateTypeWithEntityManager($mailTemplate);
            $mailTemplateId = $this->ensureMailTemplateWithEntityManager($mailTemplate, $mailTemplateTypeId);
            $this->ensureMailActionEventsWithEntityManager(
                $mailTemplate,
                $mailTemplateTypeId,
                $mailTemplateId,
                $logger,
            );
        });
    }

    private function ensureMailTemplateTypeWithEntityManager(MailTemplate $mailTemplate): string
    {
        /** @var MailTemplateEntity|null $existingMailTemplateType */
        $existingMailTemplateType = $this->entityManager->findOneBy(
            MailTemplateTypeDefinition::class,
            ['technicalName' => $mailTemplate->getTechnicalName()],
            Context::createDefaultContext(),
        );
        $id = $existingMailTemplateType ? $existingMailTemplateType->getId() : Uuid::randomHex();

        $this->entityManager->upsert(
            MailTemplateTypeDefinition::class,
            [
                [
                    'id' => $id,
                    'name' => $mailTemplate->getTypeNameTranslations(),
                    'technicalName' => $mailTemplate->getTechnicalName(),
                    'availableEntities' => $mailTemplate->getAvailableTemplateVariables(),
                ],
            ],
            Context::createDefaultContext(),
        );

        return $id;
    }

    private function ensureMailTemplateWithEntityManager(MailTemplate $mailTemplate, string $mailTemplateTypeId): string
    {
        // It is possible to add multiple mail templates for the same mail template type. Use the _oldest_ mail template
        // to ensure that it's the one we created upon installation.
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('mailTemplateTypeId', $mailTemplateTypeId))
            ->addSorting(new FieldSorting('createdAt', FieldSorting::ASCENDING))
            ->setLimit(1);

        $existingMailTemplate = $this->entityManager->findOneBy(
            MailTemplateDefinition::class,
            $criteria,
            Context::createDefaultContext(),
        );

        $id = $existingMailTemplate ? $existingMailTemplate->getId() : Uuid::randomHex();

        $this->entityManager->upsert(
            MailTemplateDefinition::class,
            [
                [
                    'id' => $id,
                    'mailTemplateTypeId' => $mailTemplateTypeId,
                    'translations' => $mailTemplate->getMailTranslations(),
                ],
            ],
            Context::createDefaultContext(),
        );

        return $id;
    }

    /**
     * @deprecated Will be removed with next major release
     * For each event name as {@see MailTemplate::getActionEvents specified by the given MailTemplate}, ensure there is
     * at least one {@see EventAction}, and carefully try to remove some duplicates, if any.
     *
     * @see self::findIdsForDuplicatedEventActionToDelete determines which duplicate EventAction entries to delete
     */
    private function ensureMailActionEventsWithEntityManager(
        MailTemplate $mailTemplate,
        string $mailTemplateTypeId,
        string $mailTemplateId,
        LoggerInterface $logger
    ): void {
        $deleteIds = [];
        $upsertPayloads = [];

        $context = Context::createDefaultContext();

        foreach (array_unique($mailTemplate->getActionEvents()) as $eventName) {
            $criteria = (new Criteria())
                ->addFilter(new EqualsFilter('eventName', $eventName))
                ->addFilter(new EqualsFilter('actionName', MailTemplateActions::MAIL_TEMPLATE_MAIL_SEND_ACTION))
                // We sort in a descending order because we need that later for
                // {@see self::findIdsForDuplicatedEventActionToDelete}'s algorithm.
                ->addSorting(new FieldSorting('createdAt', 'DESC'));

            /** @var EventActionEntity[] */
            $existingEventActionsFromNewestToOldest = $this->entityManager->findBy(
                EventActionDefinition::class,
                $criteria,
                $context,
            )->getElements();

            $existingEventActionCount = count($existingEventActionsFromNewestToOldest);

            // If there are none, we will create a new entry below, otherwise, we rewrite existing entries.
            $eventActions = $existingEventActionCount > 0 ? $existingEventActionsFromNewestToOldest : [null];

            // The ids of EventActions to be deleted because they were duplicated and that we assume may be safe to
            // delete.
            $deleteIds = array_unique(array_merge(
                $deleteIds,
                $this->findIdsForDuplicatedEventActionToDelete(
                    $existingEventActionsFromNewestToOldest,
                    $context,
                    $logger,
                ),
            ));

            // We only ever want one EventAction per event name.
            // However, while the expectation is that here, we should only ever find at most one EventAction for the
            // event name, this is nowhere enforced in the data model, and instances of unexplained duplicated and
            // broken entries have been found.
            // Hence, we acknowledge reality and try to update all EventActions, and delete ones we can reasonably
            // assume to have been created in error by us in the past, taking care not to break the user's system.
            foreach ($eventActions as $eventAction) {
                $id = $eventAction !== null ? $eventAction->getId() : Uuid::randomHex();

                if (in_array($id, $deleteIds)) {
                    // Do not update EventActions which will be deleted.
                    continue;
                }

                $upsertPayloads[] = [
                    'id' => $id,
                    'eventName' => $eventName,
                    'actionName' => MailTemplateActions::MAIL_TEMPLATE_MAIL_SEND_ACTION,
                    'config' => [
                        'mail_template_type_id' => $mailTemplateTypeId,
                        'mail_template_id' => $mailTemplateId,
                    ],
                ];
            }
        }

        $this->entityManager->upsert(EventActionDefinition::class, $upsertPayloads, $context);
        $this->entityManager->delete(EventActionDefinition::class, $deleteIds, $context);
    }

    /**
     * Returns a list of ids for EventActions deemed unnecessary, but safe-to-delete duplicates.
     *
     * All given EventActions are assumed to have the same event name, action name and are assumed to be sorted from
     * newest to oldest by their createdAt field.
     *
     * This only applies to the new API when using an {@see EntityManager}.
     *
     * @see {@link https://github.com/pickware/shopware-plugins/issues/2765}
     * @see {@link https://github.com/pickware/pickware-cloud/issues/5426}
     *
     * @param EventActionEntity[] $eventActionsSortedFromNewestToOldest
     * @return string[] an array of EventAction ids to delete
     */
    private function findIdsForDuplicatedEventActionToDelete(
        array $eventActionsSortedFromNewestToOldest,
        Context $context,
        LoggerInterface $logger
    ): array {
        $eventActionCount = count($eventActionsSortedFromNewestToOldest);

        if ($eventActionCount < 2) {
            // We will never delete the only existing EventAction.
            // This is the ideal case in which nothing has gone wrong in the past, and should almost always happen.
            return [];
        }

        $deletedIds = [];

        /** @var null|EventActionEntity */
        $previousEventAction = null;

        // We iterate from newest to oldest so that we can add a check favoring not deleting the oldest entry, which,
        // in all observed cases, was the one we wanted to remain.
        foreach ($eventActionsSortedFromNewestToOldest as $eventAction) {
            assert($previousEventAction ? $eventAction->getCreatedAt() <= $previousEventAction->getCreatedAt() : true);
            assert($previousEventAction ? $eventAction->getEventName() <= $previousEventAction->getEventName() : true);
            assert(
                $previousEventAction ? $eventAction->getActionName() <= $previousEventAction->getActionName() : true,
            );
            $previousEventAction = $eventAction;

            if (!$this->isEligibleToBeDeletedAsDuplicatedEventAction($eventAction, $context, $logger)) {
                // Either this EventAction seems ordinary and not like the observed duplicates, or we may have
                // determined we consider it too unsafe to delete it.
                continue;
            }

            if (count($deletedIds) + 1 >= $eventActionCount) {
                // We do not want to delete all EventActions, and will always not delete the last remaining one.
                // Given the sorting, the last remaining one not deleted, even in the never observed case it were
                // eligible to be deleted, the oldest EventAction will remain.
                $logger->error(
                    (
                        'Found unexpected EventAction for event name {eventName}'
                        . ', for which there exists more than one EventAction,'
                        . ' which seems to be invalid, but will not be deleted because it is the oldest and only '
                        . 'remaining EventAction entry.'
                    ),
                    [
                        'eventName' => $eventAction->getEventName(),
                        'eventAction' => $eventAction->jsonSerialize(),
                    ],
                );

                break;
            }

            $logger->error(
                (
                    'Found unexpected EventAction for event name {eventName}'
                    . ', for which there exists more than one EventAction,'
                    . ' which will therefore be deleted.'
                ),
                [
                    'eventName' => $eventAction->getEventName(),
                    'eventAction' => $eventAction->jsonSerialize(),
                ],
            );

            $deletedIds[] = $eventAction->getId();
        }

        return $deletedIds;
    }

    private function isEligibleToBeDeletedAsDuplicatedEventAction(
        EventActionEntity $eventAction,
        Context $context,
        LoggerInterface $logger
    ): bool {
        $config = $eventAction->getConfig();
        if ($config === null) {
            $logger->warning('Found unexpected EventAction for event name {eventName} without config.', [
                'eventName' => $eventAction->getEventName(),
                'eventAction' => $eventAction->jsonSerialize(),
            ]);

            // This does not match any known case we want to workaround.
            // In this case, there must be an entirely different problem which we shall not hide by deleting the entry.
            return false;
        }

        if (!array_key_exists('mail_template_type_id', $config)
            || !is_string($config['mail_template_type_id'])
        ) {
            $logger->warning(
                (
                    'Found unexpected EventAction for event name {eventName} without a mail_template_type_id string'
                    . ' in its config'
                ),
                [
                    'eventName' => $eventAction->getEventName(),
                    'eventAction' => $eventAction->jsonSerialize(),
                ],
            );

            // This does not match any known case we want to workaround.
            // In this case, there must be an entirely different problem which we shall not hide by deleting the entry.
            return false;
        }

        $mailTemplateTypeId = $config['mail_template_type_id'];

        if ($this->mailTemplateTypeExists($mailTemplateTypeId, $context)) {
            // All broken cases with duplicated entries had one entry where the referenced mail template type id did not
            // still point to an existing entry in the mail_template_type table.
            // Hence, if the MailTemplateType exists, assume this is not an entry we would want to delete.
            // In fact, this case is most likely to be hit for the correct EventAction that we want to keep.
            return false;
        }

        if ($this->eventActionRulesExistForEventActionId($eventAction->getId(), $context)) {
            $logger->error(
                (
                    'Found unexpected EventAction for event name {eventName}'
                    . ', for which there exists more than one EventAction,'
                    . ' which should therefore be deleted'
                    . ', but is not being deleted because it has associated EventActionRules.'
                ),
                [
                    'eventName' => $eventAction->getEventName(),
                    'eventAction' => $eventAction->jsonSerialize(),
                ],
            );

            // While the entry might be one we would want to delete, we do not because in the specific instance, someone
            // has taken care to create EventActionRules which reference this EventAction. We absolutely do not want to
            // break manually configured behavior.
            return false;
        }

        if ($eventAction->isActive()) {
            // In none of the observed cases, the broken entry was active.
            // In some cases, the correct entry was active, and in some it was not.
            $logger->error(
                (
                    'Found unexpected EventAction for event name {eventName}'
                    . ', for which there exists more than one EventAction,'
                    . ' which should therefore be deleted'
                    . ', but is not being deleted because it is active.'
                ),
                [
                    'eventName' => $eventAction->getEventName(),
                    'eventAction' => $eventAction->jsonSerialize(),
                ],
            );

            return false;
        }

        return true;
    }

    private function mailTemplateTypeExists(string $mailTemplateTypeId, Context $context): bool
    {
        return $this->entitiesExist(
            MailTemplateTypeDefinition::class,
            'id',
            $mailTemplateTypeId,
            $context,
        );
    }

    private function eventActionRulesExistForEventActionId(string $eventActionId, Context $context): bool
    {
        return $this->entitiesExist(
            EventActionRuleDefinition::class,
            'eventActionId',
            $eventActionId,
            $context,
        );
    }

    /**
     * @param class-string<EntityDefinition> $entityDefinitionClassName
     */
    private function entitiesExist(
        string $entityDefinitionClassName,
        string $entityFieldName,
        string $equalsValue,
        Context $context
    ): bool {
        $countCriteria = (new Criteria())
            ->addAggregation(new CountAggregation('count', $entityFieldName))
            ->addFilter(new EqualsFilter($entityFieldName, $equalsValue));
        $aggregationCollectionResult = $this->entityManager
            ->getRepository($entityDefinitionClassName)
            ->aggregate($countCriteria, $context);

        if ($aggregationCollectionResult->count() !== 1) {
            throw new LogicException(sprintf(
                'Expected a %1$s with exactly one entry, but found a %2$s with %3$s entries',
                AggregationResultCollection::class,
                get_class($aggregationCollectionResult),
                $aggregationCollectionResult->count(),
            ));
        }

        $countResult = $aggregationCollectionResult->first();

        if (!($countResult instanceof CountResult)) {
            throw new LogicException(sprintf('Expected a CountResult but was a %s', get_class($countResult)));
        }

        return $countResult->getCount() > 0;
    }

    /**
     * Ensures that a MailTemplateType exists for the given technical name.
     *
     * @param string $technicalName Matches mail_template_type.technical_name
     * @param array $nameTranslations Mail template type name for the locale codes de-DE and en-GB. E.g. [
     *   'de-DE' => 'Mein Mail Template',
     *   'en-GB' => 'My Mail Template',
     * ]
     * @return string id of the MailTemplateType
     * @deprecated tag:next-major Method will be marked private with next major release. Install your mail templates
     * with self::installMailTemplate(). Return type of UUID will change from BIN to HEX.
     */
    public function ensureMailTemplateType(
        string $technicalName,
        array $nameTranslations,
        array $availableEntities
    ): string {
        if (!array_key_exists('de-DE', $nameTranslations) || !array_key_exists('en-GB', $nameTranslations)) {
            throw new Exception('Mail template type translations must support locale codes \'de-DE\' and \'en-GB\'');
        }

        $this->db->executeStatement(
            'INSERT INTO `mail_template_type` (
                `id`,
                `technical_name`,
                `available_entities`,
                `created_at`
            ) VALUES (
                :id,
                :technicalName,
                :availableEntities,
                NOW()
            ) ON DUPLICATE KEY UPDATE `id` = `id`',
            [
                'id' => Uuid::randomBytes(),
                'technicalName' => $technicalName,
                'availableEntities' => json_encode($availableEntities),
            ],
        );
        /** @var string $mailTemplateTypeId */
        $mailTemplateTypeId = $this->db->fetchOne(
            'SELECT `id` FROM `mail_template_type` WHERE `technical_name` = :technicalName',
            [
                'technicalName' => $technicalName,
            ],
        );

        foreach ($nameTranslations as $localeCode => $translatedName) {
            $languageId = $this->idLookUpService->lookUpLanguageIdForLocaleCode($localeCode);
            if (!$languageId) {
                continue;
            }
            $this->db->executeStatement(
                'INSERT INTO `mail_template_type_translation` (
                    `mail_template_type_id`,
                    `language_id`,
                    `name`,
                    `created_at`
                ) VALUES (
                    :mailTemplateTypeId,
                    :languageId,
                    :translatedName,
                    NOW(3)
                ) ON DUPLICATE KEY UPDATE `mail_template_type_id` = `mail_template_type_id`',
                [
                    'mailTemplateTypeId' => $mailTemplateTypeId,
                    'languageId' => $languageId,
                    'translatedName' => $translatedName,
                ],
            );
        }

        return $mailTemplateTypeId;
    }

    /**
     * Ensures that a MailTemplate exists for the given MailTemplateType id.
     *
     * @return string id of the MailTemplate
     * @deprecated tag:next-major Method will be marked private with next major release. Install your mail templates
     * with self::installMailTemplate(). Accepted format for UUID parameters will change from BIN to HEX.  Return type
     * of UUID will change from BIN to HEX.
     */
    public function ensureMailTemplate(string $mailTemplateTypeId): string
    {
        $mailTemplateId = $this->getMailTemplateId($mailTemplateTypeId);

        if ($mailTemplateId) {
            return $mailTemplateId;
        }

        $this->db->executeStatement(
            'INSERT INTO `mail_template` (
                `id`,
                `mail_template_type_id`,
                `system_default`,
                `created_at`
            ) VALUES (
                :id,
                :mailTemplateTypeId,
                1,
                NOW()
            ) ON DUPLICATE KEY UPDATE `id` = `id`',
            [
                'id' => Uuid::randomBytes(),
                'mailTemplateTypeId' => $mailTemplateTypeId,
            ],
        );

        return $this->getMailTemplateId($mailTemplateTypeId);
    }

    /**
     * @return string|bool
     * @deprecated tag:next-major Method will be marked private with next major release. Install your mail templates
     * with self::installMailTemplate(). Return type of UUID will change from BIN to HEX.
     */
    private function getMailTemplateId(string $mailTemplateTypeId)
    {
        return $this->db->fetchOne(
            'SELECT `id` FROM `mail_template` WHERE `mail_template_type_id` = :mailTemplateTypeId',
            ['mailTemplateTypeId' => $mailTemplateTypeId],
        );
    }

    /**
     * Ensures that translations (content and mail properties) for the given MailTemplate exist.
     *
     * @deprecated tag:next-major Method will be marked private with next major release. Install your mail templates
     * with self::installMailTemplate(). Accepted format for UUID parameters will change from BIN to HEX.
     */
    public function ensureMailTemplateTranslation(
        string $mailTemplateId,
        MailTemplateTranslation $mailTemplateTranslation
    ): void {
        $this->db->executeStatement(
            'INSERT INTO `mail_template_translation` (
                `mail_template_id`,
                `language_id`,
                `sender_name`,
                `subject`,
                `description`,
                `content_html`,
                `content_plain`,
                `created_at`
            )
            SELECT
                :mailTemplateId,
                `language`.`id`,
                :senderName,
                :subject,
                :description,
                :content_html,
                :content_plain,
                NOW(3)
            FROM `language`
            INNER JOIN `locale` ON `language`.`locale_id` = `locale`.`id`
            WHERE `locale`.`code` = :localeCode
            ON DUPLICATE KEY UPDATE `mail_template_id` = `mail_template_id`',
            [
                'mailTemplateId' => $mailTemplateId,
                'senderName' => $mailTemplateTranslation->getSender(),
                'subject' => $mailTemplateTranslation->getSubject(),
                'description' => $mailTemplateTranslation->getDescription(),
                'content_html' => $mailTemplateTranslation->getContentHtml(),
                'content_plain' => $mailTemplateTranslation->getContentPlain(),
                'localeCode' => $mailTemplateTranslation->getLocaleCode(),
            ],
        );
    }

    /**
     * @deprecated tag:next-major Method will be marked private with next major release. Install your mail templates
     * with self::installMailTemplate(). Accepted format for UUID parameters will change from BIN to HEX.
     */
    public function ensureMailActionEvent(string $mailTemplateTypeId, string $mailTemplateId, string $eventName): void
    {
        $existingEventAction = $this->db->fetchAllAssociative(
            'SELECT * FROM `event_action`
            WHERE `event_name` = :eventName
            AND `action_name` = :actionName',
            [
                'eventName' => $eventName,
                'actionName' => MailTemplateActions::MAIL_TEMPLATE_MAIL_SEND_ACTION,
            ],
        );
        if ($existingEventAction) {
            return;
        }

        $this->db->executeStatement(
            'INSERT INTO `event_action` (
                `id`,
                `event_name`,
                `action_name`,
                `config`,
                `created_at`
            ) VALUES(
                :id,
                :eventName,
                :actionName,
                :config,
                NOW()
            )',
            [
                'id' => Uuid::randomBytes(),
                'eventName' => $eventName,
                'actionName' => MailTemplateActions::MAIL_TEMPLATE_MAIL_SEND_ACTION,
                'config' => json_encode([
                    'mail_template_type_id' => Uuid::fromBytesToHex($mailTemplateTypeId),
                    'mail_template_id' => Uuid::fromBytesToHex($mailTemplateId),
                ]),
            ],
        );
    }
}
