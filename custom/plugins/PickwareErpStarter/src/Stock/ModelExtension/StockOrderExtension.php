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
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class StockOrderExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new OneToManyAssociationField(
                'pickwareErpSourceStockMovements',
                StockMovementDefinition::class,
                'source_order_id',
                'id',
            ))->addFlags(new SetNullOnDelete()),
        );

        $collection->add(
            (new OneToManyAssociationField(
                'pickwareErpDestinationStockMovements',
                StockMovementDefinition::class,
                'destination_order_id',
                'id',
            ))->addFlags(new SetNullOnDelete()),
        );

        $collection->add(
            (new OneToManyAssociationField(
                'pickwareErpStocks',
                StockDefinition::class,
                'order_id',
                'id',
            ))->addFlags(new CascadeDelete(/* cloneRelevant: */false)),
        );
    }

    public function getDefinitionClass(): string
    {
        return OrderDefinition::class;
    }
}
