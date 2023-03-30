<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Product;

use Pickware\DalBundle\EntityManager;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Content\Property\PropertyGroupEntity;
use Shopware\Core\Framework\Context;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class ProductNameFormatterService
{
    private const DEFAULT_BASE_TEMPLATE = '{{ name }}{{ renderedOptions | raw }}';
    private const DEFAULT_OPTIONS_TEMPLATE = ' ({{ options | join(", ") }})';

    private const DEFAULT_TEMPLATE_OPTIONS = [
        'baseTemplate' => self::DEFAULT_BASE_TEMPLATE,
        'optionsTemplate' => self::DEFAULT_OPTIONS_TEMPLATE,
    ];

    private EntityManager $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function getFormattedProductName(string $productId, array $templateOptions, Context $context): string
    {
        return $this->renderProductName(
            $this->getProductEntities([$productId], $context)->first(),
            array_merge(self::DEFAULT_TEMPLATE_OPTIONS, $templateOptions),
        );
    }

    public function getFormattedProductNames(array $productIds, array $templateOptions, Context $context): array
    {
        $uniqueProductIds = array_unique($productIds);
        $templateOptions = array_merge(self::DEFAULT_TEMPLATE_OPTIONS, $templateOptions);

        // Fetch products per batch while collecting all formatted product names to reduce peak memory usage.
        $formattedNames = [];
        $productIdsBatches = array_chunk($uniqueProductIds, 50);
        foreach ($productIdsBatches as $productIdsBatch) {
            $products = $this->getProductEntities($productIdsBatch, $context);
            $formattedNames = array_merge(
                $formattedNames,
                $products->map(fn (ProductEntity $product) => $this->renderProductName($product, $templateOptions)),
            );
        }

        return $formattedNames;
    }

    private function getProductEntities(array $productIds, Context $context): ProductCollection
    {
        /** @var ProductCollection $products */
        $products = $context->enableInheritance(function (Context $inheritanceContext) use ($productIds) {
            return $this->entityManager->findBy(
                ProductDefinition::class,
                ['id' => $productIds],
                $inheritanceContext,
                [
                    // Load this association to automatically fill the ProductEntity::variant property
                    'options.group',
                ],
            );
        });

        if ($products->count() !== count($productIds)) {
            throw ProductException::productsDoNotExist(array_diff($productIds, $products->getIds()));
        }

        return $products;
    }

    private function getProductName(ProductEntity $product): string
    {
        return $product->getTranslation('name') ?: ($product->getName() ?: '');
    }

    private function getProductOptionNames(ProductEntity $product): ?array
    {
        if (!$product->getParentId() || !$product->getOptions() || $product->getOptions()->count() === 0) {
            return null;
        }

        $groupedOptions = $product->getOptions()->groupByPropertyGroups();
        // Sorts the option groups by position
        $groupedOptions->sortByPositions();
        // Sorts the options inside the option groups
        $groupedOptions->sortByConfig();

        return array_merge(
            ...$groupedOptions->map(fn (PropertyGroupEntity $optionsGroup) => $optionsGroup->getOptions()->map(fn (PropertyGroupOptionEntity $option) => $option->getTranslation('name') ?: $option->getName())),
        );
    }

    private function renderProductName(ProductEntity $product, array $templateOptions): string
    {
        $twig = new Environment(
            new ArrayLoader([
                'baseTemplate' => $templateOptions['baseTemplate'],
                'optionsTemplate' => $templateOptions['optionsTemplate'],
            ]),
            [
                'strict_variables' => true,
                'cache' => false,
            ],
        );

        $renderedOptions = '';
        $productOptions = $this->getProductOptionNames($product);
        if ($productOptions) {
            $renderedOptions = $twig->render('optionsTemplate', [
                'options' => $productOptions,
            ]);
        }

        return $twig->render('baseTemplate', [
            'name' => $this->getProductName($product),
            'renderedOptions' => $renderedOptions,
        ]);
    }
}
