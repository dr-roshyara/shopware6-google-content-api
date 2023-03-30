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

class ProductAvailableStockUpdatedEvent
{
    public const EVENT_NAME = 'pickware_erp.stock.product_available_stock_updated';

    /**
     * @var string[]
     */
    private array $productIds;

    public function __construct(array $productIds)
    {
        $this->productIds = $productIds;
    }

    public function getProductIds(): array
    {
        return $this->productIds;
    }
}
