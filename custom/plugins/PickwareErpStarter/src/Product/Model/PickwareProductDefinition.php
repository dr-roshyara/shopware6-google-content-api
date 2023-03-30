<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Product\Model;

use Pickware\DalBundle\Field\FixedReferenceVersionField;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class PickwareProductDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_erp_pickware_product';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return PickwareProductEntity::class;
    }

    public function getCollectionClass(): string
    {
        return PickwareProductCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new FkField('product_id', 'productId', ProductDefinition::class, 'id'))->addFlags(new Required()),
            (new FixedReferenceVersionField(ProductDefinition::class, 'product_version_id'))->addFlags(new Required()),
            new OneToOneAssociationField('product', 'product_id', 'id', ProductDefinition::class, false),

            new IntField('reorder_point', 'reorderPoint'),
            (new IntField('incoming_stock', 'incomingStock'))->addFlags(new WriteProtected(Context::SYSTEM_SCOPE)),

            new CreatedAtField(),
            new UpdatedAtField(),
        ]);
    }
}
