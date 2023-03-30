<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Order\Model;

use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

/**
 * @deprecated will be removed with 3.0.0. Use the new order.pickwareErpOrderPickabilities extension instead.
 */
class OrderPickabilityViewEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $orderId;

    /**
     * @var OrderEntity|null
     */
    protected $order;

    /**
     * @var string
     */
    protected $orderPickabilityStatus;

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function setOrderId(string $orderId): void
    {
        if ($this->order && $this->order->getId() !== $orderId) {
            $this->order = null;
        }
        $this->orderId = $orderId;
    }

    public function getOrder(): OrderEntity
    {
        if (!$this->order) {
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

    public function getOrderPickabilityStatus(): string
    {
        return $this->orderPickabilityStatus;
    }

    public function setOrderPickabilityStatus(string $orderPickabilityStatus): void
    {
        $this->orderPickabilityStatus = $orderPickabilityStatus;
    }
}
