<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\SupplierOrder\Model\Subscriber;

use DateInterval;
use DateTime;
use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\SupplierOrder\Model\SupplierOrderDefinition;
use Pickware\PickwareErpStarter\SupplierOrder\Model\SupplierOrderEntity;
use Pickware\PickwareErpStarter\SupplierOrder\SupplierOrderStateMachine;
use Shopware\Core\System\StateMachine\Event\StateMachineTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class DeliveryDateUpdateSubscriber implements EventSubscriberInterface
{
    public EntityManager $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public static function getSubscribedEvents(): array
    {
        return [StateMachineTransitionEvent::class => 'setDeliveryDateForSupplierOrdersOnStateChange'];
    }

    public function setDeliveryDateForSupplierOrdersOnStateChange(StateMachineTransitionEvent $event): void
    {
        if ($event->getEntityName() !== SupplierOrderDefinition::ENTITY_NAME
            || $event->getToPlace()->getTechnicalName() !== SupplierOrderStateMachine::STATE_CONFIRMED
        ) {
            return;
        }

        /** @var SupplierOrderEntity $supplierOrder */
        $supplierOrder = $this->entityManager->getByPrimaryKey(
            SupplierOrderDefinition::class,
            $event->getEntityId(),
            $event->getContext(),
            ['supplier'],
        );

        $defaultDeliveryTime = $supplierOrder->getSupplier()->getDefaultDeliveryTime();
        // Do not overwrite the delivery date if any is set
        if (!$defaultDeliveryTime || $supplierOrder->getDeliveryDate()) {
            return;
        }

        $this->entityManager->update(
            SupplierOrderDefinition::class,
            [
                [
                    'id' => $event->getEntityId(),
                    'deliveryDate' => (new DateTime('today'))->add(new DateInterval(sprintf('P%sD', $defaultDeliveryTime))),
                ],
            ],
            $event->getContext(),
        );
    }
}
