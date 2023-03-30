<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\PurchaseList\Model;

use Pickware\DalBundle\Field\FixedReferenceVersionField;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class PurchaseListItemDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_erp_purchase_list_item';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return PurchaseListItemEntity::class;
    }

    public function getCollectionClass(): string
    {
        return PurchaseListItemCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            new IntField('purchase_suggestion', 'purchaseSuggestion'),
            new IntField('quantity', 'quantity'),

            (new FkField('product_id', 'productId', ProductDefinition::class, 'id'))->addFlags(new Required()),
            (new FixedReferenceVersionField(ProductDefinition::class, 'product_version_id'))->addFlags(new Required()),
            new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class, 'id'),
        ]);
    }

    public function getDefaults(): array
    {
        return [
            'purchaseSuggestion' => 0,
            'quantity' => 0,
        ];
    }
}
