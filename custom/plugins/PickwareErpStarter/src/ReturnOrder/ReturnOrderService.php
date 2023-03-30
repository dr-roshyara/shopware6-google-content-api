<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\ReturnOrder;

use Pickware\DalBundle\EntityManager;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderDefinition;
use Pickware\PickwareErpStarter\StockApi\StockLocationReference;
use Pickware\PickwareErpStarter\StockApi\StockMovement;
use Pickware\PickwareErpStarter\StockApi\StockMovementService;
use Pickware\ShopwareExtensionsBundle\StateTransitioning\StateTransitionService;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;

class ReturnOrderService
{
    private EntityManager $entityManager;
    private StockMovementService $stockMovementService;
    private StateTransitionService $stateTransitionService;

    public function __construct(
        EntityManager $entityManager,
        StockMovementService $stockMovementService,
        StateTransitionService $stateTransitionService
    ) {
        $this->entityManager = $entityManager;
        $this->stockMovementService = $stockMovementService;
        $this->stateTransitionService = $stateTransitionService;
    }

    public function placeReturnOrder(array $returnOrderPayload, string $userId, Context $context): void
    {
        $errors = new JsonApiErrors();
        if ($context->getVersionId() !== Defaults::LIVE_VERSION) {
            $errors->addErrors(ReturnOrderException::invalidVersionContext()->serializeToJsonApiError());
        }

        foreach ($returnOrderPayload['lineItems'] ?? [] as $lineItem) {
            if (isset($lineItem['restockedQuantity']) && $lineItem['restockedQuantity'] > 0 && !isset($returnOrderPayload['warehouseId'])) {
                $errors->addErrors(ReturnOrderException::missingWarehouseIdForRestocked($lineItem['id'])->serializeToJsonApiError());
            }

            if (($lineItem['restockedQuantity'] ?? 0) + ($lineItem['writtenOffQuantity'] ?? 0) > $lineItem['quantity']) {
                $errors->addErrors(ReturnOrderException::invalidQuantities(
                    $lineItem['id'],
                    $lineItem['restockedQuantity'],
                    $lineItem['writtenOffQuantity'],
                    $lineItem['quantity'],
                )->serializeToJsonApiError());
            }
        }

        if (count($errors) > 0) {
            throw ReturnOrderException::errorsDuringCreation($errors);
        }

        $this->entityManager->runInTransactionWithRetry(
            function () use ($context, $returnOrderPayload, $userId): void {
                $this->createStockMovementsForNewReturnOrder($returnOrderPayload, $userId, $context);
                $this->stateTransitionService->transitionState(
                    ReturnOrderDefinition::ENTITY_NAME,
                    $returnOrderPayload['id'],
                    ReturnOrderStateMachine::TRANSITION_COMPLETE,
                    'stateId',
                    $context,
                );
            },
        );
    }

    private function createStockMovementsForNewReturnOrder(array $returnOrderPayload, string $userId, Context $context): void
    {
        // Moves stock into the return order and from the return order into a warehouse or special stock location for each product line item.
        $stockMovements = [];
        $returnOrderId = $returnOrderPayload['id'];
        $orderId = $returnOrderPayload['orderId'];
        foreach ($returnOrderPayload['lineItems'] ?? [] as $lineItem) {
            if ($lineItem['type'] !== LineItem::PRODUCT_LINE_ITEM_TYPE) {
                // The line item is not associated with a product
                continue;
            }

            $productId = $lineItem['productId'] ?? $lineItem['product']['id'];
            if ($lineItem['quantity'] > 0) {
                $stockMovements[] = StockMovement::create([
                    'productId' => $productId,
                    'quantity' => $lineItem['quantity'],
                    'source' => StockLocationReference::order($orderId),
                    'destination' => StockLocationReference::returnOrder($returnOrderId),
                    'userId' => $userId,
                ]);
            }

            if (isset($lineItem['restockedQuantity']) && $lineItem['restockedQuantity'] > 0) {
                $stockMovements[] = StockMovement::create([
                    'productId' => $productId,
                    'quantity' => $lineItem['restockedQuantity'],
                    'source' => StockLocationReference::returnOrder($returnOrderId),
                    'destination' => StockLocationReference::warehouse($returnOrderPayload['warehouseId']),
                    'userId' => $userId,
                ]);
            }

            if (isset($lineItem['writtenOffQuantity']) && $lineItem['writtenOffQuantity'] > 0) {
                $stockMovements[] = StockMovement::create([
                    'productId' => $productId,
                    'quantity' => $lineItem['writtenOffQuantity'],
                    'source' => StockLocationReference::returnOrder($returnOrderId),
                    'destination' => StockLocationReference::unknown(),
                    'userId' => $userId,
                ]);
            }
        }

        $this->stockMovementService->moveStock($stockMovements, $context);
    }
}
