<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stocking;

use Pickware\PickwareErpStarter\StockApi\StockLocationReference;

class ProductQuantityLocation
{
    /**
     * @var StockLocationReference
     */
    private $stockLocationReference;

    /**
     * @var ProductQuantity
     */
    private $productQuantity;

    public function __construct(StockLocationReference $locationReference, ProductQuantity $productQuantity)
    {
        $this->stockLocationReference = $locationReference;
        $this->productQuantity = $productQuantity;
    }

    public function getStockLocationReference(): StockLocationReference
    {
        return $this->stockLocationReference;
    }

    public function getProductQuantity(): ProductQuantity
    {
        return $this->productQuantity;
    }
}
