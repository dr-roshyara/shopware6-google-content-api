<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\ReturnOrder\Model;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void add(ReturnOrderEntity $entity)
 * @method void set(string $key, ReturnOrderEntity $entity)
 * @method ReturnOrderEntity[] getIterator()
 * @method ReturnOrderEntity[] getElements()
 * @method ReturnOrderEntity|null get(string $key)
 * @method ReturnOrderEntity|null first()
 * @method ReturnOrderEntity|null last()
 */
class ReturnOrderCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ReturnOrderEntity::class;
    }
}
