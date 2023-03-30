<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stock\ContainerOverride;

use Doctrine\DBAL\Connection;
use Pickware\PickwareErpStarter\Stock\ProductSalesUpdater;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Content\Product\DataAbstractionLayer\StockUpdater;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\System\StateMachine\Event\StateMachineTransitionEvent;

/**
 * Override for \Shopware\Core\Content\Product\DataAbstractionLayer\StockUpdater to disable all
 * of its functionality except the updating of product.sales. Stock will be updated by our indexers.
 *
 * The product.sales update is kept in this subscriber. See this issue: https://github.com/pickware/shopware-plugins/issues/2852
 * The update itself is done asynchronously and periodically in a scheduled task. See this isse: https://github.com/pickware/shopware-plugins/issues/3408
 */
class StockUpdaterOverride extends StockUpdater
{
    private ?Connection $connection;
    private ?ProductSalesUpdater $productSalesUpdater;

    /**
     * @internal
     * @deprecated next major version: Both arguments will be non-optional
     */
    public function __construct(
        ?Connection $connection = null,
        $productSalesUpdater = null
    ) {
        $this->connection = $connection;

        if ($productSalesUpdater instanceof ProductSalesUpdater) {
            // Backwards compatibility: the second parameter of this subscriber (override) changed to ProductSalesUpdater
            $this->productSalesUpdater = $productSalesUpdater;
        } else {
            $this->productSalesUpdater = null;
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            StateMachineTransitionEvent::class => 'stateChanged',
        ];
    }

    public function triggerChangeSet(PreWriteValidationEvent $event): void
    {
    }

    public function lineItemWritten(EntityWrittenEvent $event): void
    {
    }

    public function stateChanged(StateMachineTransitionEvent $event): void
    {
        if (!$this->connection || !$this->productSalesUpdater) {
            return;
        }

        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }
        if ($event->getEntityName() !== OrderDefinition::ENTITY_NAME) {
            return;
        }

        $productIds = $this->connection->fetchFirstColumn(
            'SELECT HEX(`product_id`) FROM `order_line_item`
            WHERE
                `order_line_item`.`type` = :lineItemType
                AND `order_line_item`.`version_id` = :liveVersionId
                AND `order_line_item`.`order_id` = :orderId
                AND `order_line_item`.`product_id` IS NOT NULL;',
            [
                'orderId' => hex2bin($event->getEntityId()),
                'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
                'lineItemType' => LineItem::PRODUCT_LINE_ITEM_TYPE,
            ],
        );

        $this->productSalesUpdater->addProductsToUpdateQueue($productIds);
    }

    public function update(array $ids, Context $context): void
    {
    }

    public function orderPlaced(CheckoutOrderPlacedEvent $event): void
    {
    }
}
