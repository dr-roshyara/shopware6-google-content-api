<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\MoneyBundle;

use InvalidArgumentException;
use JsonSerializable;

class MoneyValue implements JsonSerializable
{
    /**
     * @var float
     */
    private $value;

    /**
     * @var Currency
     */
    private $currency;

    public function __construct(float $value, Currency $currency)
    {
        $this->value = $value;
        $this->currency = $currency;
    }

    public static function sum(self ...$moneyValues): self
    {
        return array_reduce(
            $moneyValues,
            function (MoneyValue $total, MoneyValue $moneyValue) {
                if ($moneyValue->value === 0.0) {
                    return $total;
                }
                if ($total->value === 0.0) {
                    // If the total is currently zero, it has no currency. So use the currency of the added value now.
                    $total->currency = $moneyValue->currency;
                }

                if (!($moneyValue->currency->equals($total->currency))) {
                    throw new InvalidArgumentException(sprintf(
                        'You cannot add up instances of %s that do not have the same currency code. Please first ' .
                        'convert all instances to the same currency before adding them up.',
                        self::class,
                    ));
                }
                $total->value += $moneyValue->value;

                return $total;
            },
            // Use "no currency" as start value. This is necessary for the case $moneyValues is an empty array or all
            // contained money values are zero. We then can still return a valid MoneyValue object.
            new MoneyValue(0.0, new Currency(Currency::ISO_CODE_NO_CURRENCY)),
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'value' => $this->value,
            'currency' => $this->currency,
        ];
    }

    public static function fromArray(array $array): self
    {
        return new self(
            $array['value'],
            Currency::fromArray($array['currency']),
        );
    }

    public function getValue(): float
    {
        return $this->value;
    }

    public function getCurrency(): Currency
    {
        return $this->currency;
    }

    public function convertTo(Currency $targetCurrency, float $conversionFactor): self
    {
        return new self($this->value * $conversionFactor, $targetCurrency);
    }
}
