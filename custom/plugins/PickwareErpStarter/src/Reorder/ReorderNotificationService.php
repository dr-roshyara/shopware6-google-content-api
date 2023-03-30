<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Reorder;

use Doctrine\DBAL\Connection;
use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\Product\ProductNameFormatterService;
use Shopware\Core\Content\Flow\Dispatching\FlowDispatcher;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

class ReorderNotificationService
{
    private Connection $db;
    private EntityManager $entityManager;
    private FlowDispatcher $flowDispatcher;
    private ProductNameFormatterService $productNameFormatterService;

    public function __construct(
        Connection $db,
        EntityManager $entityManager,
        FlowDispatcher $flowDispatcher,
        ProductNameFormatterService $productNameFormatterService
    ) {
        $this->db = $db;
        $this->entityManager = $entityManager;
        $this->flowDispatcher = $flowDispatcher;
        $this->productNameFormatterService = $productNameFormatterService;
    }

    public function sendReorderNotification(Context $context): void
    {
        $products = $this->getReorderProducts($context);
        if ($products->count() === 0) {
            return;
        }

        $this->flowDispatcher->dispatch(
            new ReorderMailEvent($context, $products),
            ReorderMailEvent::EVENT_NAME,
        );
    }

    private function getReorderProducts(Context $context): ProductCollection
    {
        $productResult = $this->db->fetchAllAssociative(
            'SELECT
                product.id AS id,
                product.version_id as versionId
            FROM product
            LEFT JOIN pickware_erp_pickware_product AS pickwareProduct
                ON pickwareProduct.product_id = product.id
                AND pickwareProduct.product_version_id = product.version_id
            WHERE
                product.version_id = :liveVersionId
                AND product.stock <= pickwareProduct.reorder_point',
            [
                'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
            ],
        );
        if (count($productResult) === 0) {
            return new ProductCollection();
        }

        $criteria = (new Criteria())
            ->addFilter(
                new EqualsAnyFilter('id', array_map('bin2hex', array_column($productResult, 'id'))),
                new EqualsAnyFilter('versionId', array_map('bin2hex', array_column($productResult, 'versionId'))),
            )
            ->addAssociations([
                'pickwareErpPickwareProduct',
                'options',
            ])
            ->addSorting(new FieldSorting('name'));

        $productNames = [];
        $reorderProducts = $context->enableInheritance(function (Context $inheritanceContext) use ($criteria, &$productNames) {
            // Fetch ids to format names before fetching the full products to reduce memory usage peak
            $productIds = $this->entityManager->findIdsBy(ProductDefinition::class, $criteria, $inheritanceContext);
            $productNames = $this->productNameFormatterService->getFormattedProductNames($productIds, [], $inheritanceContext);

            return $this->entityManager->findBy(ProductDefinition::class, $criteria, $inheritanceContext);
        });
        foreach ($reorderProducts as $reorderProduct) {
            $reorderProduct->setName($productNames[$reorderProduct->getId()]);
        }

        return $reorderProducts;
    }
}
