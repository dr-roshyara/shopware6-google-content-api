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

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void add(OrderConfigurationEntity $entity)
 * @method void set(string $key, OrderConfigurationEntity $entity)
 * @method OrderConfigurationEntity[] getIterator()
 * @method OrderConfigurationEntity[] getElements()
 * @method OrderConfigurationEntity|null get(string $key)
 * @method OrderConfigurationEntity|null first()
 * @method OrderConfigurationEntity|null last()
 */
class OrderConfigurationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return OrderConfigurationEntity::class;
    }
}
