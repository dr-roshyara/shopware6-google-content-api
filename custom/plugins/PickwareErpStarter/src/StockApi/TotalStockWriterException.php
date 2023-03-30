<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\StockApi;

use Exception;

class TotalStockWriterException extends Exception
{
    public static function notEnoughStock(string $productId): self
    {
        return new self(sprintf(
            'There is not enough stock for product with ID=%s in any warehouse',
            $productId,
        ));
    }

    public static function negativeStockNotAllowed(): self
    {
        return new self('Setting a negative stock for a product is not allowed.');
    }
}
