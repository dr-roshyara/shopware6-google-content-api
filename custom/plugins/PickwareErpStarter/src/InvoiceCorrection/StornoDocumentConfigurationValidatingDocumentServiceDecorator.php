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

use Pickware\PickwareErpStarter\InvoiceStack\InvoiceStack;
use Pickware\PickwareErpStarter\InvoiceStack\InvoiceStackService;
use Shopware\Core\Checkout\Document\DocumentConfiguration;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\Document\DocumentGenerator\StornoGenerator;
use Shopware\Core\Checkout\Document\DocumentIdStruct;
use Shopware\Core\Checkout\Document\DocumentService as ShopwareDocumentService;
use Shopware\Core\Checkout\Document\GeneratedDocument;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Request;

/**
 * Decorates Shopware's DocumentService to validate the configuration when creating an storno document.
 */
class StornoDocumentConfigurationValidatingDocumentServiceDecorator extends ShopwareDocumentService
{
    private ShopwareDocumentService $decoratedInstance;
    private InvoiceStackService $invoiceStackService;

    /**
     * There is no parent constructor call which is acceptable since we do not call any parent functions.
     */
    public function __construct(
        ShopwareDocumentService $decoratedInstance,
        InvoiceStackService $invoiceStackService
    ) {
        $this->decoratedInstance = $decoratedInstance;
        $this->invoiceStackService = $invoiceStackService;
    }

    public function create(
        string $orderId,
        string $documentTypeName,
        string $fileType,
        DocumentConfiguration $config,
        Context $context,
        ?string $referencedDocumentId = null,
        bool $static = false
    ): DocumentIdStruct {
        if ($documentTypeName === StornoGenerator::STORNO) {
            $this->validateNoInvoiceCorrectionForInvoiceExists($orderId, $config->custom['invoiceNumber'], $context);
        }

        return $this->decoratedInstance->create(
            $orderId,
            $documentTypeName,
            $fileType,
            $config,
            $context,
            $referencedDocumentId,
            $static,
        );
    }

    public function preview(
        string $orderId,
        string $deepLinkCode,
        string $documentTypeName,
        string $fileType,
        DocumentConfiguration $config,
        Context $context
    ): GeneratedDocument {
        if ($documentTypeName === StornoGenerator::STORNO) {
            $this->validateNoInvoiceCorrectionForInvoiceExists($orderId, $config->custom['invoiceNumber'], $context);
        }

        return $this->decoratedInstance->preview(
            $orderId,
            $deepLinkCode,
            $documentTypeName,
            $fileType,
            $config,
            $context,
        );
    }

    public function getDocument(DocumentEntity $document, Context $context): GeneratedDocument
    {
        return $this->decoratedInstance->getDocument($document, $context);
    }

    public function uploadFileForDocument(
        string $documentId,
        Context $context,
        Request $uploadedFileRequest
    ): DocumentIdStruct {
        return $this->decoratedInstance->uploadFileForDocument($documentId, $context, $uploadedFileRequest);
    }

    /**
     * Validates that no invoice correction exists in the invoice stack of the given order and invoice number. Because
     * no storno document should be created for an invoice that already has invoice corrections. Throws an
     * InvoiceCorrectionException if such an invoice correction exists.
     */
    private function validateNoInvoiceCorrectionForInvoiceExists(
        string $orderId,
        string $invoiceNumber,
        Context $context
    ): void {
        $invoiceStacks = $this->invoiceStackService->getInvoiceStacksOfOrder($orderId, $context);
        /** @var InvoiceStack $invoiceStack */
        foreach ($invoiceStacks as $invoiceStack) {
            if ($invoiceStack->invoice->number === $invoiceNumber && $invoiceStack->hasInvoiceCorrections()) {
                throw InvoiceCorrectionException::invoiceCorrectionForInvoiceExists($invoiceNumber);
            }
        }
    }
}
