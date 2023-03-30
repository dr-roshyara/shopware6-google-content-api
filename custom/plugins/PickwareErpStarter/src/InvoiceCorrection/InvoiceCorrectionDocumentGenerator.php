<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\InvoiceCorrection;

use InvalidArgumentException;
use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\OrderCalculation\CalculatableOrder;
use Pickware\PickwareErpStarter\OrderCalculation\CalculatableOrderLineItem;
use Shopware\Core\Checkout\Document\DocumentConfiguration;
use Shopware\Core\Checkout\Document\DocumentGenerator\DocumentGeneratorInterface;
use Shopware\Core\Checkout\Document\Twig\DocumentTemplateRenderer;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

class InvoiceCorrectionDocumentGenerator implements DocumentGeneratorInterface
{
    public const DEFAULT_TEMPLATE = '@PickwareErpStarter/documents/invoice-correction.html.twig';

    private string $rootDir;
    private EntityManager $entityManager;
    private InvoiceCorrectionCalculator $invoiceCorrectionCalculator;
    private DocumentTemplateRenderer $documentTemplateRenderer;

    public function __construct(
        string $rootDir,
        EntityManager $entityManager,
        InvoiceCorrectionCalculator $invoiceCorrectionCalculator,
        DocumentTemplateRenderer $documentTemplateRenderer
    ) {
        $this->rootDir = $rootDir;
        $this->entityManager = $entityManager;
        $this->invoiceCorrectionCalculator = $invoiceCorrectionCalculator;
        $this->documentTemplateRenderer = $documentTemplateRenderer;
    }

    public function supports(): string
    {
        return InvoiceCorrectionDocumentType::TECHNICAL_NAME;
    }

    public function generate(
        OrderEntity $order,
        DocumentConfiguration $config,
        Context $context,
        ?string $templatePath = null
    ): string {
        $referencedDocumentId = $config->custom[InvoiceCorrectionDocumentConfigurationValidatingDocumentServiceDecorator::DOCUMENT_CONFIGURATION_REFERENCED_DOCUMENT_ID_KEY];

        // Note that the given OrderEntity $order is already at the version referenced by this document (or it is at the
        // live version if this document is a preview).
        $invoiceCorrection = $this->invoiceCorrectionCalculator->calculateInvoiceCorrection(
            $order->getId(),
            $referencedDocumentId,
            $order->getVersionId(),
            $context,
        );

        /** @var OrderEntity $order */
        $order = $this->entityManager->getByPrimaryKey(
            OrderDefinition::class,
            $order->getId(),
            $context->createWithVersionId($order->getVersionId()),
            [
                // Shopware default associations for the default order document template (do not remove)
                'lineItems',
                'transactions.paymentMethod',
                'currency',
                'language.locale',
                'addresses.country',
                'deliveries.positions',
                'deliveries.shippingMethod',
            ],
        );
        $this->applyInvoiceCorrectionToOrder($order, $invoiceCorrection);

        return $this->documentTemplateRenderer->render(
            $templatePath ?? self::DEFAULT_TEMPLATE,
            [
                'invoiceCorrection' => $order,
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
        if (!$config->documentNumber) {
            throw new InvalidArgumentException(
                'Document number ("documentNumber") is missing in document configuration.',
            );
        }
        if (!isset($config->custom[InvoiceCorrectionDocumentConfigurationValidatingDocumentServiceDecorator::DOCUMENT_CONFIGURATION_REFERENCED_INVOICE_NUMBER_KEY])) {
            throw new InvalidArgumentException(sprintf(
                'Referenced invoice number (configuration key: "%s") is missing in custom document configuration.',
                InvoiceCorrectionDocumentConfigurationValidatingDocumentServiceDecorator::DOCUMENT_CONFIGURATION_REFERENCED_INVOICE_NUMBER_KEY,
            ));
        }

        return vsprintf(
            '%s%s_to_invoice_%s%s',
            [
                $config->getFilenamePrefix(),
                $config->documentNumber,
                $config->custom[InvoiceCorrectionDocumentConfigurationValidatingDocumentServiceDecorator::DOCUMENT_CONFIGURATION_REFERENCED_INVOICE_NUMBER_KEY],
                $config->getFilenameSuffix(),
            ],
        );
    }

    /**
     * Use the base order entity with most of its associations unchanged (user, addresses etc.) but overwrite prices and
     * line items with the calculated invoice correction.
     */
    private function applyInvoiceCorrectionToOrder(
        OrderEntity $orderEntity,
        CalculatableOrder $invoiceCorrection
    ): void {
        $orderEntity->setPrice($invoiceCorrection->price);
        $orderEntity->setAmountNet($invoiceCorrection->price->getNetPrice());
        $orderEntity->setAmountTotal($invoiceCorrection->price->getTotalPrice());
        $orderEntity->setPositionPrice($invoiceCorrection->price->getPositionPrice());
        $orderEntity->setTaxStatus($invoiceCorrection->price->getTaxStatus());
        $orderEntity->setShippingTotal($invoiceCorrection->shippingCosts->getTotalPrice());
        $orderEntity->setShippingCosts($invoiceCorrection->shippingCosts);
        $orderEntity->setLineItems(new OrderLineItemCollection(array_map(
            fn (CalculatableOrderLineItem $orderLineItem) => $this->transformOrderLineItemToOrderLineItemEntity($orderLineItem, $orderEntity->getId()),
            $invoiceCorrection->lineItems,
        )));
    }

    private function transformOrderLineItemToOrderLineItemEntity(
        CalculatableOrderLineItem $orderLineItem,
        string $orderId
    ): OrderLineItemEntity {
        $orderLineItemEntity = new OrderLineItemEntity();
        // Set some required properties with default values
        $orderLineItemEntity->setId(Uuid::randomHex());
        $orderLineItemEntity->setPosition(0);
        $orderLineItemEntity->setGood(false);
        $orderLineItemEntity->setStackable(false);
        $orderLineItemEntity->setRemovable(false);

        $orderLineItemEntity->setOrderId($orderId);
        $orderLineItemEntity->setQuantity($orderLineItem->quantity);
        $orderLineItemEntity->setUnitPrice($orderLineItem->price->getUnitPrice());
        $orderLineItemEntity->setTotalPrice($orderLineItem->price->getTotalPrice());
        $orderLineItemEntity->setPrice($orderLineItem->price);
        $orderLineItemEntity->setLabel($orderLineItem->label);
        $orderLineItemEntity->setType($orderLineItem->type);
        $orderLineItemEntity->setProductId($orderLineItem->productId);
        $orderLineItemEntity->setReferencedId($orderLineItem->productId);
        $orderLineItemEntity->setIdentifier($orderLineItem->productId ?? Uuid::randomHex());
        $orderLineItemEntity->setPayload($orderLineItem->payload);

        return $orderLineItemEntity;
    }
}
