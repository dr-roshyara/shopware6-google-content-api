<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShopwareExtensionsBundle\OrderDelivery;

use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;

/**
 * @deprecated tag:next-major Use OrderDeliveryCollectionExtension instead
 */
class PickwareOrderDeliveryCollection extends OrderDeliveryCollection
{
    /**
     * We use the order delivery with the highest shipping costs as Shopware creates additional order deliveries with
     * negative shipping costs when applying shipping costs vouchers and just using the first or last order delivery
     * without sorting first can result in the wrong order delivery to be used.
     *
     * @deprecated tag:next-major Use OrderDeliveryCollectionExtension::getPrimaryOrderDelivery instead
     */
    public function getPrimaryOrderDelivery(): ?OrderDeliveryEntity
    {
        $collectionCopy = self::createFrom($this);
        // Sort by shippingCosts ascending
        $collectionCopy->sort(function (OrderDeliveryEntity $a, OrderDeliveryEntity $b) {
            if ($a->getShippingCosts()->getTotalPrice() === $b->getShippingCosts()->getTotalPrice()) {
                return 0;
            }

            return $a->getShippingCosts()->getTotalPrice() < $b->getShippingCosts()->getTotalPrice() ? -1 : 1;
        });

        return $collectionCopy->last();
    }
}
