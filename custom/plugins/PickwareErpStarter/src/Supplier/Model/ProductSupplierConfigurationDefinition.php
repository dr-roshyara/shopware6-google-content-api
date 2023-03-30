<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Supplier\Model;

use Pickware\DalBundle\Field\FixedReferenceVersionField;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class ProductSupplierConfigurationDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_erp_product_supplier_configuration';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return ProductSupplierConfigurationEntity::class;
    }

    public function getCollectionClass(): string
    {
        return ProductSupplierConfigurationCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new FkField('product_id', 'productId', ProductDefinition::class, 'id'))->addFlags(new Required()),
            (new FixedReferenceVersionField(ProductDefinition::class, 'product_version_id'))->addFlags(new Required()),
            new OneToOneAssociationField('product', 'product_id', 'id', ProductDefinition::class, false),

            new FkField('supplier_id', 'supplierId', SupplierDefinition::class, 'id'),
            new ManyToOneAssociationField(
                'supplier',
                'supplier_id',
                SupplierDefinition::class,
                'id',
                false,
            ),

            new StringField('supplier_product_number', 'supplierProductNumber'),
            (new IntField('min_purchase', 'minPurchase'))->addFlags(new Required()),
            (new IntField('purchase_steps', 'purchaseSteps'))->addFlags(new Required()),
        ]);
    }

    public function getDefaults(): array
    {
        return [
            'minPurchase' => 1,
            'purchaseSteps' => 1,
        ];
    }
}
