<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stocktaking\Model;

use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class StocktakeSnapshotItemEntity extends Entity
{
    use EntityIdTrait;

    protected ?StocktakeCountingProcessItemEntity $countingProcessItem = null;
    protected int $warehouseStock;
    protected int $totalCounted;
    protected int $totalStockDifference;
    protected int $stockLocationStock;
    protected int $counted;
    protected int $stockDifference;
    protected string $countingProcessItemId;

    public function getCountingProcessItemId(): string
    {
        return $this->countingProcessItemId;
    }

    public function setCountingProcessItemId(string $countingProcessItemId): void
    {
        $this->countingProcessItemId = $countingProcessItemId;
        if ($this->countingProcessItem && $this->countingProcessItem->getId() !== $countingProcessItemId) {
            $this->countingProcessItem = null;
        }
    }

    public function getCountingProcessItem(): StocktakeCountingProcessItemEntity
    {
        if (!$this->countingProcessItem) {
            throw new AssociationNotLoadedException('countingProcessItem', $this);
        }

        return $this->countingProcessItem;
    }

    public function setCountingProcessItem(StocktakeCountingProcessItemEntity $countingProcessItem): void
    {
        $this->countingProcessItem = $countingProcessItem;
        $this->countingProcessItemId = $countingProcessItem->getId();
    }

    public function getWarehouseStock(): int
    {
        return $this->warehouseStock;
    }

    public function setWarehouseStock(int $warehouseStock): void
    {
        $this->warehouseStock = $warehouseStock;
    }

    public function getTotalCounted(): int
    {
        return $this->totalCounted;
    }

    public function setTotalCounted(int $totalCounted): void
    {
        $this->totalCounted = $totalCounted;
    }

    public function getTotalStockDifference(): int
    {
        return $this->totalStockDifference;
    }

    public function setTotalStockDifference(int $totalStockDifference): void
    {
        $this->totalStockDifference = $totalStockDifference;
    }

    public function getStockLocationStock(): int
    {
        return $this->stockLocationStock;
    }

    public function setStockLocationStock(int $stockLocationStock): void
    {
        $this->stockLocationStock = $stockLocationStock;
    }

    public function getCounted(): int
    {
        return $this->counted;
    }

    public function setCounted(int $counted): void
    {
        $this->counted = $counted;
    }

    public function getStockDifference(): int
    {
        return $this->stockDifference;
    }

    public function setStockDifference(int $stockDifference): void
    {
        $this->stockDifference = $stockDifference;
    }
}
