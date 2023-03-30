<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stock\ImportExportProfile;

use Pickware\PickwareErpStarter\ImportExport\ReadWrite\Csv\CsvRowNormalizedColumnMapping;
use Pickware\PickwareErpStarter\ImportExport\ReadWrite\Csv\CsvRowNormalizer;
use Pickware\PickwareErpStarter\StockApi\StockLocationReferenceFinder;

class StockImportCsvRowNormalizer extends CsvRowNormalizer
{
    public function __construct()
    {
        $mapping = new CsvRowNormalizedColumnMapping([
            'productNumber' => [
                'produktnummer',
                'product number',
            ],
            'binLocationCode' => [
                'lagerplatz',
                'bin location',
            ],
            'warehouseCode' => [
                'warehouse code',
                'lagerkürzel',
            ],
            'warehouseName' => [
                'lager',
                'warehouse',
            ],
            'reorderPoint' => [
                'meldebestand',
                'reorder point',
            ],
            'stock' => [
                'stock',
                'bestand',
            ],
            'change' => [
                'change',
                'änderung',
            ],
            'defaultBinLocation' => [
                'default bin location',
                'standardlagerplatz',
            ],
        ]);
        parent::__construct($mapping);
    }

    public function normalizeRow(array $row): array
    {
        $row = parent::normalizeRow($row);

        $row = $this->mapAliases($row);
        $row = $this->mapTypes($row);

        return $row;
    }

    private function mapAliases(array $row): array
    {
        if (isset($row['binLocationCode'])
            && in_array(mb_strtolower($row['binLocationCode']), ['unknown', 'unbekannt'])
        ) {
            $row['binLocationCode'] = StockLocationReferenceFinder::BIN_LOCATION_CODE_UNKNOWN;
        }

        if (isset($row['defaultBinLocation']) && in_array(mb_strtolower($row['defaultBinLocation']), ['yes', 'ja'])) {
            $row['defaultBinLocation'] = 'true';
        }

        if (isset($row['defaultBinLocation']) && in_array(mb_strtolower($row['defaultBinLocation']), ['no', 'nein'])) {
            $row['defaultBinLocation'] = 'false';
        }

        return $row;
    }

    private function mapTypes(array $row): array
    {
        if (isset($row['defaultBinLocation']) && self::isBooleanString($row['defaultBinLocation'])) {
            $row['defaultBinLocation'] = $row['defaultBinLocation'] === 'true';
        }

        if (isset($row['stock']) && self::isIntegerString($row['stock'])) {
            $row['stock'] = (int)$row['stock'];
        }

        if (isset($row['change']) && self::isIntegerString($row['change'])) {
            $row['change'] = (int)$row['change'];
        }

        if (isset($row['reorderPoint']) && self::isIntegerString($row['reorderPoint'])) {
            $row['reorderPoint'] = (int)$row['reorderPoint'];
        }

        return $row;
    }
}
