<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\DemodataGeneration\Patcher;

use Pickware\DalBundle\EntityManager;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;

class ProductPatcher
{
    private EntityManager $entityManager;
    private NumberRangeValueGeneratorInterface $numberRangeValueGenerator;

    public function __construct(
        EntityManager $entityManager,
        NumberRangeValueGeneratorInterface $numberRangeValueGenerator
    ) {
        $this->entityManager = $entityManager;
        $this->numberRangeValueGenerator = $numberRangeValueGenerator;
    }

    public function patch(Context $context): void
    {
        $this->patchProductNumbers($context);
    }

    /**
     * Updates existing products by changing the product numbers to actual numbers from the 'product' number range.
     */
    private function patchProductNumbers(Context $context): void
    {
        $productIds = $this->entityManager->findIdsBy(ProductDefinition::class, [], $context);

        $payloads = [];
        foreach ($productIds as $productId) {
            $payload = ['id' => $productId];
            $payloads[] = $this->getProductPayload($context, $payload);

            if (count($payloads) >= 50) {
                $this->entityManager->update(ProductDefinition::class, $payloads, $context);
                $payloads = [];
            }
        }
        $this->entityManager->update(ProductDefinition::class, $payloads, $context);

        $this->updateOrderLineItemProductNumbers($context);
    }

    /**
     * Updates existing order line items by syncing the product order number from the payload to the actual product
     * number of the respective product entity.
     */
    private function updateOrderLineItemProductNumbers(Context $context): void
    {
        $orderLineItems = $this->entityManager->findAll(OrderLineItemDefinition::class, $context, ['product']);

        $payloads = [];
        /** @var OrderLineItemEntity $orderLineItem */
        foreach ($orderLineItems as $orderLineItem) {
            if ($orderLineItem->getType() !== LineItem::PRODUCT_LINE_ITEM_TYPE) {
                // Only update product numbers of line items of type "product"
                continue;
            }

            // When changing the "product" (in this case: the product number), reference ids must be part of the payload
            $payloads[] = [
                'id' => $orderLineItem->getId(),
                'productId' => $orderLineItem->getProductId(),
                'referencedId' => $orderLineItem->getProductId(),
                'payload' => array_merge(
                    $orderLineItem->getPayload(),
                    ['productNumber' => $orderLineItem->getProduct()->getProductNumber()],
                ),
            ];

            if (count($payloads) >= 50) {
                $this->entityManager->update(OrderLineItemDefinition::class, $payloads, $context);
                $payloads = [];
            }
        }

        if (count($payloads) > 0) {
            $this->entityManager->update(OrderLineItemDefinition::class, $payloads, $context);
        }
    }

    private function getProductPayload(Context $context, array $payload = []): array
    {
        $productNumber = $this->numberRangeValueGenerator->getValue('product', $context, null);

        return array_merge(
            [
                'productNumber' => $productNumber,
            ],
            $payload,
        );
    }
}
