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

use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\PickwareErpStarter\ImportExport\Exception\ImportException;
use Shopware\Core\Framework\Context;

interface Importer
{
    /**
     * Imports a chunk of CSV data from table pickware_erp_import_export_element.
     *
     * The start of the chunk is $offset.
     * The chunk size can be chosen by the implementation of the method.
     * The method returns the index of the next unprocessed element of pickware_erp_import.
     * If the method returns null, there are no items left to import.
     *
     * Notes for implementation:
     *  * You should choose a chunk size so that the process time takes about 1 second.
     *
     * @param int $nextRowNumberToRead next row number that will be read from the db. Starts with 1 for each import.
     * @throws ImportException
     */
    public function importChunk(string $importId, int $nextRowNumberToRead, Context $context): ?int;

    /**
     * @return JsonApiErrors A list of JSON API error objects describing what went wrong, returns an empty JsonApiErrors
     * objects, when nothing went wrong.
     */
    public function validateHeaderRow(array $headerRow, Context $context): JsonApiErrors;

    public function validateConfig(array $config): JsonApiErrors;
}
