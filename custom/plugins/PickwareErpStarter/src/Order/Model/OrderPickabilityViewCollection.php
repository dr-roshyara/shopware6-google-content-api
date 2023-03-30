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

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @deprecated will be removed with 3.0.0. Use the new order.pickwareErpOrderPickabilities extension instead.
 *
 * @method void add(OrderPickabilityViewEntity $entity)
 * @method void set(string $key, OrderPickabilityViewEntity $entity)
 * @method OrderPickabilityViewEntity[] getIterator()
 * @method OrderPickabilityViewEntity[] getElements()
 * @method OrderPickabilityViewEntity|null get(string $key)
 * @method OrderPickabilityViewEntity|null first()
 * @method OrderPickabilityViewEntity|null last()
 */
class OrderPickabilityViewCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return OrderPickabilityViewEntity::class;
    }
}
