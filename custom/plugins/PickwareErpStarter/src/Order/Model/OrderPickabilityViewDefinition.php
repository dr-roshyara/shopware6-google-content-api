<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Order\Model;

use Pickware\DalBundle\Field\EnumField;
use Pickware\DalBundle\Field\FixedReferenceVersionField;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @deprecated will be removed with 3.0.0. Use the new order.pickwareErpOrderPickabilities extension instead.
 */
class OrderPickabilityViewDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_erp_order_pickability_view';

    public const PICKABILITY_STATUS_COMPLETELY_PICKABLE = 'completely_pickable';
    public const PICKABILITY_STATUS_PARTIALLY_PICKABLE = 'partially_pickable';
    public const PICKABILITY_STATUS_NOT_PICKABLE = 'not_pickable';
    public const PICKABILITY_STATUS_CANCELLED_OR_SHIPPED = 'cancelled_or_shipped';

    public const PICKABILITY_STATES = [
        self::PICKABILITY_STATUS_COMPLETELY_PICKABLE,
        self::PICKABILITY_STATUS_PARTIALLY_PICKABLE,
        self::PICKABILITY_STATUS_NOT_PICKABLE,
        self::PICKABILITY_STATUS_CANCELLED_OR_SHIPPED,
    ];

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return OrderPickabilityViewEntity::class;
    }

    public function getCollectionClass(): string
    {
        return OrderPickabilityViewCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new FkField('order_id', 'orderId', OrderDefinition::class, 'id'))->addFlags(new Required()),
            (new FixedReferenceVersionField(OrderDefinition::class, 'order_version_id'))->addFlags(new Required()),
            new OneToOneAssociationField('order', 'order_id', 'id', OrderDefinition::class, false),

            new EnumField('order_pickability_status', 'orderPickabilityStatus', self::PICKABILITY_STATES),

            new CreatedAtField(),
            new UpdatedAtField(),
        ]);
    }
}
