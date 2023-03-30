<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\ReturnOrder;

use Exception;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\HttpUtils\JsonApi\JsonApiErrorSerializable;

class ReturnOrderException extends Exception implements JsonApiErrorSerializable
{
    private const ERROR_CODE_NAMESPACE = 'PICKWARE_ERP_RETURN_ORDER_BASIC_ADMINISTRATION_BUNDLE__RETURN_ORDER__';
    public const INVALID_VERSION_CONTEXT = self::ERROR_CODE_NAMESPACE . 'INVALID_VERSION_CONTEXT';
    public const MISSING_WAREHOUSE_ID = self::ERROR_CODE_NAMESPACE . 'MISSING_WAREHOUSE_ID';
    public const INVALID_QUANTITIES = self::ERROR_CODE_NAMESPACE . 'INVALID_QUANTITIES';
    public const ERRORS_DURING_CREATION = self::ERROR_CODE_NAMESPACE . 'ERRORS_DURING_CREATION';

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

    public static function errorsDuringCreation(JsonApiErrors $errors): self
    {
        return new self(new JsonApiError([
            'code' => self::ERRORS_DURING_CREATION,
            'title' => 'Multiple errors while creating the return order',
            'detail' => 'The return order could not be created because errors occurred',
            'meta' => ['errors' => $errors->jsonSerialize()],
        ]));
    }

    public static function invalidVersionContext(): self
    {
        return new self(new JsonApiError([
            'code' => self::INVALID_VERSION_CONTEXT,
            'title' => 'Invalid version context',
            'detail' => 'Creating a return order is only allowed in live version context.',
        ]));
    }

    public static function missingWarehouseIdForRestocked($lineItemId): self
    {
        return new self(new JsonApiError([
            'code' => self::MISSING_WAREHOUSE_ID,
            'title' => 'Missing warehouse id',
            'detail' => sprintf(
                'In order to restock the product with id "%s", a warehouse must be specified to which the product should ' .
                'be restocked via the property "warehouseId".',
                $lineItemId,
            ),
            'meta' => ['lineItemId' => $lineItemId],
        ]));
    }

    public static function invalidQuantities($lineItemId, $restockedQuantity, $writtenOffQuantity, $totalQuantity): self
    {
        return new self(new JsonApiError([
            'code' => self::INVALID_QUANTITIES,
            'title' => 'Invalid quantities for line items',
            'detail' => sprintf(
                'The sum of the number of units to be restocked and to be written off must not exceed the ' .
                'total quantity of units to be returned, but they do in line item with id "%s" ' .
                '(%u restocked, %u written off, %u total).',
                $lineItemId,
                $restockedQuantity,
                $writtenOffQuantity,
                $totalQuantity,
            ),
            'meta' => [
                'lineItemId' => $lineItemId,
                'restockedQuantity' => $restockedQuantity,
                'writtenOffQuantity' => $writtenOffQuantity,
                'totalQuantity' => $totalQuantity,
            ],
        ]));
    }
}
