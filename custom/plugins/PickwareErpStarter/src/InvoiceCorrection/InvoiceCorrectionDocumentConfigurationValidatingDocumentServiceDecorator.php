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
use Shopware\Core\Checkout\Document\DocumentIdStruct;
use Shopware\Core\Checkout\Document\DocumentService as ShopwareDocumentService;
use Shopware\Core\Checkout\Document\GeneratedDocument;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Request;

/**
 * Decorates Shopware's DocumentService to validate the configuration when creating an invoice correction document.
 */
class InvoiceCorrectionDocumentConfigurationValidatingDocumentServiceDecorator extends ShopwareDocumentService
{
    public const DOCUMENT_CONFIGURATION_REFERENCED_DOCUMENT_ID_KEY = 'pickwareErpReferencedDocumentId';
    public const DOCUMENT_CONFIGURATION_REFERENCED_INVOICE_NUMBER_KEY = 'pickwareErpReferencedInvoiceDocumentNumber';

    private ShopwareDocumentService $decoratedInstance;
    private InvoiceStackService $invoiceStackService;
    private InvoiceCorrectionCalculator $invoiceCorrectionCalculator;

    /**
     * There is no parent constructor call which is acceptable since we do not call any parent functions.
     */
    public function __construct(
        ShopwareDocumentService $decoratedInstance,
        InvoiceStackService $invoiceStackService,
        InvoiceCorrectionCalculator $invoiceCorrectionCalculator
    ) {
        $this->decoratedInstance = $decoratedInstance;
        $this->invoiceStackService = $invoiceStackService;
        $this->invoiceCorrectionCalculator = $invoiceCorrectionCalculator;
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
        if ($documentTypeName === InvoiceCorrectionDocumentType::TECHNICAL_NAME) {
            if ($referencedDocumentId) {
                throw InvoiceCorrectionException::invalidDocumentConfiguration(sprintf(
                    'A document of type "%s" must not reference another document directly in the "referencedDocumentId"'
                    . ' property.',
                    InvoiceCorrectionDocumentType::TECHNICAL_NAME,
                ));
            }

            $referencedDocumentConfiguration = $this->getReferencedDocumentConfiguration($orderId, $context);
            $config->assign([
                'custom' => array_merge(
                    $config->custom,
                    $referencedDocumentConfiguration,
                ),
            ]);

            $this->validateNonEmptyInvoiceCorrection(
                $orderId,
                $referencedDocumentConfiguration[self::DOCUMENT_CONFIGURATION_REFERENCED_DOCUMENT_ID_KEY],
                $context->getVersionId(),
                $context,
            );
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

    public function getDocument(DocumentEntity $document, Context $context): GeneratedDocument
    {
        return $this->decoratedInstance->getDocument($document, $context);
    }

    public function preview(
        string $orderId,
        string $deepLinkCode,
        string $documentTypeName,
        string $fileType,
        DocumentConfiguration $config,
        Context $context
    ): GeneratedDocument {
        if ($documentTypeName === InvoiceCorrectionDocumentType::TECHNICAL_NAME) {
            $referencedDocumentConfiguration = $this->getReferencedDocumentConfiguration($orderId, $context);
            $config->assign([
                'custom' => array_merge(
                    $config->custom,
                    $referencedDocumentConfiguration,
                ),
            ]);

            $this->validateNonEmptyInvoiceCorrection(
                $orderId,
                $referencedDocumentConfiguration[self::DOCUMENT_CONFIGURATION_REFERENCED_DOCUMENT_ID_KEY],
                $context->getVersionId(),
                $context,
            );
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

    public function uploadFileForDocument(
        string $documentId,
        Context $context,
        Request $uploadedFileRequest
    ): DocumentIdStruct {
        return $this->decoratedInstance->uploadFileForDocument($documentId, $context, $uploadedFileRequest);
    }

    private function validateNonEmptyInvoiceCorrection(
        string $orderId,
        string $referencedDocumentId,
        string $newVersionId,
        Context $context
    ): void {
        $invoiceCorrection = $this->invoiceCorrectionCalculator->calculateInvoiceCorrection(
            $orderId,
            $referencedDocumentId,
            $newVersionId,
            $context,
        );

        if ($invoiceCorrection->isEmpty()) {
            throw InvoiceCorrectionException::invoiceCorrectionWouldBeEmpty();
        }
    }

    /**
     * Determines the latest open invoice stack and returns the latest invoice correction or invoice id of that stack,
     * as well as the invoice number of that stack.
     *
     * REMARK REGARDING THE RETURN ORDER MVP: If there are multiple open invoice stacks (which is a valid scenario for
     * the MVP) the latest invoice stack that already has an invoice correction is used. If no invoice correction exists
     * in any open invoice stack, the invoice stack of the latest invoice document is used instead.
     *
     * Also: a storno document can only be created when there are no invoice corrections in the invoice stack. This
     * behavior will change in the future.
     */
    private function getReferencedDocumentConfiguration(string $orderId, Context $context): array
    {
        $invoiceStacks = $this->invoiceStackService->getInvoiceStacksOfOrder($orderId, $context);
        $openInvoiceStacks = $invoiceStacks->filter(fn (InvoiceStack $invoiceStack) => $invoiceStack->isOpen);

        if (count($openInvoiceStacks) === 0) {
            // No open invoice stack was found, no invoice correction can be created
            throw InvoiceCorrectionException::noReferenceDocumentFound();
        }

        $openInvoiceStacksWithInvoiceCorrections = $openInvoiceStacks->filter(
            fn (InvoiceStack $invoiceStack) => $invoiceStack->hasInvoiceCorrections(),
        );
        if (count($openInvoiceStacksWithInvoiceCorrections) > 0) {
            // Use the latest invoice stack and reference the latest invoice correction document of that stack
            $relevantInvoiceStack = $openInvoiceStacksWithInvoiceCorrections->getLatest();

            return [
                self::DOCUMENT_CONFIGURATION_REFERENCED_DOCUMENT_ID_KEY => $relevantInvoiceStack->getLatestInvoiceCorrection()->id,
                self::DOCUMENT_CONFIGURATION_REFERENCED_INVOICE_NUMBER_KEY => $relevantInvoiceStack->invoice->number,
            ];
        }

        // If no invoice correction document exist in any open invoice stack, use the latest invoice stack instead
        // and reference the invoice document of that stack
        $relevantInvoiceStack = $openInvoiceStacks->getLatest();

        return [
            self::DOCUMENT_CONFIGURATION_REFERENCED_DOCUMENT_ID_KEY => $relevantInvoiceStack->invoice->id,
            self::DOCUMENT_CONFIGURATION_REFERENCED_INVOICE_NUMBER_KEY => $relevantInvoiceStack->invoice->number,
        ];
    }
}
