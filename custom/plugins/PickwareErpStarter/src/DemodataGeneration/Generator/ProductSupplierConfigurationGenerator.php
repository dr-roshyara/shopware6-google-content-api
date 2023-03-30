<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\DemodataGeneration\Generator;

use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\Supplier\Model\ProductSupplierConfigurationDefinition;
use Pickware\PickwareErpStarter\Supplier\Model\SupplierDefinition;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\DemodataGeneratorInterface;

/**
 * This generator generates product-supplier-configurations.
 */
class ProductSupplierConfigurationGenerator implements DemodataGeneratorInterface
{
    private EntityManager $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function getDefinition(): string
    {
        return ProductSupplierConfigurationDefinition::class;
    }

    public function generate(int $number, DemodataContext $demoDataContext, array $options = []): void
    {
        $productSupplierConfigurations = $this->entityManager->findAll(
            ProductSupplierConfigurationDefinition::class,
            $demoDataContext->getContext(),
        );
        $supplierIds = $this->entityManager
            ->findAll(SupplierDefinition::class, $demoDataContext->getContext())->getKeys();

        $demoDataContext->getConsole()->progressStart($productSupplierConfigurations->count());
        $payloads = [];
        $numberOfWrittenItems = 0;
        foreach ($productSupplierConfigurations as $productSupplierConfiguration) {
            $payload = ['id' => $productSupplierConfiguration->getId()];
            if (count($supplierIds) > 0) {
                $payload['supplierId'] = $supplierIds[array_rand($supplierIds)];
            }
            $payloads[] = $this->getProductSupplierConfigurationPayload($demoDataContext, $payload);

            if (count($payloads) >= 50) {
                $this->entityManager->update(
                    ProductSupplierConfigurationDefinition::class,
                    $payloads,
                    $demoDataContext->getContext(),
                );
                $numberOfWrittenItems += count($payloads);
                $demoDataContext->getConsole()->progressAdvance($numberOfWrittenItems);
                $payloads = [];
            }
        }
        $this->entityManager->update(
            ProductSupplierConfigurationDefinition::class,
            $payloads,
            $demoDataContext->getContext(),
        );

        $demoDataContext->getConsole()->progressFinish();
        $demoDataContext->getConsole()->text(sprintf(
            '%s product supplier configurations have been updated and assigned to suppliers.',
            $productSupplierConfigurations->count(),
        ));
    }

    private function getProductSupplierConfigurationPayload(
        DemodataContext $demoDataContext,
        array $payload = []
    ): array {
        $faker = $demoDataContext->getFaker();

        $purchaseStepOptions = [
            1,
            5,
            10,
            25,
            50,
        ];
        $purchaseSteps = $purchaseStepOptions[array_rand($purchaseStepOptions)];
        // 5er steps in [5..50]
        $minPurchase = random_int(1, 10) * 5;

        return array_merge(
            [
                'minPurchase' => $minPurchase,
                'purchaseSteps' => $purchaseSteps,
                'supplierProductNumber' => sprintf(
                    '%s%s',
                    mb_strtoupper($faker->randomLetter),
                    random_int(10000, 99999),
                ),
            ],
            $payload,
        );
    }
}
