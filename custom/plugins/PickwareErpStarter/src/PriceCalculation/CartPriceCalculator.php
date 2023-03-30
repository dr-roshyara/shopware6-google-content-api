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
use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;

/**
 * See also Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator
 */
class CartPriceCalculator
{
    private CashRounding $rounding;

    public function __construct(CashRounding $rounding)
    {
        $this->rounding = $rounding;
    }

    public function calculateCartPrice(
        PriceCollection $prices,
        PriceCalculationContext $priceCalculationContext
    ): CartPrice {
        $taxStatus = $priceCalculationContext->taxStatus;
        switch ($taxStatus) {
            case CartPrice::TAX_STATE_GROSS:
                return $this->calculateGrossCartPrice(
                    $prices,
                    $priceCalculationContext->itemRounding,
                    $priceCalculationContext->totalRounding,
                );
            case CartPrice::TAX_STATE_NET:
                return $this->calculateNetCartPrice(
                    $prices,
                    $priceCalculationContext->itemRounding,
                    $priceCalculationContext->totalRounding,
                );
            case CartPrice::TAX_STATE_FREE:
                return $this->calculateTaxFreeCartPrice($prices);
            default:
                throw new InvalidArgumentException(sprintf(
                    'Calculating cart price with tax status "%s" is not supported.',
                    $taxStatus,
                ));
        }
    }

    /**
     * See also Shopware\Core\Checkout\Cart\Price\AmountCalculator::calculateGrossAmount()
     */
    private function calculateGrossCartPrice(
        PriceCollection $prices,
        CashRoundingConfig $itemRounding,
        CashRoundingConfig $totalRounding
    ): CartPrice {
        $total = $prices->sum();
        $taxes = $this->calculateTaxes($prices, $itemRounding);
        $price = $this->rounding->cashRound($total->getTotalPrice(), $totalRounding);
        $net = $this->rounding->mathRound($total->getTotalPrice() - $taxes->getAmount(), $itemRounding);

        return new CartPrice(
            $net,
            $price,
            $prices->sum()->getTotalPrice(),
            $taxes,
            $total->getTaxRules(),
            CartPrice::TAX_STATE_GROSS,
            $total->getTotalPrice(),
        );
    }

    /**
     * See also Shopware\Core\Checkout\Cart\Price\AmountCalculator::calculateNetAmount()
     */
    private function calculateNetCartPrice(
        PriceCollection $prices,
        CashRoundingConfig $itemRounding,
        CashRoundingConfig $totalRounding
    ): CartPrice {
        $total = $prices->sum();
        $taxes = $this->calculateTaxes($prices, $itemRounding);
        $price = $this->rounding->cashRound($total->getTotalPrice() + $taxes->getAmount(), $totalRounding);

        return new CartPrice(
            $total->getTotalPrice(),
            $price,
            $prices->sum()->getTotalPrice(),
            $taxes,
            $total->getTaxRules(),
            CartPrice::TAX_STATE_NET,
            $total->getTotalPrice() + $taxes->getAmount(),
        );
    }

    /**
     * See also Shopware\Core\Checkout\Cart\Price\AmountCalculator::calculateNetDeliveryAmount()
     */
    private function calculateTaxFreeCartPrice(PriceCollection $prices): CartPrice
    {
        $total = $prices->sum()->getTotalPrice();

        return new CartPrice(
            $total,
            $total,
            $total,
            new CalculatedTaxCollection([]),
            new TaxRuleCollection([]),
            CartPrice::TAX_STATE_FREE,
        );
    }

    /**
     * Note that we are using horizontal tax calculation here (SalesChannelDefinition::CALCULATION_TYPE_HORIZONTAL) and
     * simply sum up the taxes of the given prices.
     *
     * Otherwise, the Shopware\Core\Checkout\Cart\Tax\TaxCalculator should be used. See also
     * Shopware\Core\Checkout\Cart\Price\AmountCalculator::calculateTaxes()
     */
    private function calculateTaxes(PriceCollection $prices, CashRoundingConfig $itemRounding): CalculatedTaxCollection
    {
        $taxes = $prices->getCalculatedTaxes();
        $taxes->round($this->rounding, $itemRounding);

        return $taxes;
    }
}
