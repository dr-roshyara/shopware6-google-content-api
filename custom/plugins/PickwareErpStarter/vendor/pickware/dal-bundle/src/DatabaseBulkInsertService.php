<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DalBundle;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Identifier;
use Doctrine\DBAL\Types\Type;
use Exception;
use function Franzose\DoctrineBulkInsert\parameters;
use function Franzose\DoctrineBulkInsert\sql;
use function Franzose\DoctrineBulkInsert\types;

class DatabaseBulkInsertService
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Bulk insert data into the given table name and updates all columns for the given columns when duplicate keys are
     * found.
     *
     * @param string $tableName Tablename
     * @param array<string, mixed>[] $dataset An array of rows to insert as assoc arrays where the keys are the name of the column
     * @param array<string, int|string|Type|null> $types the types of the columns as assoc array with the name of the columns as key
     * @param string[] $updateColumns name of the columns that will appear in the `ON DUPDATE KEY UPDATE` statement
     */
    public function insertOnDuplicateKeyUpdate(string $tableName, array $dataset, array $types = [], array $updateColumns = []): int
    {
        if (empty($dataset)) {
            return 0;
        }

        $sql = sql($this->connection->getDatabasePlatform(), new Identifier($tableName), $dataset);
        $sqlWithUpdate = $this->addOnDuplicateKey($sql, $updateColumns);

        return $this->connection->executeStatement($sqlWithUpdate, parameters($dataset), types($types, count($dataset)));
    }

    private function addOnDuplicateKey(string $sql, array $updateColumns): string
    {
        if (mb_substr($sql, -1) !== ';') {
            throw new Exception('Last character of the sql is not a ;');
        }

        $sql = rtrim($sql, ';');

        return sprintf(
            '%s ON DUPLICATE KEY UPDATE %s;',
            $sql,
            $this->makeUpdateStatementContent($updateColumns),
        );
    }

    private function makeUpdateStatementContent(array $updateColumns): string
    {
        $update = [];
        foreach ($updateColumns as $updateColumn) {
            $update[] = sprintf('%1$s = VALUES(%1$s)', $updateColumn);
        }

        return implode(', ', $update);
    }
}
