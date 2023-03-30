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

use Pickware\InstallationLibrary\SqlView\SqlView;
use Shopware\Core\Defaults;

class StockValuationViewSqlView extends SqlView
{
    public function __construct()
    {
        $selectParentOrVariantPurchasePriceJson = 'COALESCE(product.purchase_prices, parentProduct.purchase_prices)';

        $defaultCurrencyKey = 'c' . Defaults::CURRENCY;
        // These snippets extract "the" purchase price from the PriceField. Since the PriceField may contain multiple
        // purchase prices and different currencies, the following strategy is applied to select "the" purchase price:
        // (1) select the purchase price in the default currency
        // (2) otherwise select the first purchase price of the price field (which is, because of Shopware's JSON field
        //     handling the price of the currency with the alphanumerically first lowest ID)
        // (3) otherwise use NULL
        $extractPurchasePriceString = 'COALESCE(
            JSON_UNQUOTE(JSON_EXTRACT(' . $selectParentOrVariantPurchasePriceJson . ', "$.' . $defaultCurrencyKey . '.%1$s")),
            JSON_UNQUOTE(JSON_EXTRACT(
                ' . $selectParentOrVariantPurchasePriceJson . ',
                CONCAT("$.", JSON_UNQUOTE(JSON_EXTRACT(JSON_KEYS(' . $selectParentOrVariantPurchasePriceJson . '), "$[0]")), ".%1$s")
            ))
        )';
        $extractPurchasePriceCurrencyString = 'UNHEX(IF(
            JSON_EXTRACT(' . $selectParentOrVariantPurchasePriceJson . ', "$.' . $defaultCurrencyKey . '") IS NOT NULL,
            "' . Defaults::CURRENCY . '",
            SUBSTR(JSON_UNQUOTE(JSON_EXTRACT(JSON_KEYS(' . $selectParentOrVariantPurchasePriceJson . '), "$[0]")), 2)
        ))';

        // Define sprintf as closure, so we can use the method in heredoc
        $sprintf = fn (...$args) => sprintf(...$args);

        parent::__construct(
            StockValuationViewDefinition::ENTITY_NAME,
            <<<SELECT_STATEMENT
                SELECT
                    warehouseStock.id AS id,
                    warehouseStock.id AS warehouse_stock_id,
                    product.id AS product_id,
                    product.version_id AS product_version_id,
                    currency.id AS currency_id,

                    ROUND({$sprintf($extractPurchasePriceString, 'net')}, JSON_UNQUOTE(JSON_EXTRACT(currency.item_rounding, "$.decimals"))) AS purchase_price_net,
                    ROUND({$sprintf($extractPurchasePriceString, 'gross')}, JSON_UNQUOTE(JSON_EXTRACT(currency.item_rounding, "$.decimals"))) AS purchase_price_gross,
                    -- Calculate the stock valuation with the rounded purchase price so the user can replicate this
                    -- result manually within this view.
                    warehouseStock.quantity * ROUND({$sprintf($extractPurchasePriceString, 'net')}, JSON_UNQUOTE(JSON_EXTRACT(currency.item_rounding, "$.decimals"))) AS stock_valuation_net,
                    warehouseStock.quantity * ROUND({$sprintf($extractPurchasePriceString, 'gross')}, JSON_UNQUOTE(JSON_EXTRACT(currency.item_rounding, "$.decimals"))) AS stock_valuation_gross,

                    -- Use the rounded purchase price when converting it to the default currency so the user can
                    -- replicate the results manually within this view.
                    ROUND(ROUND({$sprintf($extractPurchasePriceString, 'net')}, JSON_UNQUOTE(JSON_EXTRACT(currency.item_rounding, "$.decimals"))) * currency.factor, JSON_UNQUOTE(JSON_EXTRACT(defaultCurrency.item_rounding, "$.decimals"))) AS purchase_price_net_in_default_currency,
                    ROUND(ROUND({$sprintf($extractPurchasePriceString, 'gross')}, JSON_UNQUOTE(JSON_EXTRACT(currency.item_rounding, "$.decimals"))) * currency.factor, JSON_UNQUOTE(JSON_EXTRACT(defaultCurrency.item_rounding, "$.decimals"))) AS purchase_price_gross_in_default_currency,
                    -- Calculate the stock valuation (in default currency) with the rounded purchase price (in default
                    -- currency) so the user can replicate this result manually within this view.
                    warehouseStock.quantity * ROUND(ROUND({$sprintf($extractPurchasePriceString, 'net')}, JSON_UNQUOTE(JSON_EXTRACT(currency.item_rounding, "$.decimals"))) * currency.factor, JSON_UNQUOTE(JSON_EXTRACT(defaultCurrency.item_rounding, "$.decimals"))) AS stock_valuation_net_in_default_currency,
                    warehouseStock.quantity * ROUND(ROUND({$sprintf($extractPurchasePriceString, 'gross')}, JSON_UNQUOTE(JSON_EXTRACT(currency.item_rounding, "$.decimals"))) * currency.factor, JSON_UNQUOTE(JSON_EXTRACT(defaultCurrency.item_rounding, "$.decimals"))) AS stock_valuation_gross_in_default_currency,

                    NOW() as updated_at,
                    NOW() as created_at

                FROM pickware_erp_warehouse_stock warehouseStock
                INNER JOIN product ON product.id = warehouseStock.product_id AND product.version_id = warehouseStock.product_version_id
                LEFT JOIN product parentProduct ON product.parent_id = parentProduct.id
                LEFT JOIN currency ON currency.id = {$extractPurchasePriceCurrencyString}
                LEFT JOIN currency defaultCurrency ON defaultCurrency.id = UNHEX(:defaultCurrencyId)
                SELECT_STATEMENT,
            ['defaultCurrencyId' => Defaults::CURRENCY],
        );
    }
}
