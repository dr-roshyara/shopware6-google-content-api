<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Cache;

use Doctrine\DBAL\Connection;
use Pickware\PickwareErpStarter\Stock\ProductAvailableStockUpdatedEvent;
use Shopware\Core\Content\Product\SalesChannel\Detail\CachedProductDetailRoute;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CacheInvalidationSubscriber implements EventSubscriberInterface
{
    private Connection $connection;

    private CacheInvalidator $cacheInvalidator;

    public function __construct(
        Connection $connection,
        CacheInvalidator $cacheInvalidator
    ) {
        $this->connection = $connection;
        $this->cacheInvalidator = $cacheInvalidator;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductAvailableStockUpdatedEvent::EVENT_NAME => [
                'onProductAvailableStockUpdated',
                PHP_INT_MIN,
            ],
        ];
    }

    public function onProductAvailableStockUpdated(ProductAvailableStockUpdatedEvent $event): void
    {
        $this->invalidateProductCache($event->getProductIds());
    }

    private function invalidateProductCache(array $productIds): void
    {
        // Invalidate the store front api cache if the products stock or reserved stock was updated
        // and in turn the product availability was recalculated. For variant products the variant and main product
        // cache need to be invalidated.
        $parentIds = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(COALESCE(parent_id, id)))
                    FROM product
                    WHERE id in (:productIds) AND version_id = :version',
            [
                'productIds' => array_map('hex2bin', $productIds),
                'version' => hex2bin(Defaults::LIVE_VERSION),
            ],
            [
                'productIds' => Connection::PARAM_STR_ARRAY,
            ],
        );

        $productIds = array_merge($productIds, $parentIds);

        $this->invalidateDetailRoute($productIds);
        $this->invalidateProductIds($productIds);
    }

    private function invalidateDetailRoute(array $productIds): void
    {
        $this->cacheInvalidator->invalidate(
            array_map([CachedProductDetailRoute::class, 'buildName'], $productIds),
        );
    }

    private function invalidateProductIds(array $productIds): void
    {
        $this->cacheInvalidator->invalidate(
            array_map([EntityCacheKeyGenerator::class, 'buildProductTag'], $productIds),
        );
    }
}
