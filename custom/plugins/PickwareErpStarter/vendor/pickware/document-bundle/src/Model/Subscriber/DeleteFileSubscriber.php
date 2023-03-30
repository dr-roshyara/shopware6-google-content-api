<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DocumentBundle\Model\Subscriber;

use League\Flysystem\FilesystemInterface;
use Pickware\DocumentBundle\Model\DocumentDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class DeleteFileSubscriber implements EventSubscriberInterface
{
    /**
     * @var FilesystemInterface
     */
    private $privateFileSystem;

    public function __construct(FilesystemInterface $privateFileSystem)
    {
        $this->privateFileSystem = $privateFileSystem;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            DocumentDefinition::ENTITY_DELETED_EVENT => 'onDocumentEntityDeleted',
            PreWriteValidationEvent::class => 'onPreWriteValidationEvent',
        ];
    }

    public function onPreWriteValidationEvent(PreWriteValidationEvent $event): void
    {
        foreach ($event->getCommands() as $command) {
            if ($command->getDefinition()->getEntityName() !== DocumentDefinition::ENTITY_NAME) {
                continue;
            }
            if (!$command instanceof DeleteCommand) {
                continue;
            }
            $command->requestChangeSet();
        }
    }

    public function onDocumentEntityDeleted(EntityDeletedEvent $event): void
    {
        foreach ($event->getWriteResults() as $writeResult) {
            $changeSet = $writeResult->getChangeSet();
            $filePath = $changeSet->getBefore('path_in_private_file_system');
            if ($filePath === null) {
                $filePath = 'documents/' . bin2hex($changeSet->getBefore('id'));
            }
            if ($this->privateFileSystem->has($filePath)) {
                $this->privateFileSystem->delete($filePath);
            }
        }
    }
}
