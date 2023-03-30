<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Picking;

use LogicException;
use Pickware\PickwareErpStarter\Stock\Model\LocationTypeDefinition;
use Pickware\PickwareErpStarter\StockApi\StockLocationReference;

class PickLocation
{
    /**
     * @var string
     */
    private $stockId;

    /**
     * @var null|string
     */
    private $binLocationId;

    /**
     * @var string
     */
    private $locationTypeTechnicalName;

    /**
     * @var null|string
     */
    private $binLocationCode;

    /**
     * @var int
     */
    private $quantityInStock;

    /**
     * @var int
     */
    private $quantityToPick;

    /**
     * @var PickLocationWarehouse
     */
    private $pickLocationWarehouse;

    public function __construct(
        string $stockId,
        string $locationTypeTechnicalName,
        PickLocationWarehouse $pickLocationWarehouse,
        int $quantityInStock = 0,
        int $quantityToPick = 0
    ) {
        $this->stockId = $stockId;
        $this->locationTypeTechnicalName = $locationTypeTechnicalName;
        $this->quantityInStock = $quantityInStock;
        $this->quantityToPick = $quantityToPick;
        $this->pickLocationWarehouse = $pickLocationWarehouse;
    }

    public function getStockLocationReference(): StockLocationReference
    {
        if ($this->getLocationTypeTechnicalName() === LocationTypeDefinition::TECHNICAL_NAME_BIN_LOCATION) {
            return StockLocationReference::binLocation($this->getBinLocationId());
        }
        if ($this->getLocationTypeTechnicalName() === LocationTypeDefinition::TECHNICAL_NAME_WAREHOUSE) {
            return StockLocationReference::warehouse($this->pickLocationWarehouse->getId());
        }

        throw new LogicException('Not implemented');
    }

    public function getStockId(): string
    {
        return $this->stockId;
    }

    public function getLocationTypeTechnicalName(): string
    {
        return $this->locationTypeTechnicalName;
    }

    public function getBinLocationCode(): ?string
    {
        return $this->binLocationCode;
    }

    public function setBinLocationCode(?string $binLocationCode): void
    {
        $this->binLocationCode = $binLocationCode;
    }

    public function getQuantityInStock(): int
    {
        return $this->quantityInStock;
    }

    public function getQuantityToPick(): int
    {
        return $this->quantityToPick;
    }

    public function setQuantityToPick(int $quantityToPick): void
    {
        $this->quantityToPick = $quantityToPick;
    }

    public function getBinLocationId(): ?string
    {
        return $this->binLocationId;
    }

    public function setBinLocationId(?string $binLocationId): void
    {
        $this->binLocationId = $binLocationId;
    }

    public function getPickLocationWarehouse(): PickLocationWarehouse
    {
        return $this->pickLocationWarehouse;
    }
}
