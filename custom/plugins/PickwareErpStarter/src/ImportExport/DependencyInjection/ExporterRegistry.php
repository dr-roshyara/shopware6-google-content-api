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
use Pickware\PickwareErpStarter\ImportExport\Exporter;

class ExporterRegistry
{
    /**
     * @var Exporter[]
     */
    private $exporter = [];

    public function addExporter(string $technicalName, Exporter $exporter): void
    {
        $this->exporter[$technicalName] = $exporter;
    }

    public function hasExporter(string $technicalName): bool
    {
        return array_key_exists($technicalName, $this->exporter);
    }

    public function getExporterByTechnicalName(string $technicalName): Exporter
    {
        if (!$this->hasExporter($technicalName)) {
            throw new OutOfBoundsException(sprintf(
                'Exporter with technical name "%s" is not installed.',
                $technicalName,
            ));
        }

        return $this->exporter[$technicalName];
    }
}
