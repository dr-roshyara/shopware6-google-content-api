<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShopwareExtensionsBundle\OrderConfiguration;

use Pickware\DalBundle\EntityManager;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;

class OrderConfigurationIndexer extends EntityIndexer
{
    public const NAME = 'PickwareExtensionsBundle.OrderConfigurationIndexer';

    private EntityManager $entityManager;
    private IteratorFactory $iteratorFactory;
    private OrderConfigurationUpdater $orderConfigurationUpdater;

    public function __construct(
        EntityManager $entityManager,
        IteratorFactory $iteratorFactory,
        OrderConfigurationUpdater $orderConfigurationUpdater
    ) {
        $this->entityManager = $entityManager;
        $this->iteratorFactory = $iteratorFactory;
        $this->orderConfigurationUpdater = $orderConfigurationUpdater;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function iterate($offset): ?EntityIndexingMessage
    {
        $iterator = $this->iteratorFactory->createIterator(
            $this->entityManager->getEntityDefinition(OrderDefinition::class),
            $offset,
        );
        // Index 50 orders per run
        $iterator->getQuery()->setMaxResults(50);
        $ids = $iterator->fetch();

        if (empty($ids)) {
            return null;
        }

        return new EntityIndexingMessage(array_values($ids), $iterator->getOffset());
    }

    public function update(EntityWrittenContainerEvent $event): ?EntityIndexingMessage
    {
        // Keeping the order configuration in sync is done by synchronous subscribers. See OrderConfigurationUpdater
        return null;
    }

    public function handle(EntityIndexingMessage $message): void
    {
        $orderIds = $message->getData();

        $orderIds = array_unique(array_filter($orderIds));
        if (empty($orderIds)) {
            return;
        }

        $this->orderConfigurationUpdater->updateOrderConfigurations($orderIds, $message->getContext());
    }
}
