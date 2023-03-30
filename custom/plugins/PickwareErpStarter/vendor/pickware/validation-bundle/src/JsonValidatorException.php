<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ValidationBundle;

use Exception;
use JsonException;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrorSerializable;
use Throwable;

class JsonValidatorException extends Exception implements JsonApiErrorSerializable
{
    private const ERROR_CODE_NAMESPACE = 'PICKWARE_VALIDATION_BUNDLE__JSON_VALIDATOR__';

    public const INVALID_JSON = self::ERROR_CODE_NAMESPACE . 'INVALID_JSON';
    public const JSON_DOES_NOT_VALIDATE_AGAINST_SCHEMA = self::ERROR_CODE_NAMESPACE . 'JSON_DOES_NOT_VALIDATE_AGAINST_SCHEMA';

    private JsonApiError $jsonApiError;

    public function __construct(JsonApiError $jsonApiError, ?Throwable $previous = null)
    {
        $this->jsonApiError = $jsonApiError;
        parent::__construct($jsonApiError->getDetail(), 0, $previous);
    }

    public function serializeToJsonApiError(): JsonApiError
    {
        return $this->jsonApiError;
    }

    public static function invalidJson(JsonException $previous): self
    {
        return new self(
            new JsonApiError([
                'code' => self::INVALID_JSON,
                'title' => 'Invalid JSON',
                'detail' => 'JSON could not be parsed as the syntax is invalid.',
            ]),
            $previous,
        );
    }

    public static function jsonDoesNotValidateAgainstSchema(array $errors, array $data): self
    {
        $detail = "JSON does not validate against schema:\n";
        foreach ($errors as $error) {
            $detail .= sprintf("[%s] %s\n", $error['property'], $error['message']);
        }

        return new self(new JsonApiError([
            'code' => self::JSON_DOES_NOT_VALIDATE_AGAINST_SCHEMA,
            'title' => 'JSON does not validate against schema',
            'detail' => $detail,
            'meta' => [
                'errors' => $errors,
                'data' => $data,
            ],
        ]));
    }
}
