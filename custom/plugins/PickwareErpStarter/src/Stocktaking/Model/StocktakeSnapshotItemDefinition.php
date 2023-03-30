<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stocktaking\Model;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class StocktakeSnapshotItemDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_erp_stocktaking_stocktake_snapshot_item';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new FkField('counting_process_item_id', 'countingProcessItemId', StocktakeCountingProcessItemDefinition::class))->addFlags(new Required()),
            new OneToOneAssociationField('countingProcessItem', 'counting_process_item_id', 'id', StocktakeCountingProcessItemDefinition::class, false),

            (new IntField('warehouse_stock', 'warehouseStock'))->addFlags(new Required()),
            (new IntField('total_counted', 'totalCounted'))->addFlags(new Required()),
            (new IntField('total_stock_difference', 'totalStockDifference'))->addFlags(new Required()),
            (new IntField('stock_location_stock', 'stockLocationStock'))->addFlags(new Required()),
            (new IntField('counted', 'counted'))->addFlags(new Required()),
            (new IntField('stock_difference', 'stockDifference'))->addFlags(new Required()),
        ]);
    }

    public function getCollectionClass(): string
    {
        return StocktakeSnapshotItemCollection::class;
    }

    public function getEntityClass(): string
    {
        return StocktakeSnapshotItemEntity::class;
    }
}
