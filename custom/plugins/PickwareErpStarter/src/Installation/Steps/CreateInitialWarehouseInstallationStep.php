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
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

class CreateInitialWarehouseInstallationStep
{
    /**
     * @var Connection
     */
    private $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function install(): void
    {
        $warehouseExists = $this->db->fetchOne(
            'SELECT COUNT(`id`)
            FROM pickware_erp_warehouse',
        );
        if ($warehouseExists) {
            return;
        }

        $systemDefaultLanguage = $this->db->fetchOne(
            'SELECT `locale`.`code`
            FROM `language`
            INNER JOIN `locale` ON `locale`.`id` = `language`.`locale_id`
            WHERE `language`.`id` = UNHEX(:systemLanguageId)',
            ['systemLanguageId' => Defaults::LANGUAGE_SYSTEM],
        );

        $initialWarehousePayload = [];
        if (mb_stripos($systemDefaultLanguage, 'de-') === 0) {
            $initialWarehousePayload['code'] = 'HL';
            $initialWarehousePayload['name'] = 'Hauptlager';
        } else {
            $initialWarehousePayload['code'] = 'MW';
            $initialWarehousePayload['name'] = 'Main warehouse';
        }

        $addressId = Uuid::randomHex();
        $initialWarehousePayload['id'] = Uuid::randomHex();
        $initialWarehousePayload['addressId'] = $addressId;

        $this->db->executeStatement(
            'INSERT INTO `pickware_erp_address` (
                `id`,
                `created_at`
            ) VALUES (
                UNHEX(:id),
                NOW(3)
            )',
            ['id' => $addressId],
        );
        $this->db->executeStatement(
            'INSERT INTO `pickware_erp_warehouse` (
                `id`,
                `code`,
                `name`,
                `address_id`,
                `created_at`
            ) VALUES (
                UNHEX(:id),
                :code,
                :name,
                UNHEX(:addressId),
                NOW(3)
            )',
            $initialWarehousePayload,
        );
    }
}
