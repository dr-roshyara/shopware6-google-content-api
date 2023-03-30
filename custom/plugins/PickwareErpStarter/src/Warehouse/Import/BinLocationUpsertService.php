<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Warehouse\Import;

use Doctrine\DBAL\Connection;
use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\Warehouse\Model\BinLocationDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

class BinLocationUpsertService
{
    private Connection $db;
    private EntityManager $entityManager;

    public function __construct(
        Connection $db,
        EntityManager $entityManager
    ) {
        $this->db = $db;
        $this->entityManager = $entityManager;
    }

    /**
     * Upserts bin locations for the given list of bin location codes. Since we do not have `id` from the imported csv,
     * we cannot use a real upsert function but instead find the bin location codes that are actually new and insert
     * entities for them manually.
     *
     * @param string[] $binLocationCodes
     * @return int number of created entities
     *
     * @throws \Doctrine\DBAL\Exception
     */
    public function upsertBinLocations(array $binLocationCodes, string $warehouseId, Context $context): int
    {
        if (empty($binLocationCodes)) {
            return 0;
        }

        $existingBinLocationsResult = $this->db->fetchAllAssociative(
            'SELECT code
                FROM pickware_erp_bin_location AS bin_location
                WHERE bin_location.code IN (:binLocationCodes)
                AND bin_location.warehouse_id = :warehouseId',
            [
                'binLocationCodes' => $binLocationCodes,
                'warehouseId' => hex2bin($warehouseId),
            ],
            [
                'binLocationCodes' => Connection::PARAM_STR_ARRAY,
            ],
        );
        $newBinLocationCodes = array_values(array_unique(array_diff(
            $binLocationCodes,
            array_column($existingBinLocationsResult, 'code'),
        )));
        $numberOfNewBinLocations = count($newBinLocationCodes);

        if ($numberOfNewBinLocations > 0) {
            $binLocationPayload = array_map(static function (string $binLocationCode) use ($warehouseId) {
                return [
                    'id' => Uuid::randomHex(),
                    'warehouseId' => $warehouseId,
                    'code' => $binLocationCode,
                ];
            }, $newBinLocationCodes);

            $this->entityManager->upsert(
                BinLocationDefinition::class,
                $binLocationPayload,
                $context,
            );
        }

        return $numberOfNewBinLocations;
    }
}
