<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\OrderShipping;

use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\PickwareErpStarter\Stocking\ProductQuantity;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseEntity;
use Shopware\Core\Checkout\Order\OrderEntity;

class NotEnoughStockException extends OrderShippingException
{
    /**
     * @var ProductQuantity[]
     */
    private array $stockShortage;

    /**
     * @param ProductQuantity[] $stockShortage
     */
    public function __construct(WarehouseEntity $warehouse, OrderEntity $order, array $stockShortage)
    {
        $this->stockShortage = $stockShortage;
        $jsonApiError = new JsonApiError([
            'code' => self::ERROR_CODE_NOT_ENOUGH_STOCK,
            'title' => 'Operation leads to negative stocks',
            'detail' => sprintf(
                'There is not enough stock in warehouse with ID=%s to ship the order with ID=%s',
                $warehouse->getId(),
                $order->getId(),
            ),
            'meta' => [
                'warehouseName' => $warehouse->getName(),
                'warehouseCode' => $warehouse->getCode(),
                'orderNumber' => $order->getOrderNumber(),
                'stockShortage' => $stockShortage,
            ],
        ]);

        parent::__construct($jsonApiError);
    }

    /**
     * @return ProductQuantity[] $stockShortage
     */
    public function getStockShortage(): array
    {
        return $this->stockShortage;
    }
}
