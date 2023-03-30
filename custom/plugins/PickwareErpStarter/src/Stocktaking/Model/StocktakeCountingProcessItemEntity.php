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
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class StocktakeCountingProcessItemEntity extends Entity
{
    use EntityIdTrait;

    protected string $countingProcessId;
    protected ?StocktakeCountingProcessEntity $countingProcess = null;
    protected ?string $productId = null;
    protected ?ProductEntity $product = null;
    protected array $productSnapshot;
    protected int $quantity;
    protected ?StocktakeSnapshotItemEntity $snapshotItem = null;

    public function getCountingProcessId(): string
    {
        return $this->countingProcessId;
    }

    public function setCountingProcessId(string $countingProcessId): void
    {
        if ($this->countingProcess && $this->countingProcess->getId() !== $countingProcessId) {
            $this->countingProcess = null;
        }
        $this->countingProcessId = $countingProcessId;
    }

    public function getCountingProcess(): StocktakeCountingProcessEntity
    {
        if (!$this->countingProcess) {
            throw new AssociationNotLoadedException('countingProcess', $this);
        }

        return $this->countingProcess;
    }

    public function setCountingProcess(StocktakeCountingProcessEntity $countingProcess): void
    {
        $this->countingProcess = $countingProcess;
        $this->countingProcessId = $countingProcess->getId();
    }

    public function getProductId(): ?string
    {
        return $this->productId;
    }

    public function setProductId(?string $productId): void
    {
        if ($this->product && $this->product->getId() !== $productId) {
            $this->product = null;
        }
        $this->productId = $productId;
    }

    public function getProduct(): ?ProductEntity
    {
        if ($this->productId && !$this->product) {
            throw new AssociationNotLoadedException('product', $this);
        }

        return $this->product;
    }

    public function setProduct(?ProductEntity $product): void
    {
        $this->product = $product;
        $this->productId = $product ? $product->getId() : null;
    }

    public function getProductSnapshot(): array
    {
        return $this->productSnapshot;
    }

    public function setProductSnapshot(array $productSnapshot): void
    {
        $this->productSnapshot = $productSnapshot;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function getSnapshotItem(): ?StocktakeSnapshotItemEntity
    {
        return $this->snapshotItem;
    }

    public function setSnapshotItem(?StocktakeSnapshotItemEntity $snapshotItem): void
    {
        $this->snapshotItem = $snapshotItem;
    }
}
