<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DocumentBundle\Installation;

use Doctrine\DBAL\Connection;

class EnsureDocumentTypeInstallationStep
{
    /**
     * @var Connection
     */
    private $db;

    /**
     * @var array
     */
    private $documentTypeDescriptionMapping;

    public function __construct(Connection $db, array $documentTypeDescriptionMapping)
    {
        $this->db = $db;
        $this->documentTypeDescriptionMapping = $documentTypeDescriptionMapping;
    }

    public function install(): void
    {
        $sql = '
            INSERT INTO `pickware_document_type`
                (`technical_name`, `description`, `created_at`)
            VALUES
                (:technicalName, :description, NOW(3))
            ON DUPLICATE KEY UPDATE
                `description` = VALUES(`description`),
                `updated_at` = NOW(3)';

        foreach ($this->documentTypeDescriptionMapping as $technicalName => $description) {
            $this->db->executeStatement($sql, [
                'technicalName' => $technicalName,
                'description' => $description,
            ]);
        }
    }
}
