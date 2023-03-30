<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShopwareExtensionsBundle\OrderTransaction;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;

/**
 * @deprecated tag:next-major Use OrderTransactionCollectionExtension instead
 */
class PickwareOrderTransactionCollection extends OrderTransactionCollection
{
    /**
     * Return the oldest order transaction that is not 'cancelled' else return the last order transaction.
     *
     * @see https://github.com/shopware/platform/blob/v6.4.8.1/src/Administration/Resources/app/administration/src/module/sw-order/view/sw-order-detail-base/index.js#L91-L98
     * @see https://github.com/shopware/platform/blob/v6.4.8.1/src/Administration/Resources/app/administration/src/module/sw-order/view/sw-order-detail-base/index.js#L207
     *
     * @deprecated tag:next-major Use OrderTransactionCollectionExtension::getPrimaryOrderTransaction instead
     */
    public function getPrimaryOrderTransaction(): ?OrderTransactionEntity
    {
        /** @var OrderTransactionCollection $collectionCopy */
        $collectionCopy = self::createFrom($this);
        // Sort by createdAt ascending
        $collectionCopy->sort(function (OrderTransactionEntity $a, OrderTransactionEntity $b) {
            if ($a->getCreatedAt() === $b->getCreatedAt()) {
                return 0;
            }

            return $a->getCreatedAt()->getTimestamp() < $b->getCreatedAt()->getTimestamp() ? -1 : 1;
        });

        foreach ($collectionCopy as $orderTransaction) {
            if ($orderTransaction->getStateMachineState()->getTechnicalName() !== OrderTransactionStates::STATE_CANCELLED) {
                return $orderTransaction;
            }
        }

        return $collectionCopy->last();
    }
}
