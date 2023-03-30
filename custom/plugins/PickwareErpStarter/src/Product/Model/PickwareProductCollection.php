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

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void add(PickwareProductEntity $entity)
 * @method void set(string $key, PickwareProductEntity $entity)
 * @method PickwareProductEntity[] getIterator()
 * @method PickwareProductEntity[] getElements()
 * @method PickwareProductEntity|null get(string $key)
 * @method PickwareProductEntity|null first()
 * @method PickwareProductEntity|null last()
 */
class PickwareProductCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return PickwareProductEntity::class;
    }
}
