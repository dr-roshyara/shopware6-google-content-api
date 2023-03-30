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
 * @method void add(WarehouseStockEntity $entity)
 * @method void set(string $key, StockEntity $entity)
 * @method WarehouseStockEntity[] getIterator()
 * @method WarehouseStockEntity[] getElements()
 * @method WarehouseStockEntity|null get(string $key)
 * @method WarehouseStockEntity|null first()
 * @method WarehouseStockEntity|null last()
 */
class WarehouseStockCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return WarehouseStockEntity::class;
    }
}
