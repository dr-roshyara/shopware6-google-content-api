<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\PurchaseList;

use Doctrine\DBAL\Connection;
use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\PurchaseList\Model\PurchaseListItemCollection;
use Pickware\PickwareErpStarter\PurchaseList\Model\PurchaseListItemDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class PurchaseListService
{
    private EntityManager $entityManager;
    private Connection $connection;

    public function __construct(
        EntityManager $entityManager,
        Connection $connection
    ) {
        $this->entityManager = $entityManager;
        $this->connection = $connection;
    }

    public function clearPurchaseList(): void
    {
        $this->connection->executeStatement('DELETE FROM `pickware_erp_purchase_list_item`');
    }

    public function hasPurchaseListItemsWithoutSupplier(): bool
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.pickwareErpProductSupplierConfiguration.supplierId', null));

        /** @var PurchaseListItemCollection $purchaseListItems */
        $purchaseListItems = $this->entityManager->findBy(
            PurchaseListItemDefinition::class,
            $criteria,
            Context::createDefaultContext(),
        );

        if ($purchaseListItems->count() === 0) {
            return false;
        }

        return true;
    }
}
