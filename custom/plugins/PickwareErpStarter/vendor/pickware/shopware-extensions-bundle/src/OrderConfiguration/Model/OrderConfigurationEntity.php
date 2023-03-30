<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShopwareExtensionsBundle\OrderConfiguration\Model;

use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class OrderConfigurationEntity extends Entity
{
    use EntityIdTrait;

    protected string $orderId;
    protected string $orderVersionId;
    protected ?OrderEntity $order = null;
    protected ?string $primaryOrderDeliveryId = null;
    protected ?string $primaryOrderDeliveryVersionId = null;
    protected ?OrderDeliveryEntity $primaryOrderDelivery = null;
    protected ?string $primaryOrderTransactionId = null;
    protected ?string $primaryOrderTransactionVersionId = null;
    protected ?OrderTransactionEntity $primaryOrderTransaction = null;

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function setOrderId(string $orderId): void
    {
        if ($this->order->getId() !== $orderId) {
            $this->order = null;
        }
        $this->orderId = $orderId;
    }

    public function getOrderVersionId(): string
    {
        return $this->orderVersionId;
    }

    public function setOrderVersionId(string $orderVersionId): void
    {
        if ($this->order && $this->order->getVersionId() !== $orderVersionId) {
            $this->order = null;
        }
        $this->orderVersionId = $orderVersionId;
    }

    public function getOrder(): OrderEntity
    {
        if (!$this->order) {
            throw new AssociationNotLoadedException('order', $this);
        }

        return $this->order;
    }

    public function setOrder(OrderEntity $order): void
    {
        $this->order = $order;
        $this->orderId = $order->getId();
    }

    public function getPrimaryOrderDeliveryId(): ?string
    {
        return $this->primaryOrderDeliveryId;
    }

    public function setPrimaryOrderDeliveryId(?string $primaryOrderDeliveryId): void
    {
        $this->primaryOrderDeliveryId = $primaryOrderDeliveryId;
        if (!$primaryOrderDeliveryId || ($this->primaryOrderDelivery && $primaryOrderDeliveryId !== $this->primaryOrderDelivery->getId())) {
            $this->primaryOrderDelivery = null;
        }
    }

    public function getPrimaryOrderDeliveryVersionId(): ?string
    {
        return $this->primaryOrderDeliveryVersionId;
    }

    public function setPrimaryOrderDeliveryVersionId(?string $primaryOrderDeliveryVersionId): void
    {
        $this->primaryOrderDeliveryVersionId = $primaryOrderDeliveryVersionId;
        if (!$primaryOrderDeliveryVersionId || ($this->primaryOrderDelivery && $primaryOrderDeliveryVersionId !== $this->primaryOrderDelivery->getVersionId())) {
            $this->primaryOrderDelivery = null;
        }
    }

    public function getPrimaryOrderDelivery(): ?OrderDeliveryEntity
    {
        if ($this->primaryOrderDeliveryId && !$this->primaryOrderDelivery) {
            throw new AssociationNotLoadedException('primaryOrderDelivery', $this);
        }

        return $this->primaryOrderDelivery;
    }

    public function setPrimaryOrderDelivery(?OrderDeliveryEntity $orderDelivery): void
    {
        $this->primaryOrderDelivery = $orderDelivery;
        if ($orderDelivery) {
            $this->primaryOrderDeliveryId = $orderDelivery->getId();
            $this->primaryOrderDeliveryVersionId = $orderDelivery->getVersionId();
        } else {
            $this->primaryOrderDeliveryId = null;
            $this->primaryOrderDeliveryVersionId = null;
        }
    }

    public function getPrimaryOrderTransactionId(): ?string
    {
        return $this->primaryOrderTransactionId;
    }

    public function setPrimaryOrderTransactionId(?string $primaryOrderTransactionId): void
    {
        $this->primaryOrderTransactionId = $primaryOrderTransactionId;
        if (!$primaryOrderTransactionId || ($this->primaryOrderTransaction && $primaryOrderTransactionId !== $this->primaryOrderTransaction->getId())) {
            $this->primaryOrderTransaction = null;
        }
    }

    public function getPrimaryOrderTransactionVersionId(): ?string
    {
        return $this->primaryOrderTransactionVersionId;
    }

    public function setPrimaryOrderTransactionVersionId(?string $primaryOrderTransactionVersionId): void
    {
        $this->primaryOrderTransactionVersionId = $primaryOrderTransactionVersionId;
        if (!$primaryOrderTransactionVersionId || ($this->primaryOrderTransaction && $primaryOrderTransactionVersionId !== $this->primaryOrderTransaction->getVersionId())) {
            $this->primaryOrderTransaction = null;
        }
    }

    public function getPrimaryOrderTransaction(): ?OrderTransactionEntity
    {
        if ($this->primaryOrderTransactionId && !$this->primaryOrderTransaction) {
            throw new AssociationNotLoadedException('primaryOrderTransaction', $this);
        }

        return $this->primaryOrderTransaction;
    }

    public function setPrimaryOrderTransaction(?OrderTransactionEntity $orderTransaction): void
    {
        $this->primaryOrderTransaction = $orderTransaction;
        if ($orderTransaction) {
            $this->primaryOrderTransactionId = $orderTransaction->getId();
            $this->primaryOrderTransactionVersionId = $orderTransaction->getVersionId();
        } else {
            $this->primaryOrderTransactionId = null;
            $this->primaryOrderTransactionVersionId = null;
        }
    }
}
