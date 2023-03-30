<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stock\ModelExtension;

use Pickware\PickwareErpStarter\Stock\Model\StockDefinition;
use Pickware\PickwareErpStarter\Stock\Model\StockMovementDefinition;
use Pickware\PickwareErpStarter\Stock\Model\WarehouseStockDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class ProductExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new OneToManyAssociationField(
                'pickwareErpStockMovements',
                StockMovementDefinition::class,
                'product_id',
                'id',
            ))->addFlags(new CascadeDelete(false /* isCloneRelevant */)),
        );

        $collection->add(
            (new OneToManyAssociationField(
                'pickwareErpStocks',
                StockDefinition::class,
                'product_id',
                'id',
            ))->addFlags(new CascadeDelete(false /* isCloneRelevant */)),
        );

        $collection->add(
            (new OneToManyAssociationField(
                'pickwareErpWarehouseStocks',
                WarehouseStockDefinition::class,
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
