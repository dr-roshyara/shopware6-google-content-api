<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\OrderCalculation;

use Shopware\Core\Framework\Util\FloatComparator;

class OrderCalculationService
{
    private PriceTotalCalculator $priceTotalCalculator;

    public function __construct(PriceTotalCalculator $priceTotalCalculator)
    {
        $this->priceTotalCalculator = $priceTotalCalculator;
    }

    /**
     * Merge the given orders into a single order regarding prices and line items. Same order line items of the summed
     * up order will be reduced my merging (see reduceOrderLineItems()).
     */
    public function mergeOrders(CalculatableOrder $baseOrder, CalculatableOrder ...$orders): CalculatableOrder
    {
        $mergedOrder = CalculatableOrder::createFrom($baseOrder);

        foreach ($orders as $order) {
            foreach ($order->lineItems as $lineItem) {
                $mergedOrder->addLineItem($lineItem);
            }

            $mergedOrder->price = $this->priceTotalCalculator->sumCartPrices($mergedOrder->price, $order->price);
            $mergedOrder->shippingCosts = $this->priceTotalCalculator->sumShippingCosts(
                $mergedOrder->shippingCosts,
                $order->shippingCosts,
            );
        }

        $mergedOrder->lineItems = $this->consolidateOrderLineItems($mergedOrder->lineItems);

        return $mergedOrder;
    }

    /**
     * Consolidates the order line items of the given order by merging 'matching' order line items together to a single
     * order line item: add quantities, add prices, add taxes.
     *
     * Since there may be negative order line item quantities, the resulting merged order line item may have 0 quantity.
     * The resulting order line item will be removed from the order in this case.
     *
     * @param CalculatableOrderLineItem[] $orderLineItems
     * @return CalculatableOrderLineItem[]
     */
    private function consolidateOrderLineItems(array $orderLineItems): array
    {
        foreach ($orderLineItems as $keyA => $orderLineItemA) {
            foreach ($orderLineItems as $keyB => $orderLineItemB) {
                if ($keyA === $keyB) {
                    continue;
                }

                if ($this->orderLineItemMatchesOrderLineItem($orderLineItemA, $orderLineItemB)) {
                    $orderLineItemA->price = $this->priceTotalCalculator->sumCalculatedPrices(
                        $orderLineItemA->price,
                        $orderLineItemB->price,
                    );
                    $orderLineItemA->quantity = $orderLineItemA->quantity + $orderLineItemB->quantity;
                    unset($orderLineItems[$keyB]);
                    if ($orderLineItemA->price->getQuantity() === 0 && $orderLineItemA->quantity === 0) {
                        // If the quantity was reduced to zero, the order line item is removed from the result list.
                        unset($orderLineItems[$keyA]);
                    }

                    // If an order line item was reduced/removed while we are looping through the lists, break the loop
                    // and run again.
                    return $this->consolidateOrderLineItems($orderLineItems);
                }
            }
        }

        return $orderLineItems;
    }

    /**
     * Compares two given order line items and determines whether or not they reference the same real-world order line
     * item.
     *
     * Order line items are considered matching if:
     *  - they reference the same product and in turn the same type and label
     *  - they have the same unit price and in turn calculated prices and tax values
     *  - they have the same tax rules (e.g. "100% taxed at 19.00%")
     *
     * Order line items are still considered matching if they have different quantities and therefore different totals
     * and total tax values.
     */
    private function orderLineItemMatchesOrderLineItem(
        CalculatableOrderLineItem $orderLineItem1,
        CalculatableOrderLineItem $orderLineItem2
    ): bool {
        if ($orderLineItem1->type !== $orderLineItem2->type) {
            return false;
        }
        if ($orderLineItem1->productId !== $orderLineItem2->productId) {
            return false;
        }
        if ($orderLineItem1->productVersionId !== $orderLineItem2->productVersionId) {
            return false;
        }
        if ($orderLineItem1->label !== $orderLineItem2->label) {
            return false;
        }

        // Prices
        $price1 = $orderLineItem1->price;
        $price2 = $orderLineItem2->price;
        if (!FloatComparator::equals($price1->getUnitPrice(), $price2->getUnitPrice())) {
            return false;
        }

        // Tax Rules
        $taxRules1 = $price1->getTaxRules();
        $taxRules2 = $price2->getTaxRules();
        if ($taxRules1->count() !== $taxRules2->count()) {
            return false;
        }
        // Since tax rule collections are mapped by the tax rate of the tax rule, we can use this key for the comparison
        foreach ($taxRules1->getKeys() as $taxRate) {
            $taxRule1 = $taxRules1->get($taxRate);
            $taxRule2 = $taxRules2->get($taxRate);
            if (!$taxRule2) {
                // A tax rule in tax rules collection 1 was not found (by tax rate) in tax rules collection 2
                return false;
            }
            if (!FloatComparator::equals($taxRule1->getTaxRate(), $taxRule2->getTaxRate())) {
                return false;
            }
            if (!FloatComparator::equals($taxRule1->getPercentage(), $taxRule2->getPercentage())) {
                return false;
            }
        }
        // We do not compare calculated taxes since they depend on the item's quantity. If the unit price matches and
        // the tax rules are the same (see above), the calculated prices should be matching.

        return true;
    }
}
