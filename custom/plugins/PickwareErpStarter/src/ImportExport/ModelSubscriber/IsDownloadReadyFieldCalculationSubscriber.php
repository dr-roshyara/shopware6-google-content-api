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

use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class IsDownloadReadyFieldCalculationSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            ImportExportDefinition::EVENT_LOADED => 'onImportExportLoaded',
        ];
    }

    public function onImportExportLoaded(EntityLoadedEvent $event): void
    {
        /** @var ImportExportEntity $importExport */
        foreach ($event->getEntities() as $importExport) {
            $importExport->assign([
                'isDownloadReady' => false,
            ]);

            if (!$importExport->getDocumentId()) {
                continue;
            }

            if ($importExport->getType() === ImportExportDefinition::TYPE_IMPORT || (
                    $importExport->getType() === ImportExportDefinition::TYPE_EXPORT
                    && $importExport->getState() === ImportExportDefinition::STATE_COMPLETED
                )) {
                $importExport->assign([
                    'isDownloadReady' => true,
                ]);
            }
        }
    }
}
