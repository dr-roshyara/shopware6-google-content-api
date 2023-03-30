<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShopwareExtensionsBundle\OrderConfiguration\Model;

use Pickware\DalBundle\Field\FixedReferenceVersionField;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class OrderConfigurationDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_shopware_extensions_order_configuration';

    public const ENTITY_WRITTEN_EVENT = self::ENTITY_NAME . '.written';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return OrderConfigurationEntity::class;
    }

    public function getCollectionClass(): string
    {
        return OrderConfigurationCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            new VersionField(),

            (new FkField('order_id', 'orderId', OrderDefinition::class, 'id'))->addFlags(new Required()),
            (new FixedReferenceVersionField(OrderDefinition::class, 'order_version_id'))->addFlags(new Required()),
            new OneToOneAssociationField('order', 'order_id', 'id', OrderDefinition::class, false),

            new FkField('primary_order_delivery_id', 'primaryOrderDeliveryId', OrderDeliveryDefinition::class, 'id'),
            new FixedReferenceVersionField(OrderDeliveryDefinition::class, 'primary_order_delivery_version_id'),
            new OneToOneAssociationField('primaryOrderDelivery', 'primary_order_delivery_id', 'id', OrderDeliveryDefinition::class, false),

            new FkField('primary_order_transaction_id', 'primaryOrderTransactionId', OrderTransactionDefinition::class, 'id'),
            new FixedReferenceVersionField(OrderTransactionDefinition::class, 'primary_order_transaction_version_id'),
            new OneToOneAssociationField('primaryOrderTransaction', 'primary_order_transaction_id', 'id', OrderTransactionDefinition::class, false),
        ]);
    }
}
