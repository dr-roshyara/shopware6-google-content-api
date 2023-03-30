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

use Exception;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrorSerializable;

class InvoiceCorrectionException extends Exception implements JsonApiErrorSerializable
{
    private const ERROR_CODE_NAMESPACE = 'PICKWARE_ERP__INVOICE_CORRECTION__';
    public const ERROR_CODE_INVALID_DOCUMENT_CONFIGURATION = self::ERROR_CODE_NAMESPACE . 'INVALID_DOCUMENT_CONFIGURATION';
    public const ERROR_CODE_INVOICE_CORRECTION_WOULD_BE_EMPTY = self::ERROR_CODE_NAMESPACE . 'INVOICE_CORRECTION_WOULD_BE_EMPTY';
    public const ERROR_CODE_NO_REFERENCE_DOCUMENT_FOUND = self::ERROR_CODE_NAMESPACE . 'NO_REFERENCE_DOCUMENT_FOUND';
    public const ERROR_CODE_INVOICE_CORRECTION_FOR_INVOICE_EXISTS = self::ERROR_CODE_NAMESPACE . 'INVOICE_CORRECTION_FOR_INVOICE_EXISTS';

    private JsonApiError $jsonApiError;

    public function __construct(JsonApiError $jsonApiError)
    {
        $this->jsonApiError = $jsonApiError;
        parent::__construct($jsonApiError->getDetail());
    }

    public function serializeToJsonApiError(): JsonApiError
    {
        return $this->jsonApiError;
    }

    public static function invalidDocumentconfiguration(string $message): self
    {
        return new self(new JsonApiError([
            'code' => self::ERROR_CODE_INVALID_DOCUMENT_CONFIGURATION,
            'title' => 'Invalid document configuration',
            'detail' => $message,
        ]));
    }

    public static function invoiceCorrectionWouldBeEmpty(): self
    {
        return new self(new JsonApiError([
            'code' => self::ERROR_CODE_INVOICE_CORRECTION_WOULD_BE_EMPTY,
            'title' => 'Invoice correction would be empty',
            'detail' => 'The calculated invoice correction would be empty. Therefore, it cannot be created.',
        ]));
    }

    public static function noReferenceDocumentFound(): self
    {
        return new self(new JsonApiError([
            'code' => self::ERROR_CODE_NO_REFERENCE_DOCUMENT_FOUND,
            'title' => 'No referenceable invoice document found',
            'detail' => 'No latest invoice correction or referenceable invoice, that has not been cancelled yet, could be found. Therefore, no invoice correction can be created.',
        ]));
    }

    public static function invoiceCorrectionForInvoiceExists(string $invoiceNumber): self
    {
        return new self(new JsonApiError([
            'code' => self::ERROR_CODE_INVOICE_CORRECTION_FOR_INVOICE_EXISTS,
            'title' => 'An invoice correction exists for the invoice',
            'detail' => sprintf(
                'At least one invoice correction exists for the invoice with number "%s". Therefore, no storno document can be created.',
                $invoiceNumber,
            ),
        ]));
    }
}
