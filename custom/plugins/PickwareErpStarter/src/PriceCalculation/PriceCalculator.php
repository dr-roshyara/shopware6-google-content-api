<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\PriceCalculation;

use InvalidArgumentException;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceDefinitionInterface;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;

class PriceCalculator
{
    private QuantityPriceCalculator $quantityPriceCalculator;

    public function __construct(QuantityPriceCalculator $quantityPriceCalculator)
    {
        $this->quantityPriceCalculator = $quantityPriceCalculator;
    }

    public function calculatePrice(
        PriceDefinitionInterface $priceDefinition,
        PriceCalculationContext $priceCalculationContext
    ): CalculatedPrice {
        switch ($priceDefinition->getType()) {
            case QuantityPriceDefinition::TYPE:
                /** @var QuantityPriceDefinition $priceDefinition */
                return $this->quantityPriceCalculator->calculate($priceDefinition, $priceCalculationContext);
            default:
                throw new InvalidArgumentException(sprintf(
                    'Recalculating price for price definition of type "%s" is not supported.',
                    $priceDefinition->getType(),
                ));
        }
    }
}
