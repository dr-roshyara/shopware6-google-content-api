<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\PurchaseList\ModelExtension;

use Pickware\PickwareErpStarter\PurchaseList\Model\PurchaseListItemDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class PurchaseListProductExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new OneToManyAssociationField(
                'pickwareErpPurchaseListItems',
                PurchaseListItemDefinition::class,
                'product_id',
                'id',
            ))->addFlags(new CascadeDelete(false /* isCloneRelevant */)),
        );
    }

    public function getDefinitionClass(): string
    {
        return ProductDefinition::class;
    }
}
