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

use InvalidArgumentException;
use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\Warehouse\Model\BinLocationDefinition;
use Pickware\PickwareErpStarter\Warehouse\Model\BinLocationEntity;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseDefinition;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseEntity;
use Shopware\Core\Framework\Context;

class StockLocationReferenceFinder
{
    public const BIN_LOCATION_CODE_UNKNOWN = 'unknown';

    public const TYPE_SPECIFIC_LOCATION = 'specific-location';
    public const TYPE_WAREHOUSES = 'warehouses';

    private EntityManager $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function findStockLocationReference(array $stockLocationDescriptor, Context $context): ?array
    {
        $binLocationGiven = isset($stockLocationDescriptor['binLocationCode']) && $stockLocationDescriptor['binLocationCode'] !== '';
        $warehouseGiven = (isset($stockLocationDescriptor['warehouseCode']) && $stockLocationDescriptor['warehouseCode'] !== '')
            || (isset($stockLocationDescriptor['warehouseName']) && $stockLocationDescriptor['warehouseName'] !== '');

        if (!$binLocationGiven && !$warehouseGiven) {
            return [
                'type' => self::TYPE_WAREHOUSES,
                // null means "all warehouses"
                'warehouseIds' => null,
            ];
        }

        if ($binLocationGiven && $warehouseGiven) {
            if ($stockLocationDescriptor['binLocationCode'] === self::BIN_LOCATION_CODE_UNKNOWN) {
                /** @var WarehouseEntity $warehouse */
                $warehouse = $this->entityManager->findOneBy(WarehouseDefinition::class, array_filter([
                    'code' => $stockLocationDescriptor['warehouseCode'] ?? null,
                    'name' => $stockLocationDescriptor['warehouseName'] ?? null,
                ]), $context);
                if (!$warehouse) {
                    return null;
                }

                return [
                    'type' => self::TYPE_SPECIFIC_LOCATION,
                    'stockLocationReference' => StockLocationReference::warehouse($warehouse->getId()),
                ];
            }

            /** @var BinLocationEntity $binLocation */
            $binLocation = $this->entityManager->findOneBy(BinLocationDefinition::class, array_filter([
                'code' => $stockLocationDescriptor['binLocationCode'],
                'warehouse.code' => $stockLocationDescriptor['warehouseCode'] ?? null,
                'warehouse.name' => $stockLocationDescriptor['warehouseName'] ?? null,
            ]), $context);
            if (!$binLocation) {
                return null;
            }

            return [
                'type' => self::TYPE_SPECIFIC_LOCATION,
                'stockLocationReference' => StockLocationReference::binLocation($binLocation->getId()),
            ];
        }

        if ($warehouseGiven && !$binLocationGiven) {
            /** @var WarehouseEntity $warehouse */
            $warehouse = $this->entityManager->findOneBy(WarehouseDefinition::class, array_filter([
                'code' => $stockLocationDescriptor['warehouseCode'] ?? null,
                'name' => $stockLocationDescriptor['warehouseName'] ?? null,
            ]), $context);
            if (!$warehouse) {
                return null;
            }

            return [
                'type' => self::TYPE_WAREHOUSES,
                'warehouseIds' => [$warehouse->getId()],
            ];
        }

        throw new InvalidArgumentException(
            'Key "binLocationCode" can only be provided together with either "warehouseCode" or "warehouseName".',
        );
    }
}
