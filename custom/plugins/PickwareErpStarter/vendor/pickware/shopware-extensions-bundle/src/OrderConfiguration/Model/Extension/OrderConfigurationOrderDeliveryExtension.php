<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShopwareExtensionsBundle\OrderConfiguration\Model\Extension;

use Pickware\ShopwareExtensionsBundle\OrderConfiguration\Model\OrderConfigurationDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class OrderConfigurationOrderDeliveryExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new OneToOneAssociationField(
                'pickwareShopwareExtensionsOrderConfiguration',
                'id',
                'primary_order_delivery_id',
                OrderConfigurationDefinition::class,
                false, /* $autoload */
            ))->addFlags(new SetNullOnDelete()),
        );
    }

    public function getDefinitionClass(): string
    {
        return OrderDeliveryDefinition::class;
    }
}
