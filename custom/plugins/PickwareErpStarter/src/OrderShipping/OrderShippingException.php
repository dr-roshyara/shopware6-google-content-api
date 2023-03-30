<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\OrderShipping;

use Exception;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\HttpUtils\JsonApi\JsonApiErrorSerializable;

class OrderShippingException extends Exception implements JsonApiErrorSerializable
{
    private const ERROR_CODE_NAMESPACE = 'PICKWARE_ERP__ORDER_SHIPPING__';
    public const ERROR_CODE_NOT_IN_LIVE_VERSION = self::ERROR_CODE_NAMESPACE . 'NOT_IN_LIVE_VERSION';
    public const ERROR_CODE_NOT_ENOUGH_STOCK = self::ERROR_CODE_NAMESPACE . 'NOT_ENOUGH_STOCK';
    public const ERROR_CODE_PRE_ORDER_SHIPPING_VALIDATION_ERROR = self::ERROR_CODE_NAMESPACE . 'PRE_ORDER_SHIPPING_VALIDATION_ERROR';

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

    public static function notInLiveVersion(): self
    {
        $jsonApiError = new JsonApiError([
            'code' => self::ERROR_CODE_NOT_IN_LIVE_VERSION,
            'title' => 'Not in live context version',
            'detail' => 'Shipping an order is only possible in live version context.',
            'meta' => [],
        ]);

        return new self($jsonApiError);
    }

    public static function preOrderShippingValidationErrors(JsonApiErrors $jsonApiErrors): self
    {
        $serializedErrors = array_map(
            fn (JsonApiError $error) => $error->jsonSerialize(),
            $jsonApiErrors->getErrors(),
        );
        $errorDetails = array_map(
            fn (JsonApiError $error) => $error->getDetail(),
            $jsonApiErrors->getErrors(),
        );
        $jsonApiError = new JsonApiError([
            'code' => self::ERROR_CODE_PRE_ORDER_SHIPPING_VALIDATION_ERROR,
            'title' => 'Pre order shipping validation errors occurred.',
            'detail' => sprintf(
                'Orders could not be shipped because the following validation errors occurred: %s',
                implode('. ', $errorDetails),
            ),
            'meta' => [
                'errors' => $serializedErrors,
            ],
        ]);

        return new self($jsonApiError);
    }
}
