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
use Shopware\Core\Defaults;

class UpsertProductSupplierConfigurationsInstallationStep
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function install(): void
    {
        $this->db->executeStatement(
            'INSERT INTO `pickware_erp_product_supplier_configuration` (
                `id`,
                `product_id`,
                `product_version_id`,
                `created_at`
            ) SELECT
                ' . SqlUuid::UUID_V4_GENERATION . ',
                `id`,
                `version_id`,
                NOW(3)
            FROM `product`
            WHERE `version_id` = :liveVersionId
            ON DUPLICATE KEY UPDATE `pickware_erp_product_supplier_configuration`.`id` = `pickware_erp_product_supplier_configuration`.`id`',
            [
                'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
            ],
            [
                'ids' => Connection::PARAM_STR_ARRAY,
            ],
        );
    }
}
