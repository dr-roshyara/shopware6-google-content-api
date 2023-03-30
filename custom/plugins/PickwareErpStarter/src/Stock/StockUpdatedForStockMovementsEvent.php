<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stock;

class StockUpdatedForStockMovementsEvent
{
    public const EVENT_NAME = 'pickware_erp.stock.stock_updated_for_stock_movements';

    private array $stockMovements;

    public function __construct(array $stockMovements)
    {
        $this->stockMovements = $stockMovements;
    }

    public function getStockMovements(): array
    {
        return $this->stockMovements;
    }
}
