<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Installation\Steps;

use Doctrine\DBAL\Connection;
use Pickware\DalBundle\Sql\SqlUuid;
use Pickware\PickwareErpStarter\Config\Config;
use Pickware\PickwareErpStarter\Stock\Model\LocationTypeDefinition;
use Pickware\PickwareErpStarter\Stock\Model\SpecialStockLocationDefinition;
use Shopware\Core\Defaults;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Throwable;

class InitializeStockInstallationStep
{
    private Connection $db;
    private EventDispatcherInterface $eventDispatcher;
    private Config $config;

    public function __construct(Connection $db, EventDispatcherInterface $eventDispatcher)
    {
        $this->db = $db;
        $this->eventDispatcher = $eventDispatcher;
        // This is not injected as constructor parameter because the service is not available in the DI-Container yet.
        // (Because plugin is not active.)
        $this->config = new Config($this->db, $this->eventDispatcher);
    }

    public function install(): void
    {
        $this->db->beginTransaction();

        try {
            if (!$this->config->isStockInitialized()) {
                // Initialize stock only if it didn't happen already, for example after a plugin-reinstall with prior
                // safe uninstall
                $this->initializeStock();
            }

            $this->config->setStockInitialized(true);
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        $this->db->commit();
    }

    private function initializeStock(): void
    {
        $destinationWarehouse = $this->db->fetchAssociative(
            'SELECT
                LOWER(HEX(`id`)) as `id`,
                `name`,
                `code`
            FROM `pickware_erp_warehouse`
            WHERE `id` = UNHEX(:defaultWarehouseId)',
            [
                'defaultWarehouseId' => $this->config->getDefaultWarehouseId(),
            ],
        );
        $warehouseSnapshot = [
            'code' => $destinationWarehouse['code'],
            'name' => $destinationWarehouse['name'],
        ];

        $this->db->executeStatement(
            'INSERT INTO pickware_erp_stock_movement (
                `id`,
                `product_id`,
                `product_version_id`,
                `quantity`,
                `source_location_type_technical_name`,
                `source_special_stock_location_technical_name`,
                `source_location_snapshot`,
                `destination_location_type_technical_name`,
                `destination_warehouse_id`,
                `destination_location_snapshot`,
                `comment`,
                `created_at`
            ) SELECT
                ' . SqlUuid::UUID_V4_GENERATION . ',
                id,
                version_id,
                stock,
                :specialStockLocationTechnicalName,
                :stockInitializationTechnicalName,
                NULL,
                :warehouseTechnicalName,
                UNHEX(:warehouseId),
                :warehouseSnapshot,
                "",
                NOW(3)
            FROM product
            WHERE product.version_id = UNHEX(:liveVersionId)
            AND product.stock != 0',
            [
                'specialStockLocationTechnicalName' => LocationTypeDefinition::TECHNICAL_NAME_SPECIAL_STOCK_LOCATION,
                'stockInitializationTechnicalName' => SpecialStockLocationDefinition::TECHNICAL_NAME_INITIALIZATION,
                'warehouseTechnicalName' => LocationTypeDefinition::TECHNICAL_NAME_WAREHOUSE,
                'warehouseId' => $destinationWarehouse['id'],
                'warehouseSnapshot' => json_encode($warehouseSnapshot),
                'liveVersionId' => Defaults::LIVE_VERSION,
            ],
        );
    }
}
