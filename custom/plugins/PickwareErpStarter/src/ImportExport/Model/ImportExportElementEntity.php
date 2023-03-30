<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\ImportExport\Model;

use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class ImportExportElementEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $importExportId;

    /**
     * @var ImportExportEntity|null
     */
    protected $importExport;

    /**
     * @var int
     */
    protected $rowNumber;

    /**
     * @var array
     */
    protected $rowData;

    /**
     * @var JsonApiErrors|null
     */
    protected $errors;

    public function getImportExportId(): string
    {
        return $this->importExportId;
    }

    public function setImportExportId(string $importExportId): void
    {
        if ($this->importExport && $this->importExport->getId() !== $importExportId) {
            $this->importExport = null;
        }
        $this->importExportId = $importExportId;
    }

    public function getImportExport(): ImportExportEntity
    {
        if (!$this->importExport) {
            throw new AssociationNotLoadedException('importExport', $this);
        }

        return $this->importExport;
    }

    public function setImportExport(ImportExportEntity $importExport): void
    {
        $this->importExport = $importExport;
        $this->importExportId = $importExport->getId();
    }

    public function getRowNumber(): int
    {
        return $this->rowNumber;
    }

    public function setRowNumber(int $rowNumber): void
    {
        $this->rowNumber = $rowNumber;
    }

    public function getRowData(): array
    {
        return $this->rowData;
    }

    public function setRowData(array $rowData): void
    {
        $this->rowData = $rowData;
    }

    public function getErrors(): ?JsonApiErrors
    {
        return $this->errors;
    }

    public function setErrors(?JsonApiErrors $errors): void
    {
        $this->errors = $errors;
    }
}
