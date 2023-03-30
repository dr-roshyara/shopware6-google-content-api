<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Supplier;

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;

class ProductSupplierConfigurationIndexer extends EntityIndexer
{
    public const NAME = 'PickwareErp.ProductSupplierConfigurationIndexer';

    private ProductDefinition $productDefinition;
    private IteratorFactory $iteratorFactory;
    private ProductSupplierConfigurationInitializer $configurationInitializer;

    public function __construct(
        ProductDefinition $productDefinition,
        IteratorFactory $iteratorFactory,
        ProductSupplierConfigurationInitializer $configurationInitializer
    ) {
        $this->productDefinition = $productDefinition;
        $this->iteratorFactory = $iteratorFactory;
        $this->configurationInitializer = $configurationInitializer;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function iterate($offset): ?EntityIndexingMessage
    {
        $iterator = $this->iteratorFactory->createIterator($this->productDefinition, $offset);
        // Index 50 products per run
        $iterator->getQuery()->setMaxResults(50);

        $ids = $iterator->fetch();

        if (empty($ids)) {
            return null;
        }

        return new EntityIndexingMessage(array_values($ids), $iterator->getOffset());
    }

    public function update(EntityWrittenContainerEvent $event): ?EntityIndexingMessage
    {
        // Ensuring that a configuration exists when entities are updated is done via the
        // ProductSupplierConfigurationInitializer.

        return null;
    }

    public function handle(EntityIndexingMessage $message): void
    {
        $productIds = $message->getData();

        $productIds = array_unique(array_filter($productIds));
        if (empty($productIds)) {
            return;
        }

        $this->configurationInitializer->ensureProductSupplierConfigurationExistsForProducts($productIds);
    }
}
