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

use Exception;
use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderCollection;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderDefinition;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderEntity;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderLineItemDefinition;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderLineItemEntity;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

/**
 * A factory class to create CalculatableOrders and CalculatableOrderLineItems from order-like entities.
 */
class CalculatableOrderFactory
{
    private EntityManager $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function createCalculatableOrderFromOrder(string $orderId, Context $context): CalculatableOrder
    {
        // To only fetch the order of the given version without a live-version-fallback, we need to add the version as a
        // filter manually.
        $filters = [
            'id' => $orderId,
            'versionId' => $context->getVersionId(),
        ];

        /** @var OrderEntity $orderEntity */
        $orderEntity = $this->entityManager->getOneBy(
            OrderDefinition::class,
            $filters,
            $context,
            ['lineItems.product'],
        );

        $order = new CalculatableOrder();
        $order->lineItems = array_values($orderEntity->getLineItems()->map(
            fn (OrderLineItemEntity $orderLineItemEntity) => $this->createOrderLineItemFromOrderLineItemEntity($orderLineItemEntity),
        ));
        $order->price = $orderEntity->getPrice();
        $order->shippingCosts = $orderEntity->getShippingCosts();

        return $order;
    }

    /**
     * Returns an CalculatableOrder for each ReturnOrder of the given Order.
     *
     * @return CalculatableOrder[]
     */
    public function createCalculatableOrdersFromReturnOrdersOfOrder(string $orderId, Context $context): array
    {
        // To only fetch return orders of the given version without a live-version-fallback, we need to add the version
        // as a filter manually.
        $filter = [
            'orderId' => $orderId,
            'versionId' => $context->getVersionId(),
        ];

        /** @var ReturnOrderCollection $returnOrders */
        $returnOrders = $this->entityManager->findBy(
            ReturnOrderDefinition::class,
            $filter,
            $context,
            ['lineItems'],
        );

        return array_values($returnOrders->map(
            fn (ReturnOrderEntity $returnOrder) => $this->createCalculatableOrderFromReturnOrderEntity($returnOrder),
        ));
    }

    private function createCalculatableOrderFromReturnOrderEntity(ReturnOrderEntity $returnOrder): CalculatableOrder
    {
        $order = new CalculatableOrder();
        $order->lineItems = array_values($returnOrder->getLineItems()->map(
            fn (ReturnOrderLineItemEntity $returnOrderLineItem) => $this->createOrderLineItemFromReturnOrderLineItemEntity($returnOrderLineItem),
        ));
        $order->price = $returnOrder->getPrice();
        $order->shippingCosts = new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection());

        return $order;
    }

    private function createOrderLineItemFromOrderLineItemEntity(OrderLineItemEntity $orderLineItemEntity): CalculatableOrderLineItem
    {
        $orderLineItem = new CalculatableOrderLineItem();
        $orderLineItem->type = $orderLineItemEntity->getType();
        $orderLineItem->label = $orderLineItemEntity->getLabel();
        $orderLineItem->price = $orderLineItemEntity->getPrice();
        $orderLineItem->quantity = $orderLineItemEntity->getQuantity();
        $orderLineItem->productId = $orderLineItemEntity->getProductId();
        $orderLineItem->productVersionId = $orderLineItemEntity->getProduct() ? $orderLineItemEntity->getProduct()->getVersionId() : null;
        $orderLineItem->payload = $orderLineItemEntity->getPayload();

        return $orderLineItem;
    }

    private function createOrderLineItemFromReturnOrderLineItemEntity(
        ReturnOrderLineItemEntity $returnOrderLineItemEntity
    ): CalculatableOrderLineItem {
        $orderLineItem = new CalculatableOrderLineItem();
        $orderLineItem->type = self::getOrderLineItemType($returnOrderLineItemEntity->getType());
        $orderLineItem->label = $returnOrderLineItemEntity->getName();
        $orderLineItem->price = $returnOrderLineItemEntity->getPrice();
        $orderLineItem->quantity = $returnOrderLineItemEntity->getQuantity();
        $orderLineItem->productId = $returnOrderLineItemEntity->getProductId();
        $orderLineItem->productVersionId = $returnOrderLineItemEntity->getProductVersionId();
        $orderLineItem->payload = [
            'productNumber' => $returnOrderLineItemEntity->getProductNumber(),
        ];

        return $orderLineItem;
    }

    private function getOrderLineItemType(?string $returnOrderLineItemType): ?string
    {
        switch ($returnOrderLineItemType) {
            case null:
                return null;
            case ReturnOrderLineItemDefinition::TYPE_PRODUCT:
                return LineItem::PRODUCT_LINE_ITEM_TYPE;
            case ReturnOrderLineItemDefinition::TYPE_CUSTOM:
                return LineItem::CUSTOM_LINE_ITEM_TYPE;
            case ReturnOrderLineItemDefinition::TYPE_CREDIT:
                return LineItem::CREDIT_LINE_ITEM_TYPE;
            case ReturnOrderLineItemDefinition::TYPE_PROMOTION:
                return LineItem::PROMOTION_LINE_ITEM_TYPE;
        }

        throw new Exception(sprintf(
            'ReturnOrder line item type "%s" cannot be converted to an order line item type.',
            $returnOrderLineItemType,
        ));
    }
}
