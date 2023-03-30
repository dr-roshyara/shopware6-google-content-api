<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\SupplierOrder\Document;

use Pickware\DalBundle\ContextFactory;
use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\SupplierOrder\Model\SupplierOrderDefinition;
use Pickware\PickwareErpStarter\SupplierOrder\SupplierOrderDocumentType;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigDefinition;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigEntity;
use Shopware\Core\Checkout\Document\DocumentConfigurationFactory;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\Language\LanguageEntity;

class SupplierOrderDocumentContentGenerator
{
    private EntityManager $entityManager;
    private ContextFactory $contextFactory;

    public function __construct(
        EntityManager $entityManager,
        ContextFactory $contextFactory
    ) {
        $this->entityManager = $entityManager;
        $this->contextFactory = $contextFactory;
    }

    public function generateFromSupplierOrder(
        string $supplierOrderId,
        string $languageId,
        Context $context
    ): array {
        $supplierOrder = $context->enableInheritance(
            function (Context $inheritanceContext) use ($languageId, $supplierOrderId) {
                $localizedContext = $this->contextFactory->createLocalizedContext($languageId, $inheritanceContext);

                return $this->entityManager->getByPrimaryKey(
                    SupplierOrderDefinition::class,
                    $supplierOrderId,
                    $localizedContext,
                    [
                        'currency',
                        'lineItems.product.manufacturer',
                        'lineItems.product.extensions.pickwareErpProductSupplierConfiguration',
                        'supplier.address',
                        'supplier.language.locale',
                        'warehouse.address',
                    ],
                );
            },
        );

        /** @var LanguageEntity $language */
        $language = $this->entityManager->getByPrimaryKey(LanguageDefinition::class, $languageId, $context, ['locale']);

        // Fetch the first supplier order document configuration that can be found. Since the document configuration is
        // global there should only be one. We do not use a specific given configuration, nor do we merge a sales
        // channel specific configuration.
        /** @var DocumentBaseConfigEntity $configuration */
        $configuration = $this->entityManager->findOneBy(
            DocumentBaseConfigDefinition::class,
            [
                'documentType.technicalName' => SupplierOrderDocumentType::TECHNICAL_NAME,
                'global' => true,
            ],
            $context,
            [
                'documentType',
                'logo',
            ],
        );

        return [
            'supplierOrder' => $supplierOrder,
            'localeCode' => $language->getLocale()->getCode(),
            'config' => DocumentConfigurationFactory::createConfiguration([], $configuration),
        ];
    }
}
