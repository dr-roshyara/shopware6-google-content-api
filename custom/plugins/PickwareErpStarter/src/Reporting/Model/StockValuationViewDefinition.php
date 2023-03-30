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

use Pickware\DalBundle\Field\FixedReferenceVersionField;
use Pickware\PickwareErpStarter\Stock\Model\WarehouseStockDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\Currency\CurrencyDefinition;

class StockValuationViewDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_erp_stock_valuation_view';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return StockValuationViewEntity::class;
    }

    public function getCollectionClass(): string
    {
        return StockValuationViewCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new FkField('warehouse_stock_id', 'warehouseStockId', WarehouseStockDefinition::class, 'id'))->addFlags(new Required()),
            new ManyToOneAssociationField('warehouseStock', 'warehouse_stock_id', WarehouseStockDefinition::class, 'id'),

            (new FkField('product_id', 'productId', ProductDefinition::class, 'id'))->addFlags(new Required()),
            (new FixedReferenceVersionField(ProductDefinition::class, 'product_version_id'))->addFlags(new Required()),
            new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class, 'id'),

            new FkField('currency_id', 'currencyId', CurrencyDefinition::class, 'id'),
            new ManyToOneAssociationField('currency', 'currency_id', CurrencyDefinition::class, 'id'),

            new FloatField('purchase_price_net', 'purchasePriceNet'),
            new FloatField('purchase_price_gross', 'purchasePriceGross'),
            new FloatField('stock_valuation_net', 'stockValuationNet'),
            new FloatField('stock_valuation_gross', 'stockValuationGross'),

            new FloatField('purchase_price_net_in_default_currency', 'purchasePriceNetInDefaultCurrency'),
            new FloatField('purchase_price_gross_in_default_currency', 'purchasePriceGrossInDefaultCurrency'),
            new FloatField('stock_valuation_net_in_default_currency', 'stockValuationNetInDefaultCurrency'),
            new FloatField('stock_valuation_gross_in_default_currency', 'stockValuationGrossInDefaultCurrency'),
        ]);
    }
}
