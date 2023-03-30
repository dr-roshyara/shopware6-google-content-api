<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Picklist\DocumentGenerator;

use DateTime;
use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\Picklist\PicklistCustomProductGenerator;
use Pickware\PickwareErpStarter\Picklist\PicklistDocumentType;
use Pickware\PickwareErpStarter\Picklist\PicklistException;
use Pickware\PickwareErpStarter\Picklist\PicklistGenerator;
use Pickware\PickwareErpStarter\Picklist\Renderer\PicklistDocumentContentGenerator;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseDefinition;
use Shopware\Core\Checkout\Document\DocumentConfiguration;
use Shopware\Core\Checkout\Document\DocumentConfigurationFactory;
use Shopware\Core\Checkout\Document\DocumentGenerator\DocumentGeneratorInterface;
use Shopware\Core\Checkout\Document\Twig\DocumentTemplateRenderer;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

class PicklistDocumentGenerator implements DocumentGeneratorInterface
{
    public const DEFAULT_TEMPLATE = '@PickwareErpStarter/documents/picklist.html.twig';

    private string $rootDir;
    private EntityManager $entityManager;
    private DocumentTemplateRenderer $documentTemplateRenderer;
    private PicklistGenerator $picklistGenerator;
    private PicklistCustomProductGenerator $picklistCustomProductGenerator;
    private PicklistDocumentContentGenerator $picklistDocumentContentGenerator;

    public function __construct(
        string $rootDir,
        EntityManager $entityManager,
        PicklistGenerator $picklistGenerator,
        PicklistCustomProductGenerator $picklistCustomProductGenerator,
        PicklistDocumentContentGenerator $picklistDocumentContentGenerator,
        DocumentTemplateRenderer $documentTemplateRenderer
    ) {
        $this->rootDir = $rootDir;
        $this->entityManager = $entityManager;
        $this->picklistGenerator = $picklistGenerator;
        $this->picklistCustomProductGenerator = $picklistCustomProductGenerator;
        $this->documentTemplateRenderer = $documentTemplateRenderer;
        $this->picklistDocumentContentGenerator = $picklistDocumentContentGenerator;
    }

    public function supports(): string
    {
        return PicklistDocumentType::TECHNICAL_NAME;
    }

    public function generate(
        OrderEntity $order,
        DocumentConfiguration $config,
        Context $context,
        ?string $templatePath = null
    ): string {
        $warehouseId = $this->getRequiredConfigParameter('warehouseId', $config);
        $warehouse = $this->entityManager->getByPrimaryKey(WarehouseDefinition::class, $warehouseId, $context);
        $picklistPickingRequest = $this->picklistGenerator->generatePicklist($warehouseId, $order->getId(), $context);

        $config = DocumentConfigurationFactory::mergeConfiguration($config, new DocumentConfiguration())->jsonSerialize();

        /** @var OrderEntity $order */
        $order = $this->entityManager->getByPrimaryKey(
            OrderDefinition::class,
            $order->getId(),
            $context,
            [
                // Shopware default associations (do not remove)
                'lineItems',
                'transactions.paymentMethod',
                'currency',
                'language.locale',
                'addresses.country',
                'deliveries.positions',
                'deliveries.shippingMethod',
                // Additional associations
                'salesChannel',
                'addresses.salutation',
            ],
        );

        $customProducts = $this->picklistCustomProductGenerator->generatorCustomProductDefinitions(
            $order->getLineItems(),
        );

        return $this->documentTemplateRenderer->render(
            $templatePath ?? self::DEFAULT_TEMPLATE,
            [
                'order' => $order,
                'warehouse' => $warehouse,
                'pickingRouteNodes' => $this->picklistDocumentContentGenerator->createDocumentPickingRouteNodes(
                    $picklistPickingRequest,
                    $order->getLineItems()->getIds(),
                    $context,
                ),
                'customProducts' => $customProducts,
                'config' => $config,
                'rootDir' => $this->rootDir,
                'context' => $context,
            ],
            $context,
            $order->getSalesChannelId(),
            $order->getLanguageId(),
            $order->getLanguage()->getLocale()->getCode(),
        );
    }

    public function getFileName(DocumentConfiguration $config): string
    {
        return vsprintf(
            '%sorder%s_%s%s',
            [
                $config->getFilenamePrefix(),
                $this->getRequiredConfigParameter('orderNumber', $config),
                (new DateTime($config->documentDate ?? 'now'))->format('Y-m-d-H_i_s'),
                $config->getFilenameSuffix(),
            ],
        );
    }

    private function getRequiredConfigParameter(string $parameterName, DocumentConfiguration $config)
    {
        if (!isset($config->$parameterName)) {
            throw PicklistException::configurationParameterMissing($parameterName);
        }

        return $config->$parameterName;
    }
}
