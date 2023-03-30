<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\ReturnOrder\Model;

use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceDefinitionInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class ReturnOrderLineItemEntity extends Entity
{
    use EntityIdTrait;

    protected string $type;
    protected string $name;
    protected int $quantity;
    protected PriceDefinitionInterface $priceDefinition;
    protected CalculatedPrice $price;
    protected float $unitPrice;
    protected float $totalPrice;
    protected ?string $productId;
    protected ?string $productVersionId;
    protected ?ProductEntity $product = null;
    protected ?string $productNumber;
    protected string $returnOrderId;
    protected ?ReturnOrderEntity $returnOrder = null;
    protected ?string $orderLineItemId;
    protected ?OrderLineItemEntity $orderLineItem = null;

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getPrice(): CalculatedPrice
    {
        return $this->price;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function getPriceDefinition(): PriceDefinitionInterface
    {
        return $this->priceDefinition;
    }

    public function setPriceDefinition(PriceDefinitionInterface $priceDefinition): void
    {
        $this->priceDefinition = $priceDefinition;
    }

    public function setPrice(CalculatedPrice $price): void
    {
        $this->price = $price;
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

    public function getProductId(): ?string
    {
        return $this->productId;
    }

    public function setProductId(?string $productId): void
    {
        if ($this->product && $this->product->getId() !== $productId) {
            $this->product = null;
        }
        $this->productId = $productId;
    }

    public function getProductVersionId(): ?string
    {
        return $this->productVersionId;
    }

    public function setProductVersionId(?string $productVersionId): void
    {
        $this->productVersionId = $productVersionId;
    }

    public function getProduct(): ?ProductEntity
    {
        if (!$this->product && $this->productId) {
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

    public function getProductNumber(): ?string
    {
        return $this->productNumber;
    }

    public function setProductNumber(?string $productNumber): void
    {
        $this->productNumber = $productNumber;
    }

    public function getReturnOrderId(): string
    {
        return $this->returnOrderId;
    }

    public function setReturnOrderId(string $returnOrderId): void
    {
        if ($this->returnOrder && $this->returnOrder->getId() !== $returnOrderId) {
            $this->returnOrder = null;
        }
        $this->returnOrderId = $returnOrderId;
    }

    public function getReturnOrder(): ReturnOrderEntity
    {
        if (!$this->returnOrder) {
            throw new AssociationNotLoadedException('returnOrder', $this);
        }

        return $this->returnOrder;
    }

    public function setReturnOrder(ReturnOrderEntity $returnOrder): void
    {
        $this->returnOrder = $returnOrder;
        $this->returnOrderId = $returnOrder->getId();
    }

    public function getOrderLineItemId(): ?string
    {
        return $this->orderLineItemId;
    }

    public function setOrderLineItemId(?string $orderLineItemId): void
    {
        if ($this->orderLineItem && $this->orderLineItem->getId() !== $orderLineItemId) {
            $this->orderLineItem = null;
        }
        $this->orderLineItemId = $orderLineItemId;
    }

    public function getOrderLineItem(): ?OrderLineItemEntity
    {
        if (!$this->orderLineItem && $this->orderLineItemId) {
            throw new AssociationNotLoadedException('orderLineItem', $this);
        }

        return $this->orderLineItem;
    }

    public function setOrderLineItem(?OrderLineItemEntity $orderLineItem): void
    {
        if ($orderLineItem) {
            $this->orderLineItemId = $orderLineItem->getId();
        }
        $this->orderLineItem = $orderLineItem;
    }
}
