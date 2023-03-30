<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stock\ImportExportProfile\AbsoluteStock;

use Pickware\DalBundle\EntityManager;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\PickwareErpStarter\ImportExport\Importer;
use Pickware\PickwareErpStarter\ImportExport\ImportExportStateService;
use Pickware\PickwareErpStarter\ImportExport\ReadWrite\Csv\Validator;
use Pickware\PickwareErpStarter\Picking\PickingRequestSolver;
use Pickware\PickwareErpStarter\Stock\ImportExportProfile\StockImportCsvRowNormalizer;
use Pickware\PickwareErpStarter\Stock\ImportExportProfile\StockImporter;
use Pickware\PickwareErpStarter\StockApi\StockLocationReferenceFinder;
use Pickware\PickwareErpStarter\StockApi\StockMovementService;
use Pickware\PickwareErpStarter\Stocking\StockingStrategy;
use Shopware\Core\Framework\Context;

class AbsoluteStockImporter implements Importer
{
    public const TECHNICAL_NAME = 'absolute-stock';
    public const VALIDATION_SCHEMA = [
        '$id' => 'pickware-erp--import-export--absolute-stock-import',
        'type' => 'object',
        'additionalProperties' => false,
        'properties' => [
            'productNumber' => [
                'type' => 'string',
                'maxLength' => 64,
            ],
            'binLocationCode' => [
                'type' => 'string',
                'maxLength' => 255,
            ],
            'defaultBinLocation' => [
                'oneOf' => [
                    ['$ref' => '#/definitions/empty'],
                    ['type' => 'boolean'],
                ],
            ],
            'warehouseCode' => [
                'type' => 'string',
                'maxLength' => 255,
            ],
            'warehouseName' => [
                'type' => 'string',
                'maxLength' => 255,
            ],
            'stock' => [
                'oneOf' => [
                    ['$ref' => '#/definitions/empty'],
                    [
                        'type' => 'integer',
                        'minimum' => 0,
                    ],
                ],
            ],
            'reorderPoint' => [
                'oneOf' => [
                    ['$ref' => '#/definitions/empty'],
                    ['type' => 'integer'],
                ],
            ],
        ],
        'required' => [
            'productNumber',
        ],
        'definitions' => [
            'empty' => [
                'type' => 'string',
                'maxLength' => 0,
            ],
        ],
    ];

    private StockImporter $stockImporter;

    public function __construct(
        EntityManager $entityManager,
        StockMovementService $stockMovementService,
        StockImportCsvRowNormalizer $normalizer,
        StockLocationReferenceFinder $stockLocationReferenceFinder,
        ImportExportStateService $importExportStateService,
        PickingRequestSolver $pickingRequestSolver,
        StockingStrategy $stockingStrategy,
        AbsoluteStockChangeCalculator $absoluteStockChangeCalculator,
        int $batchSize
    ) {
        $validator = new Validator($normalizer, self::VALIDATION_SCHEMA);
        $this->stockImporter = new StockImporter(
            $entityManager,
            $stockMovementService,
            $normalizer,
            $stockLocationReferenceFinder,
            $importExportStateService,
            $pickingRequestSolver,
            $stockingStrategy,
            $absoluteStockChangeCalculator,
            $validator,
            $batchSize,
        );
    }

    public function importChunk(string $importId, int $nextRowNumberToRead, Context $context): ?int
    {
        return $this->stockImporter->importChunk($importId, $nextRowNumberToRead, $context);
    }

    public function validateHeaderRow(array $headerRow, Context $context): JsonApiErrors
    {
        return $this->stockImporter->validateHeaderRow($headerRow, $context);
    }

    public function validateConfig(array $config): JsonApiErrors
    {
        return $this->stockImporter->validateConfig($config);
    }
}
