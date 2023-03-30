<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\ImportExport\DependencyInjection;

use OutOfBoundsException;
use Pickware\PickwareErpStarter\ImportExport\Importer;

class ImporterRegistry
{
    /**
     * @var Importer[]
     */
    private array $importer = [];

    public function addImporter(string $technicalName, Importer $importer): void
    {
        $this->importer[$technicalName] = $importer;
    }

    public function hasImporter(string $technicalName): bool
    {
        return array_key_exists($technicalName, $this->importer);
    }

    public function getImporterByTechnicalName(string $technicalName): Importer
    {
        if (!$this->hasImporter($technicalName)) {
            throw new OutOfBoundsException(sprintf(
                'Importer with technical name "%s" is not installed.',
                $technicalName,
            ));
        }

        return $this->importer[$technicalName];
    }
}
