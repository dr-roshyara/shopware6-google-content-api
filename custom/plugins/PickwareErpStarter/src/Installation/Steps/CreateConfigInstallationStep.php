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
use Pickware\PickwareErpStarter\Config\Config;

class CreateConfigInstallationStep
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
        $this->db->executeStatement(
            'INSERT INTO pickware_erp_config (
                `id`,
                `default_warehouse_id`
            ) VALUES (
                UNHEX(:id),
                (
                    SELECT `id`
                    FROM `pickware_erp_warehouse`
                    ORDER BY `created_at`
                    LIMIT 1
                )
            ) ON DUPLICATE KEY UPDATE `id` = `id`',
            ['id' => Config::CONFIG_ID],
        );
    }
}
