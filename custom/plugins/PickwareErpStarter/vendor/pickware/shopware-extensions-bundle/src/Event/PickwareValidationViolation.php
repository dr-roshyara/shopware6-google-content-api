<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShopwareExtensionsBundle\Event;

use Exception;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrorSerializable;
use Pickware\HttpUtils\JsonApi\JsonApiErrorSource;

/**
 * @deprecated tag:next-major. This class will be removed without successor.
 */
class PickwareValidationViolation extends Exception implements JsonApiErrorSerializable
{
    public const DEFAULT_ERROR_CODE = 'PICKWARE_SHOPWARE_EXTENSIONS__PICKWARE_VALIDATION_EVENT__VIOLATION';

    private JsonApiError $jsonApiError;

    /**
     * @param array{
     *     id: mixed|null,
     *     links: array|null,
     *     status: null|int|string,
     *     code: string|null,
     *     title: string|null,
     *     detail: string|null,
     *     source: JsonApiErrorSource|null,
     *     meta: array|null,
     * } $jsonApiErrorConfiguration
     */
    public function __construct(array $jsonApiErrorConfiguration)
    {
        $this->jsonApiError = new JsonApiError(array_merge(
            [
                'code' => self::DEFAULT_ERROR_CODE,
                'title' => 'Validation failed',
            ],
            $jsonApiErrorConfiguration,
        ));
        parent::__construct($this->jsonApiError->getDetail() ?? '');
    }

    public function serializeToJsonApiError(): JsonApiError
    {
        return $this->jsonApiError;
    }
}
