<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Warehouse\Model\Subscriber;

use Pickware\PickwareErpStarter\Config\Config;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseDefinition;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class WarehouseEntitySubscriber implements EventSubscriberInterface
{
    /**
     * @var Config
     */
    private $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WarehouseDefinition::EVENT_LOADED => 'onWarehouseLoaded',
        ];
    }

    public function onWarehouseLoaded(EntityLoadedEvent $event): void
    {
        /** @var WarehouseEntity $warehouse */
        foreach ($event->getEntities() as $warehouse) {
            if ($this->config->getDefaultWarehouseId() === $warehouse->getId()) {
                $warehouse->assign([
                    'isDefault' => true,
                ]);
            } else {
                $warehouse->assign([
                    'isDefault' => false,
                ]);
            }
        }
    }
}
