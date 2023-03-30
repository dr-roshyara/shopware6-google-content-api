<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Warehouse\Model;

use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Pickware\PickwareErpStarter\Address\Model\AddressEntity;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderCollection;
use Pickware\PickwareErpStarter\Stock\Model\StockCollection;
use Pickware\PickwareErpStarter\Stock\Model\StockMovementCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class WarehouseEntity extends Entity
{
    use EntityIdTrait;

    protected string $code;
    protected string $name;
    protected bool $isDefault;
    protected ?string $addressId = null;
    protected ?AddressEntity $address = null;
    protected ?BinLocationCollection $binLocations = null;
    protected ?StockMovementCollection $sourceStockMovements = null;
    protected ?StockMovementCollection $destinationStockMovements = null;
    protected ?StockCollection $stocks = null;
    protected ?ProductWarehouseConfigurationCollection $productWarehouseConfigurations = null;
    protected ?ReturnOrderCollection $returnOrders = null;

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getIsDefault(): bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(bool $isDefault): void
    {
        $this->isDefault = $isDefault;
    }

    public function getAddressId(): ?string
    {
        return $this->addressId;
    }

    public function setAddressId(?string $addressId): void
    {
        if ($this->address && $this->address->getId() !== $addressId) {
            $this->address = null;
        }
        $this->addressId = $addressId;
    }

    public function getAddress(): ?AddressEntity
    {
        if (!$this->address && $this->addressId !== null) {
            throw new AssociationNotLoadedException('address', $this);
        }

        return $this->address;
    }

    public function setAddress(?AddressEntity $address): void
    {
        if ($address) {
            $this->addressId = $address->getId();
        }
        $this->address = $address;
    }

    public function getBinLocations(): BinLocationCollection
    {
        if (!$this->binLocations) {
            throw new AssociationNotLoadedException('binLocations', $this);
        }

        return $this->binLocations;
    }

    public function setBinLocations(?BinLocationCollection $binLocations): void
    {
        $this->binLocations = $binLocations;
    }

    public function getSourceStockMovements(): StockMovementCollection
    {
        if (!$this->sourceStockMovements) {
            throw new AssociationNotLoadedException('sourceStockMovements', $this);
        }

        return $this->sourceStockMovements;
    }

    public function setSourceStockMovements(?StockMovementCollection $sourceStockMovements): void
    {
        $this->sourceStockMovements = $sourceStockMovements;
    }

    public function getDestinationStockMovements(): StockMovementCollection
    {
        if (!$this->destinationStockMovements) {
            throw new AssociationNotLoadedException('destinationStockMovements', $this);
        }

        return $this->destinationStockMovements;
    }

    public function setDestinationStockMovements(?StockMovementCollection $destinationStockMovements): void
    {
        $this->destinationStockMovements = $destinationStockMovements;
    }

    public function getStocks(): StockCollection
    {
        if (!$this->stocks) {
            throw new AssociationNotLoadedException('stocks', $this);
        }

        return $this->stocks;
    }

    public function setStocks(?StockCollection $stocks): void
    {
        $this->stocks = $stocks;
    }

    public function getProductWarehouseConfigurations(): ?ProductWarehouseConfigurationCollection
    {
        if (!$this->productWarehouseConfigurations) {
            throw new AssociationNotLoadedException('productWarehouseConfigurations', $this);
        }

        return $this->productWarehouseConfigurations;
    }

    public function setProductWarehouseConfigurations(
        ?ProductWarehouseConfigurationCollection $productWarehouseConfigurations
    ): void {
        $this->productWarehouseConfigurations = $productWarehouseConfigurations;
    }

    public function getReturnOrders(): ReturnOrderCollection
    {
        if (!$this->returnOrders) {
            throw new AssociationNotLoadedException('returnOrders', $this);
        }

        return $this->returnOrders;
    }

    public function setReturnOrders(?ReturnOrderCollection $returnOrders): void
    {
        $this->returnOrders = $returnOrders;
    }
}
