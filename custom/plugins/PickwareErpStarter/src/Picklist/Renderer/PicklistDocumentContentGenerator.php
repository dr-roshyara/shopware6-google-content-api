<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Picklist\Renderer;

use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\Picking\PickingRequest;
use Pickware\PickwareErpStarter\Picking\ProductPickingRequest;
use Pickware\PickwareErpStarter\Stock\Model\StockDefinition;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;

class PicklistDocumentContentGenerator
{
    private const NUMBER_OF_DISPLAYED_PICK_LOCATIONS_PER_PRODUCT = 4;

    private const ALLOWED_ORDER_LINE_TYPES = [
        LineItem::PRODUCT_LINE_ITEM_TYPE,
        LineItem::CUSTOM_LINE_ITEM_TYPE,
    ];

    private EntityManager $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function createDocumentPickingRouteNodes(
        PickingRequest $pickingRequest,
        array $orderLineItemIds,
        Context $context
    ): array {
        $productContext = Context::createFrom($context);
        $productContext->setConsiderInheritance(true);

        $orderLineItems = $this->entityManager->findBy(
            OrderLineItemDefinition::class,
            ['id' => $orderLineItemIds],
            $context,
            [
                'product',
            ],
        );

        $allowedOrderLineItems = array_filter(
            $orderLineItems->getElements(),
            fn (OrderLineItemEntity $orderLineItem) => in_array($orderLineItem->getType(), self::ALLOWED_ORDER_LINE_TYPES),
        );

        $pickingNodes = array_filter(array_map(
            function (ProductPickingRequest $pickingRequest) use (
                $allowedOrderLineItems,
                $productContext,
                $context
            ) {
                return $this->mapPickLocationsFromPickingRequestToOrderLineItems(
                    $pickingRequest,
                    $allowedOrderLineItems,
                    $productContext,
                    $context,
                );
            },
            $pickingRequest->getElements(),
        ));

        foreach ($allowedOrderLineItems as $orderLineItem) {
            // If an order line item is a custom line item, or it got deleted it should be displayed on the picklist, with its quantity.
            // Therefore, we move it in front of the picklist.
            if ($orderLineItem->getType() == LineItem::CUSTOM_LINE_ITEM_TYPE || $orderLineItem->getProduct() == null) {
                array_unshift($pickingNodes, [
                    'orderLineItem' => $orderLineItem,
                    'stocks' => [],
                    'quantity' => $orderLineItem->getQuantity(),
                ]);
            }
        }

        return $pickingNodes;
    }

    private function mapPickLocationsFromPickingRequestToOrderLineItems(
        ProductPickingRequest $productPickingRequest,
        array $allowedOrderLineItems,
        Context $productContext,
        Context $context
    ): ?array {
        $stocks = [];
        foreach ($allowedOrderLineItems as $orderLineItem) {
            // We are only matching items by product id. It is technically possible that the order contains the
            // same product multiple times. We match the same product picking request in this case.
            if ($orderLineItem->getProductId() !== $productPickingRequest->getProductId()) {
                continue;
            }
            /** @var ProductEntity $product */
            $product = $this->entityManager->findByPrimaryKey(
                ProductDefinition::class,
                $productPickingRequest->getProductId(),
                $productContext,
                ['options.group'],
            );
            $orderLineItem->setProduct($product);
            // Truncate each picking request to only display the first n pick locations
            $pickLocations = array_slice(
                $productPickingRequest->getPickLocations(),
                0,
                self::NUMBER_OF_DISPLAYED_PICK_LOCATIONS_PER_PRODUCT,
            );
            foreach ($pickLocations as $pickLocation) {
                // Use a loop and single entity fetching to ensure the same order of stock locations
                $stocks[] = $this->entityManager->findByPrimaryKey(
                    StockDefinition::class,
                    $pickLocation->getStockId(),
                    $context,
                    ['binLocation'],
                );
            }

            return [
                'orderLineItem' => $orderLineItem,
                'stocks' => $stocks,
                // Note that the picking node quantity is the quantity of the product picking request.
                // This way we can display the "remaining order" on the picklist, which is accurate.
                'quantity' => $productPickingRequest->getQuantity(),
            ];
        }

        return null;
    }
}
