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

class UpsertImportExportProfilesInstallationStep
{
    /**
     * @var Connection
     */
    private $db;

    /**
     * @var string[]
     */
    private $profileTechnicalNames;

    public function __construct(Connection $db, array $profileTechnicalNames)
    {
        $this->db = $db;
        $this->profileTechnicalNames = $profileTechnicalNames;
    }

    public function install(): void
    {
        foreach ($this->profileTechnicalNames as $profileTechnicalName) {
            $this->db->executeStatement(
                'INSERT INTO `pickware_erp_import_export_profile`
                (`technical_name`)
                VALUES (:technicalName)
                ON DUPLICATE KEY UPDATE `technical_name` = `technical_name`',
                ['technicalName' => $profileTechnicalName],
            );
        }
    }
}
