<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Warehouse\Model;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void add(BinLocationEntity $entity)
 * @method void set(string $key, BinLocationEntity $entity)
 * @method BinLocationEntity[] getIterator()
 * @method BinLocationEntity[] getElements()
 * @method BinLocationEntity|null get(string $key)
 * @method BinLocationEntity|null first()
 * @method BinLocationEntity|null last()
 */
class BinLocationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return BinLocationEntity::class;
    }
}
