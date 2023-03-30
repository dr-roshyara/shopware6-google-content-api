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

use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Pickware\PickwareErpStarter\Stock\Model\WarehouseStockEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\Currency\CurrencyEntity;

class StockValuationViewEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $warehouseStockId;

    /**
     * @var WarehouseStockEntity|null
     */
    protected $warehouseStock;

    /**
     * @var string
     */
    protected $productId;

    /**
     * @var ProductEntity|null
     */
    protected $product;

    /**
     * @var string|null
     */
    protected $currencyId;

    /**
     * @var CurrencyEntity|null
     */
    protected $currency;

    /**
     * @var float|null
     */
    protected $purchasePriceNet;

    /**
     * @var float|null
     */
    protected $purchasePriceGross;

    /**
     * @var float|null
     */
    protected $stockValuationNet;

    /**
     * @var float|null
     */
    protected $stockValuationGross;

    /**
     * @var float|null
     */
    protected $purchasePriceNetInDefaultCurrency;

    /**
     * @var float|null
     */
    protected $purchasePriceGrossInDefaultCurrency;

    /**
     * @var float|null
     */
    protected $stockValuationNetInDefaultCurrency;

    /**
     * @var float|null
     */
    protected $stockValuationGrossInDefaultCurrency;

    public function getWarehouseStockId(): string
    {
        return $this->warehouseStockId;
    }

    public function setWarehouseStockId(string $warehouseStockId): void
    {
        if ($this->warehouseStock && $this->warehouseStock->getId() !== $warehouseStockId) {
            $this->warehouseStock = null;
        }
        $this->warehouseStockId = $warehouseStockId;
    }

    public function getWarehouseStock(): ?WarehouseStockEntity
    {
        if (!$this->warehouseStock) {
            throw new AssociationNotLoadedException('stock', $this);
        }

        return $this->warehouseStock;
    }

    public function setWarehouseStock(?WarehouseStockEntity $warehouseStock): void
    {
        if ($warehouseStock) {
            $this->warehouseStockId = $warehouseStock->getId();
        }
        $this->warehouseStock = $warehouseStock;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function setProductId(string $productId): void
    {
        if ($this->product && $this->product->getId() !== $productId) {
            $this->product = null;
        }
        $this->productId = $productId;
    }

    public function getProduct(): ProductEntity
    {
        if (!$this->product) {
            throw new AssociationNotLoadedException('product', $this);
        }

        return $this->product;
    }

    public function setProduct(?ProductEntity $product): void
    {
        if ($product) {
            $this->productId = $product->getId();
        }
        $this->product = $product;
    }

    public function getCurrencyId(): ?string
    {
        return $this->currencyId;
    }

    public function setCurrencyId(?string $currencyId): void
    {
        if ($this->currency && $this->currency->getId() !== $currencyId) {
            $this->currency = null;
        }
        $this->currencyId = $currencyId;
    }

    public function getCurrency(): ?CurrencyEntity
    {
        return $this->currency;
    }

    public function setCurrency(?CurrencyEntity $currency): void
    {
        if ($currency) {
            $this->currencyId = $currency->getId();
        }
        $this->currency = $currency;
    }

    public function getPurchasePriceNet(): ?float
    {
        return $this->purchasePriceNet;
    }

    public function setPurchasePriceNet(?float $purchasePriceNet): void
    {
        $this->purchasePriceNet = $purchasePriceNet;
    }

    public function getPurchasePriceGross(): ?float
    {
        return $this->purchasePriceGross;
    }

    public function setPurchasePriceGross(?float $purchasePriceGross): void
    {
        $this->purchasePriceGross = $purchasePriceGross;
    }

    public function getStockValuationNet(): ?float
    {
        return $this->stockValuationNet;
    }

    public function setStockValuationNet(?float $stockValuationNet): void
    {
        $this->stockValuationNet = $stockValuationNet;
    }

    public function getStockValuationGross(): ?float
    {
        return $this->stockValuationGross;
    }

    public function setStockValuationGross(?float $stockValuationGross): void
    {
        $this->stockValuationGross = $stockValuationGross;
    }

    public function getPurchasePriceNetInDefaultCurrency(): ?float
    {
        return $this->purchasePriceNetInDefaultCurrency;
    }

    public function setPurchasePriceNetInDefaultCurrency(?float $purchasePriceNetInDefaultCurrency): void
    {
        $this->purchasePriceNetInDefaultCurrency = $purchasePriceNetInDefaultCurrency;
    }

    public function getPurchasePriceGrossInDefaultCurrency(): ?float
    {
        return $this->purchasePriceGrossInDefaultCurrency;
    }

    public function setPurchasePriceGrossInDefaultCurrency(?float $purchasePriceGrossInDefaultCurrency): void
    {
        $this->purchasePriceGrossInDefaultCurrency = $purchasePriceGrossInDefaultCurrency;
    }

    public function getStockValuationNetInDefaultCurrency(): ?float
    {
        return $this->stockValuationNetInDefaultCurrency;
    }

    public function setStockValuationNetInDefaultCurrency(?float $stockValuationNetInDefaultCurrency): void
    {
        $this->stockValuationNetInDefaultCurrency = $stockValuationNetInDefaultCurrency;
    }

    public function getStockValuationGrossInDefaultCurrency(): ?float
    {
        return $this->stockValuationGrossInDefaultCurrency;
    }

    public function setStockValuationGrossInDefaultCurrency(?float $stockValuationGrossInDefaultCurrency): void
    {
        $this->stockValuationGrossInDefaultCurrency = $stockValuationGrossInDefaultCurrency;
    }
}
