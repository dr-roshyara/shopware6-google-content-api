<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\ImportExport\ModelSubscriber;

use Pickware\DalBundle\EntityManager;
use Pickware\DocumentBundle\Model\DocumentDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class DeleteDocumentSubscriber implements EventSubscriberInterface
{
    /**
     * @var EntityManager
     */
    private $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ImportExportDefinition::EVENT_DELETED => 'onImportExportDeleted',
            PreWriteValidationEvent::class => 'onPreWriteValidationEvent',
        ];
    }

    public function onPreWriteValidationEvent(PreWriteValidationEvent $event): void
    {
        foreach ($event->getCommands() as $command) {
            if ($command->getDefinition()->getEntityName() !== ImportExportDefinition::ENTITY_NAME) {
                continue;
            }
            if (!$command instanceof DeleteCommand) {
                continue;
            }
            $command->requestChangeSet();
        }
    }

    public function onImportExportDeleted(EntityDeletedEvent $event): void
    {
        $documentIdsToRemove = [];
        foreach ($event->getWriteResults() as $writeResult) {
            $changeSet = $writeResult->getChangeSet();
            if ($changeSet->getBefore('document_id') !== null) {
                $documentIdsToRemove[] = bin2hex($changeSet->getBefore('document_id'));
            }
        }
        $this->entityManager->delete(DocumentDefinition::class, $documentIdsToRemove, $event->getContext());
    }
}
