<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stock\Model;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void add(StockMovementEntity $entity)
 * @method void set(string $key, StockMovementEntity $entity)
 * @method StockMovementEntity[] getIterator()
 * @method StockMovementEntity[] getElements()
 * @method StockMovementEntity|null get(string $key)
 * @method StockMovementEntity|null first()
 * @method StockMovementEntity|null last()
 */
class StockMovementCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return StockMovementEntity::class;
    }
}
