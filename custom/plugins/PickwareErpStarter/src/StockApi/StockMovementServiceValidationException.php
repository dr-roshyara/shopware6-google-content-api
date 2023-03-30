<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\StockApi;

use Exception;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrorSerializable;

class StockMovementServiceValidationException extends Exception implements JsonApiErrorSerializable
{
    private const ERROR_CODE_NAMESPACE = 'PICKWARE_ERP__STOCK_MOVEMENT_SERVICE_VALIDATION__';
    public const ERROR_CODE_INSUFFICIENT_STOCK_FOR_STOCK_MOVEMENT = self::ERROR_CODE_NAMESPACE . 'INSUFFICIENT_STOCK_FOR_STOCK_MOVEMENT';
    public const ERROR_CODE_INVALID_COMBINATION_OF_STOCK_LOCATIONS = self::ERROR_CODE_NAMESPACE . 'INVALID_COMBINATION_OF_STOCK_LOCATIONS';

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

    /**
     * @param string[] $productIds
     */
    public static function operationLeadsToNegativeStocks(array $stockLocationReferences, array $productIds): self
    {
        $locationsAsStrings = array_map(function (StockLocationReference $stockLocationReference) {
            return sprintf(
                '%s %s=%s',
                $stockLocationReference->getLocationTypeTechnicalName(),
                $stockLocationReference->getPrimaryKeyFieldName(),
                $stockLocationReference->getPrimaryKey(),
            );
        }, $stockLocationReferences);

        $jsonApiError = new JsonApiError([
            'code' => self::ERROR_CODE_INSUFFICIENT_STOCK_FOR_STOCK_MOVEMENT,
            'title' => 'Operation leads to negative stocks',
            'detail' => sprintf(
                'The attempted operation was aborted because it would have caused stock to drop below zero on the ' .
                'following stock locations: %s',
                implode(', ', $locationsAsStrings),
            ),
            'meta' => [
                'stockLocations' => array_values(array_map(
                    fn (StockLocationReference $stockLocationReference) => [
                        'locationTypeTechnicalName' => $stockLocationReference->getLocationTypeTechnicalName(),
                        'primaryKeyFieldName' => $stockLocationReference->getPrimaryKeyFieldName(),
                        'primaryKey' => $stockLocationReference->getPrimaryKey(),
                    ],
                    $stockLocationReferences,
                )),
                'productIds' => $productIds,
            ],
        ]);

        return new self(
            $jsonApiError,
        );
    }

    /**
     * @param array $invalidCombinations Array of array that must contain properties 'source' and 'destination'. E.g.:
     * [
     *   [
     *     'source' => 'order',
     *     'destination' => 'warehouse',
     *   ],
     * ]
     */
    public static function invalidCombinationOfSourceAndDestinationStockLocations(
        array $invalidCombinations
    ): StockMovementServiceValidationException {
        $uniqueInvalidCombinations = array_unique($invalidCombinations, SORT_REGULAR);
        $formattedCombinations = array_map(
            fn (array $combination): string => sprintf('Source: %s, destination: %s.', $combination['source'], $combination['destination']),
            $uniqueInvalidCombinations,
        );

        return new self(new JsonApiError([
            'code' => self::ERROR_CODE_INVALID_COMBINATION_OF_STOCK_LOCATIONS,
            'title' => 'Invalid combination of stock locations',
            'detail' => sprintf(
                'The attempted operation was aborted because at least one invalid combination of source stock ' .
                'location and destination stock location was used: %s',
                implode(' ', $formattedCombinations),
            ),
            'meta' => [
                'invalidCombinations' => $uniqueInvalidCombinations,
            ],
        ]));
    }
}
