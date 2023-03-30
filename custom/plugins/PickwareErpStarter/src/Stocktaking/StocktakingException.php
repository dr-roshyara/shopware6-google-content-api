<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stocktaking;

use Exception;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrorSerializable;

class StocktakingException extends Exception implements JsonApiErrorSerializable
{
    private const JSON_ERROR_CODE_NAMESPACE = 'PICKWARE_ERP__STOCKTAKING__';
    private const JSON_ERROR_AT_LEAST_ONE_CODE_BIN_LOCATION_ALREADY_COUNTED = self::JSON_ERROR_CODE_NAMESPACE . 'AT_LEAST_ONE_BIN_LOCATION_ALREADY_COUNTED';
    private const JSON_ERROR_CODE_STOCKTAKE_NOT_ACTIVE = self::JSON_ERROR_CODE_NAMESPACE . 'STOCKTAKE_NOT_ACTIVE';
    private const JSON_ERROR_CODE_STOCKTAKE_ALREADY_COMPLETED = self::JSON_ERROR_CODE_NAMESPACE . 'STOCKTAKE_ALREADY_COMPLETED';

    public const EXCEPTION_CODE_STOCKTAKE_NOT_ACTIVE = 1;
    public const EXCEPTION_CODE_AT_LEAST_ONE_BIN_LOCATION_ALREADY_COUNTED = 2;
    public const EXCEPTION_CODE_STOCKTAKE_ALREADY_COMPLETED = 3;

    private JsonApiError $jsonApiError;

    public function __construct(JsonApiError $jsonApiError, int $code)
    {
        $this->jsonApiError = $jsonApiError;
        parent::__construct($jsonApiError->getDetail(), $code);
    }

    public static function stocktakeNotActive(string $stocktakeId, string $stocktakeTitle): self
    {
        $jsonApiError = new JsonApiError([
            'code' => self::JSON_ERROR_CODE_STOCKTAKE_NOT_ACTIVE,
            'title' => 'Stocktake not active',
            'detail' => sprintf(
                'The stocktake "%s" is not active.',
                $stocktakeTitle,
            ),
            'meta' => [
                'stocktakeId' => $stocktakeId,
                'stocktakeTitle' => $stocktakeTitle,
            ],
        ]);

        return new self($jsonApiError, self::EXCEPTION_CODE_STOCKTAKE_NOT_ACTIVE);
    }

    /**
     * @param string[] $binLocationCodes
     */
    public static function countingProcessForAtLeastOneBinLocationAlreadyExists(array $binLocationCodes): self
    {
        $jsonApiError = new JsonApiError([
            'code' => self::JSON_ERROR_AT_LEAST_ONE_CODE_BIN_LOCATION_ALREADY_COUNTED,
            'title' => 'At least one bin location already counted',
            'detail' => sprintf(
                'At least one of the following bin locations has already been counted: %s.',
                implode(', ', $binLocationCodes),
            ),
            'meta' => [
                'binLocationCodes' => $binLocationCodes,
            ],
        ]);

        return new self($jsonApiError, self::EXCEPTION_CODE_AT_LEAST_ONE_BIN_LOCATION_ALREADY_COUNTED);
    }

    public static function stocktakeAlreadyCompleted(string $stocktakeId, string $stocktakeTitle, string $stocktakeNumber, string $importExportId): self
    {
        $jsonApiError = new JsonApiError([
            'code' => self::JSON_ERROR_CODE_STOCKTAKE_ALREADY_COMPLETED,
            'title' => 'Stocktake already completed',
            'detail' => sprintf(
                'The stocktake "%s" (%s) is already completed. It can not be completed again.',
                $stocktakeTitle,
                $stocktakeNumber,
            ),
            'meta' => [
                'stocktakeId' => $stocktakeId,
                'stocktakeTitle' => $stocktakeTitle,
                'stocktakeNumber' => $stocktakeNumber,
                'importExportId' => $importExportId,
            ],
        ]);

        return new self($jsonApiError, self::EXCEPTION_CODE_STOCKTAKE_ALREADY_COMPLETED);
    }

    public function serializeToJsonApiError(): JsonApiError
    {
        return $this->jsonApiError;
    }
}
