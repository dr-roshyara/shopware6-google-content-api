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
use Pickware\DocumentBundle\Model\DocumentCollection;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseEntity;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\User\UserEntity;

class ReturnOrderEntity extends Entity
{
    use EntityIdTrait;

    protected CartPrice $price;
    protected float $amountTotal;
    protected float $amountNet;
    protected float $positionPrice;
    protected string $taxStatus;
    protected ?string $internalComment;
    protected string $number;
    protected ?ReturnOrderLineItemCollection $lineItems = null;
    protected ?DocumentCollection $documents = null;
    protected ?ReturnOrderRefundEntity $refund = null;
    protected string $stateId;
    protected ?StateMachineStateEntity $state = null;
    protected string $orderId;
    protected ?OrderEntity $order = null;
    protected ?string $warehouseId;
    protected ?WarehouseEntity $warehouse = null;
    protected ?string $userId;
    protected ?UserEntity $user = null;

    public function getPrice(): CartPrice
    {
        return $this->price;
    }

    public function setPrice(CartPrice $price): void
    {
        $this->price = $price;
    }

    public function getAmountTotal(): float
    {
        return $this->amountTotal;
    }

    public function setAmountTotal(float $amountTotal): void
    {
        $this->amountTotal = $amountTotal;
    }

    public function getAmountNet(): float
    {
        return $this->amountNet;
    }

    public function setAmountNet(float $amountNet): void
    {
        $this->amountNet = $amountNet;
    }

    public function getPositionPrice(): float
    {
        return $this->positionPrice;
    }

    public function setPositionPrice(float $positionPrice): void
    {
        $this->positionPrice = $positionPrice;
    }

    public function getTaxStatus(): string
    {
        return $this->taxStatus;
    }

    public function setTaxStatus(string $taxStatus): void
    {
        $this->taxStatus = $taxStatus;
    }

    public function getInternalComment(): ?string
    {
        return $this->internalComment;
    }

    public function setInternalComment(?string $internalComment): void
    {
        $this->internalComment = $internalComment;
    }

    public function getNumber(): string
    {
        return $this->number;
    }

    public function setNumber(string $number): void
    {
        $this->number = $number;
    }

    public function getLineItems(): ReturnOrderLineItemCollection
    {
        if (!$this->lineItems) {
            throw new AssociationNotLoadedException('lineItems', $this);
        }

        return $this->lineItems;
    }

    public function setLineItems(ReturnOrderLineItemCollection $lineItems): void
    {
        $this->lineItems = $lineItems;
    }

    public function getDocuments(): DocumentCollection
    {
        if (!$this->documents) {
            throw new AssociationNotLoadedException('documents', $this);
        }

        return $this->documents;
    }

    public function setDocuments(DocumentCollection $documents): void
    {
        $this->documents = $documents;
    }

    public function getRefund(): ?ReturnOrderRefundEntity
    {
        return $this->refund;
    }

    public function setRefund(?ReturnOrderRefundEntity $refund): void
    {
        $this->refund = $refund;
    }

    public function getStateId(): string
    {
        return $this->stateId;
    }

    public function setStateId(string $stateId): void
    {
        if ($this->state && $this->state->getId() !== $stateId) {
            $this->state = null;
        }
        $this->stateId = $stateId;
    }

    public function getState(): StateMachineStateEntity
    {
        if (!$this->state && $this->stateId) {
            throw new AssociationNotLoadedException('state', $this);
        }

        return $this->state;
    }

    public function setState(StateMachineStateEntity $state): void
    {
        $this->state = $state;
        $this->stateId = $state->getId();
    }

    public function getOrderId(): ?string
    {
        return $this->orderId;
    }

    public function setOrderId(?string $orderId): void
    {
        if ($this->order && $this->order->getId() !== $orderId) {
            $this->order = null;
        }
        $this->orderId = $orderId;
    }

    public function getOrder(): ?OrderEntity
    {
        if (!$this->order && $this->orderId) {
            throw new AssociationNotLoadedException('order', $this);
        }

        return $this->order;
    }

    public function setOrder(?OrderEntity $order): void
    {
        if ($order) {
            $this->orderId = $order->getId();
        }
        $this->order = $order;
    }

    public function getWarehouseId(): ?string
    {
        return $this->warehouseId;
    }

    public function setWarehouseId(?string $warehouseId): void
    {
        if (!$warehouseId || ($this->warehouse && $this->warehouse->getId() !== $warehouseId)) {
            $this->warehouse = null;
        }
        $this->warehouseId = $warehouseId;
    }

    public function getWarehouse(): ?WarehouseEntity
    {
        if (!$this->warehouse && $this->warehouseId) {
            throw new AssociationNotLoadedException('warehouse', $this);
        }

        return $this->warehouse;
    }

    public function setWarehouse(?WarehouseEntity $warehouse): void
    {
        $this->warehouseId = $warehouse ? $warehouse->getId() : null;
        $this->warehouse = $warehouse;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(?string $userId): void
    {
        if ($this->user && $this->user->getId() !== $userId) {
            $this->user = null;
        }
        $this->userId = $userId;
    }

    public function getUser(): ?UserEntity
    {
        if (!$this->user && $this->userId) {
            throw new AssociationNotLoadedException('user', $this);
        }

        return $this->user;
    }

    public function setUser(?UserEntity $user): void
    {
        if ($user) {
            $this->userId = $user->getId();
        }
        $this->user = $user;
    }
}
