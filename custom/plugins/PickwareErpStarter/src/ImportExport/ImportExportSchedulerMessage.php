<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\ImportExport;

use Shopware\Core\Framework\Context;

class ImportExportSchedulerMessage
{
    public const STATE_EXECUTE_IMPORT = 'execute-import';
    /** @deprecated will be removed with the next major version. Use self::STATE_READ_FILE_TO_DATABASE instead */
    public const STATE_READ_CSV_TO_DATABASE = 'read-csv-to-database';
    public const STATE_READ_FILE_TO_DATABASE = 'read-file-to-database';
    /** @deprecated will be removed with the next major version. Use self::STATE_FILE_VALIDATION instead */
    public const STATE_CSV_FILE_VALIDATION = 'csv-file-validation';
    public const STATE_FILE_VALIDATION = 'file-validation';

    public const STATE_EXECUTE_EXPORT = 'execute-export';
    public const STATE_WRITE_DATABASE_TO_CSV = 'write-database-to-csv';

    private string $importExportId;
    private string $state;
    private Context $context;

    public function __construct(string $importExportId, string $state, Context $context)
    {
        $this->importExportId = $importExportId;
        $this->state = $state;
        $this->context = $context;
    }

    public function getImportExportId(): string
    {
        return $this->importExportId;
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
