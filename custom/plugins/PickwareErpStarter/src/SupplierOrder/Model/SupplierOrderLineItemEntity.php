<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\SupplierOrder\Model;

use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class SupplierOrderLineItemEntity extends Entity
{
    use EntityIdTrait;

    protected string $supplierOrderId;
    protected ?SupplierOrderEntity $supplierOrder = null;
    protected ?string $productId = null;
    protected ?ProductEntity $product = null;
    protected array $productSnapshot;
    protected int $quantity;
    protected CalculatedPrice $price;
    protected QuantityPriceDefinition $priceDefinition;
    protected float $unitPrice;
    protected float $totalPrice;

    public function getSupplierOrderId(): string
    {
        return $this->supplierOrderId;
    }

    public function setSupplierOrderId(string $supplierOrderId): void
    {
        if ($this->supplierOrder && $this->supplierOrder->getId() !== $supplierOrderId) {
            $this->supplierOrder = null;
        }

        $this->supplierOrderId = $supplierOrderId;
    }

    public function getSupplierOrder(): SupplierOrderEntity
    {
        if (!$this->supplierOrder) {
            throw new AssociationNotLoadedException('supplierOrder', $this);
        }

        return $this->supplierOrder;
    }

    public function setSupplierOrder(SupplierOrderEntity $supplierOrder): void
    {
        $this->supplierOrderId = $supplierOrder->getId();
        $this->supplierOrder = $supplierOrder;
    }

    public function getProductId(): ?string
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

    public function getProduct(): ?ProductEntity
    {
        return $this->product;
    }

    public function setProduct(ProductEntity $product): void
    {
        $this->productId = $product->getId();
        $this->product = $product;
    }

    public function getProductSnapshot(): array
    {
        return $this->productSnapshot;
    }

    public function setProductSnapshot(array $productSnapshot): void
    {
        $this->productSnapshot = $productSnapshot;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function getPrice(): CalculatedPrice
    {
        return $this->price;
    }

    public function setPrice(CalculatedPrice $price): void
    {
        $this->price = $price;
    }

    public function getPriceDefinition(): QuantityPriceDefinition
    {
        return $this->priceDefinition;
    }

    public function setPriceDefinition(QuantityPriceDefinition $priceDefinition): void
    {
        $this->priceDefinition = $priceDefinition;
    }

    public function getUnitPrice(): float
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(float $unitPrice): void
    {
        $this->unitPrice = $unitPrice;
    }

    public function getTotalPrice(): float
    {
        return $this->totalPrice;
    }

    public function setTotalPrice(float $totalPrice): void
    {
        $this->totalPrice = $totalPrice;
    }
}
