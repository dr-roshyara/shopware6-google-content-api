<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Picking;

use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderLineItemDefinition;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderLineItemEntity;
use Pickware\PickwareErpStarter\Stock\Model\StockDefinition;
use Pickware\PickwareErpStarter\Stock\Model\StockEntity;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;

class PickingRequestFactory
{
    private EntityManager $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function createPickingRequestsForOrder(string $orderId, Context $context): PickingRequest
    {
        $orderLineItemCriteria = new Criteria();
        $orderLineItemCriteria
            ->addFilter(new EqualsFilter('orderId', $orderId))
            ->addFilter(new EqualsFilter('type', LineItem::PRODUCT_LINE_ITEM_TYPE))
            ->addFilter(new NotFilter('AND', [new EqualsFilter('productId', null)]))
            ->addAssociation('product');
        /** @var OrderLineItemEntity[] $orderLineItems */
        $orderLineItems = $this->entityManager->findBy(
            OrderLineItemDefinition::class,
            $orderLineItemCriteria,
            $context,
        );

        $returnOrderLineItemCriteria = new Criteria();
        $returnOrderLineItemCriteria
            ->addFilter(new EqualsFilter('returnOrder.orderId', $orderId))
            ->addFilter(new EqualsFilter('type', ReturnOrderLineItemDefinition::TYPE_PRODUCT))
            ->addFilter(new NotFilter('AND', [new EqualsFilter('productId', null)]));
        /** @var ReturnOrderLineItemEntity[] $returnOrderLineItems */
        $returnOrderLineItems = $this->entityManager->findBy(
            ReturnOrderLineItemDefinition::class,
            $returnOrderLineItemCriteria,
            $context,
        );

        /** @var StockEntity[] $orderStocks */
        $orderStocks = $this->entityManager->findBy(StockDefinition::class, ['orderId' => $orderId], $context);

        $quantitiesToPick = [];
        $productNumbers = [];
        foreach ($orderLineItems as $orderLineItem) {
            $quantity = $quantitiesToPick[$orderLineItem->getProductId()] ?? 0;
            $quantitiesToPick[$orderLineItem->getProductId()] = $quantity + $orderLineItem->getQuantity();
            $productNumbers[$orderLineItem->getProductId()] = $orderLineItem->getProduct()->getProductNumber();
        }
        foreach ($returnOrderLineItems as $returnOrderLineItem) {
            $quantity = $quantitiesToPick[$returnOrderLineItem->getProductId()] ?? 0;
            $quantitiesToPick[$returnOrderLineItem->getProductId()] = $quantity - $returnOrderLineItem->getQuantity();
        }
        foreach ($orderStocks as $orderStock) {
            $quantity = $quantitiesToPick[$orderStock->getProductId()] ?? 0;
            $quantitiesToPick[$orderStock->getProductId()] = $quantity - $orderStock->getQuantity();
        }

        $productPickingRequests = [];
        foreach ($quantitiesToPick as $productId => $quantityToPick) {
            if ($quantityToPick <= 0) {
                continue;
            }
            $productPickingRequests[] = new ProductPickingRequest(
                $productId,
                max(0, $quantityToPick),
                [],
                $orderId,
                ['productNumber' => $productNumbers[$productId] ?? null],
            );
        }

        return new PickingRequest($productPickingRequests);
    }
}
