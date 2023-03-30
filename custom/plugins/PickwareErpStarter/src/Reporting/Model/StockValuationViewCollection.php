<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Reporting\Model;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void add(StockValuationViewEntity $entity)
 * @method void set(string $key, StockValuationViewEntity $entity)
 * @method StockValuationViewEntity[] getIterator()
 * @method StockValuationViewEntity[] getElements()
 * @method StockValuationViewEntity|null get(string $key)
 * @method StockValuationViewEntity|null first()
 * @method StockValuationViewEntity|null last()
 */
class StockValuationViewCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return StockValuationViewEntity::class;
    }
}
