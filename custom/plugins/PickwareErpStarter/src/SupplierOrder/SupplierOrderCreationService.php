<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\SupplierOrder;

use DateTime;
use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\Config\Config;
use Pickware\PickwareErpStarter\PriceCalculation\OrderRecalculationService;
use Pickware\PickwareErpStarter\Product\ProductNameFormatterService;
use Pickware\PickwareErpStarter\PurchaseList\Model\PurchaseListItemCollection;
use Pickware\PickwareErpStarter\PurchaseList\Model\PurchaseListItemDefinition;
use Pickware\PickwareErpStarter\PurchaseList\Model\PurchaseListItemEntity;
use Pickware\PickwareErpStarter\Supplier\Model\SupplierEntity;
use Pickware\PickwareErpStarter\SupplierOrder\Model\SupplierOrderDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyDefinition;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Shopware\Core\System\StateMachine\StateMachineRegistry;

class SupplierOrderCreationService
{
    // We currently only support supplier order with tax status net
    private const SUPPLIER_ORDER_TAX_STATUS = CartPrice::TAX_STATE_NET;

    private EntityManager $entityManager;
    private Config $config;
    private NumberRangeValueGeneratorInterface $numberRangeValueGenerator;
    private StateMachineRegistry $stateMachineRegistry;
    private ProductNameFormatterService $productNameFormatterService;
    private OrderRecalculationService $orderRecalculationService;

    public function __construct(
        EntityManager $entityManager,
        Config $config,
        NumberRangeValueGeneratorInterface $numberRangeValueGenerator,
        StateMachineRegistry $stateMachineRegistry,
        ProductNameFormatterService $productNameFormatterService,
        OrderRecalculationService $orderRecalculationService
    ) {
        $this->entityManager = $entityManager;
        $this->config = $config;
        $this->numberRangeValueGenerator = $numberRangeValueGenerator;
        $this->stateMachineRegistry = $stateMachineRegistry;
        $this->productNameFormatterService = $productNameFormatterService;
        $this->orderRecalculationService = $orderRecalculationService;
    }

    /**
     * Creates supplier orders from purchase list items that fit the given criteria. Note that the criteria is applied
     * to the PurchaseListItemDefinition repository.
     */
    public function createSupplierOrdersFromPurchaseListItemCriteria(Criteria $criteria, Context $context): array
    {
        /** @var CurrencyEntity $currency */
        $currency = $this->entityManager->getByPrimaryKey(CurrencyDefinition::class, Defaults::CURRENCY, $context);
        // Only create supplier orders when the product has a respective supplier assigned. Products without suppliers
        // are ignored and no supplier orders can be created for them.
        $criteria->addFilter(new NotFilter(MultiFilter::CONNECTION_AND, [
            new EqualsFilter('product.pickwareErpProductSupplierConfiguration.supplierId', null),
        ]));
        $criteria->addAssociations([
            'product',
            'product.pickwareErpProductSupplierConfiguration.supplier.address',
            'product.tax',
        ]);

        $supplierOrderPayloadsBySupplierId = [];
        $purchaseListItemIdsToBeDeleted = [];

        // Use pagination to fetch the purchase list items in batches to reduce memory usage
        $page = 1;
        $batchSize = 50;
        $criteria->setLimit($batchSize);
        while (true) {
            $criteria->setOffset(($page - 1) * $batchSize);
            $page += 1;

            /** @var PurchaseListItemCollection $purchaseListItems */
            $purchaseListItems = $context->enableInheritance(function (Context $context) use ($criteria) {
                return $this->entityManager->findBy(
                    PurchaseListItemDefinition::class,
                    $criteria,
                    $context,
                );
            });

            if ($purchaseListItems->count() === 0) {
                break;
            }

            // To reduce entity manager calls, fetch and format all product names beforehand
            $productIds = array_map(
                fn (PurchaseListItemEntity $purchaseListItem): string => $purchaseListItem->getProductId(),
                $purchaseListItems->getElements(),
            );
            $productNamesById = $this->productNameFormatterService->getFormattedProductNames($productIds, [], $context);

            foreach ($purchaseListItems as $purchaseListItem) {
                $product = $purchaseListItem->getProduct();

                /** @var SupplierEntity $supplier */
                $supplier = $product->getExtension('pickwareErpProductSupplierConfiguration')->getSupplier();
                if (!array_key_exists($supplier->getId(), $supplierOrderPayloadsBySupplierId)) {
                    $supplierOrderPayloadsBySupplierId[$supplier->getId()] = $this->createSupplierOrderPayload(
                        $supplier,
                        $currency,
                        $context,
                    );
                }

                $prices = $product->getPurchasePrices();
                $price = $prices ? $prices->getCurrencyPrice(Defaults::CURRENCY) : null;
                // Use net purchase price since we currently only support creating supplier orders with tax status net
                $unitPrice = $price ? $price->getNet() : 0;
                $quantity = $purchaseListItem->getQuantity();
                $taxRules = new TaxRuleCollection([new TaxRule($product->getTax()->getTaxRate(), 100.0)]);
                $priceDefinition = new QuantityPriceDefinition($unitPrice, $taxRules, $quantity);
                $supplierOrderLineItemPayload = [
                    'productId' => $purchaseListItem->getProductId(),
                    'productVersionId' => $purchaseListItem->getVersionId(),
                    // The actual price will be calculated by the order recalculation service based on the price
                    // definition
                    'price' => new CalculatedPrice(
                        0.0,
                        0.0,
                        new CalculatedTaxCollection(),
                        new TaxRuleCollection(),
                        0,
                    ),
                    'priceDefinition' => $priceDefinition,
                    'productSnapshot' => [
                        'name' => $productNamesById[$purchaseListItem->getProductId()],
                        'productNumber' => $purchaseListItem->getProduct()->getProductNumber(),
                    ],
                ];
                $supplierOrderPayloadsBySupplierId[$supplier->getId()]['lineItems'][] = $supplierOrderLineItemPayload;

                $purchaseListItemIdsToBeDeleted[] = $purchaseListItem->getId();
            }
        }

        $this->entityManager->create(
            SupplierOrderDefinition::class,
            array_values($supplierOrderPayloadsBySupplierId),
            $context,
        );
        $this->entityManager->delete(PurchaseListItemDefinition::class, $purchaseListItemIdsToBeDeleted, $context);

        $supplierOrderIds = array_column($supplierOrderPayloadsBySupplierId, 'id');
        $this->orderRecalculationService->recalculateSupplierOrders($supplierOrderIds, $context);

        return $supplierOrderIds;
    }

    public function createSupplierOrdersFromPurchaseListItems(array $purchaseListItemIds, Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('id', $purchaseListItemIds));

        return $this->createSupplierOrdersFromPurchaseListItemCriteria($criteria, $context);
    }

    private function createSupplierOrderPayload(
        SupplierEntity $supplier,
        CurrencyEntity $currency,
        Context $context
    ): array {
        $initialSupplierOrderState = $this->stateMachineRegistry->getInitialState(
            SupplierOrderStateMachine::TECHNICAL_NAME,
            $context,
        );
        $initialSupplierOrderPaymentState = $this->stateMachineRegistry->getInitialState(
            SupplierOrderPaymentStateMachine::TECHNICAL_NAME,
            $context,
        );
        $defaultWarehouseId = $this->config->getDefaultWarehouseId();
        $number = $this->numberRangeValueGenerator->getValue(
            SupplierOrderNumberRange::TECHNICAL_NAME,
            $context,
            null,
        );

        return [
            'id' => Uuid::randomHex(),
            'supplierId' => $supplier->getId(),
            'supplierSnapshot' => [
                'name' => $supplier->getName(),
                'number' => $supplier->getNumber(),
                'email' => $supplier->getAddress() ? $supplier->getAddress()->getEmail() : null,
                'phone' => $supplier->getAddress() ? $supplier->getAddress()->getPhone() : null,
            ],
            'warehouseId' => $defaultWarehouseId,
            'currencyId' => $currency->getId(),
            'itemRounding' => $currency->getItemRounding()->getVars(),
            'totalRounding' => $currency->getTotalRounding()->getVars(),
            'stateId' => $initialSupplierOrderState->getId(),
            'paymentStateId' => $initialSupplierOrderPaymentState->getId(),
            'number' => $number,
            'orderDateTime' => new DateTime(),
            'lineItems' => [],
            // The actual price will be calculated by the order recalculation service based on its order line items
            'price' => new CartPrice(
                0,
                0,
                0,
                new CalculatedTaxCollection(),
                new TaxRuleCollection(),
                self::SUPPLIER_ORDER_TAX_STATUS,
            ),
        ];
    }
}
