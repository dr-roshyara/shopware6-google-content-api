<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Picking;

class WarehousePriorityComparator implements WarehouseComparator
{
    public function compare(PickLocationWarehouse $warehouseA, PickLocationWarehouse $warehouseB): int
    {
        // If any one of the warehouses is de default warehouse, sort it to the front
        if ($warehouseA->isDefault() || $warehouseB->isDefault()) {
            return (int) $warehouseB->isDefault() - (int) $warehouseA->isDefault();
        }

        // Otherwise, sort by createdAt (ascending (oldest warehouse first))
        $isAOlder = $warehouseA->getCreatedAt()->diff($warehouseB->getCreatedAt())->invert;
        $isBOlder = $warehouseB->getCreatedAt()->diff($warehouseA->getCreatedAt())->invert;

        return $isAOlder - $isBOlder;
    }
}
