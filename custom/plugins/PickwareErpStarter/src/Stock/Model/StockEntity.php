<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stock\Model;

use LogicException;
use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderEntity;
use Pickware\PickwareErpStarter\StockApi\StockLocationReference;
use Pickware\PickwareErpStarter\SupplierOrder\Model\SupplierOrderEntity;
use Pickware\PickwareErpStarter\Warehouse\Model\BinLocationEntity;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class StockEntity extends Entity
{
    use EntityIdTrait;

    protected int $quantity;
    protected string $productId;
    protected ?ProductEntity $product = null;
    protected string $locationTypeTechnicalName;
    protected ?LocationTypeEntity $locationType = null;
    protected ?string $warehouseId = null;
    protected ?WarehouseEntity $warehouse = null;
    protected ?string $binLocationId = null;
    protected ?BinLocationEntity $binLocation = null;
    protected ?string $orderId = null;
    protected ?string $orderVersionId = null;
    protected ?OrderEntity $order = null;
    protected ?string $returnOrderId = null;
    protected ?ReturnOrderEntity $returnOrder = null;
    protected ?string $supplierOrderId = null;
    protected ?SupplierOrderEntity $supplierOrder = null;
    protected ?string $stockContainerId = null;
    protected ?StockContainerEntity $stockContainer = null;
    protected ?string $specialStockLocationTechnicalName = null;
    protected ?SpecialStockLocationEntity $specialStockLocation = null;

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function setProductId(string $productId): void
    {
        if ($this->product && $this->product->getId() !== $productId) {
            $this->product = null;
        }
        $this->productId = $productId;
    }

    public function getProduct(): ProductEntity
    {
        if (!$this->product) {
            throw new AssociationNotLoadedException('product', $this);
        }

        return $this->product;
    }

    public function setProduct(?ProductEntity $product): void
    {
        if ($product) {
            $this->productId = $product->getId();
        }
        $this->product = $product;
    }

    public function getLocationTypeTechnicalName(): string
    {
        return $this->locationTypeTechnicalName;
    }

    public function setLocationTypeTechnicalName(string $locationTypeTechnicalName): void
    {
        if ($this->locationType && $this->locationType->getTechnicalName() !== $locationTypeTechnicalName) {
            $this->locationType = null;
        }
        $this->locationTypeTechnicalName = $locationTypeTechnicalName;
    }

    public function getLocationType(): LocationTypeEntity
    {
        if (!$this->locationType) {
            throw new AssociationNotLoadedException('locationType', $this);
        }

        return $this->locationType;
    }

    public function setLocationType(LocationTypeEntity $locationType): void
    {
        $this->locationTypeTechnicalName = $locationType->getTechnicalName();
        $this->locationType = $locationType;
    }

    public function getWarehouseId(): ?string
    {
        return $this->warehouseId;
    }

    public function setWarehouseId(?string $warehouseId): void
    {
        if ($this->warehouse && $this->warehouse->getId() !== $warehouseId) {
            $this->warehouse = null;
        }
        $this->warehouseId = $warehouseId;
    }

    public function getWarehouse(): ?WarehouseEntity
    {
        if (!$this->warehouse && $this->warehouseId) {
            throw new AssociationNotLoadedException('warehouse', $this);
        }

        return $this->warehouse;
    }

    public function setWarehouse(?WarehouseEntity $warehouse): void
    {
        if ($warehouse) {
            $this->warehouseId = $warehouse->getId();
        }
        $this->warehouse = $warehouse;
    }

    public function getBinLocationId(): ?string
    {
        return $this->binLocationId;
    }

    public function setBinLocationId(?string $binLocationId): void
    {
        if ($this->binLocation && $this->binLocation->getId() !== $binLocationId) {
            $this->binLocation = null;
        }
        $this->binLocationId = $binLocationId;
    }

    public function getBinLocation(): ?BinLocationEntity
    {
        if (!$this->binLocation && $this->binLocationId) {
            throw new AssociationNotLoadedException('binLocation', $this);
        }

        return $this->binLocation;
    }

    public function setBinLocation(?BinLocationEntity $binLocation): void
    {
        if ($binLocation) {
            $this->binLocationId = $binLocation->getId();
        }
        $this->binLocation = $binLocation;
    }

    public function getOrderId(): ?string
    {
        return $this->orderId;
    }

    public function setOrderId(?string $orderId): void
    {
        if ($this->order && $this->order->getId() !== $orderId) {
            $this->order = null;
        }
        $this->orderId = $orderId;
    }

    public function getOrderVersionId(): ?string
    {
        return $this->orderVersionId;
    }

    public function setOrderVersionId(?string $orderVersionId): void
    {
        if ($this->order && $this->order->getVersionId() !== $orderVersionId) {
            $this->order = null;
        }
        $this->orderVersionId = $orderVersionId;
    }

    public function getOrder(): ?OrderEntity
    {
        if (!$this->order && $this->orderId) {
            throw new AssociationNotLoadedException('order', $this);
        }

        return $this->order;
    }

    public function setOrder(?OrderEntity $order): void
    {
        if ($order) {
            $this->orderId = $order->getId();
        } else {
            $this->orderId = null;
        }
        $this->order = $order;
    }

    public function getReturnOrderId(): ?string
    {
        return $this->returnOrderId;
    }

    public function setReturnOrderId(?string $returnOrderId): void
    {
        if ($this->returnOrder && $this->returnOrder->getId() !== $returnOrderId) {
            $this->returnOrder = null;
        }
        $this->returnOrderId = $returnOrderId;
    }

    public function getReturnOrder(): ?ReturnOrderEntity
    {
        if (!$this->returnOrder && $this->returnOrderId) {
            throw new AssociationNotLoadedException('returnOrder', $this);
        }

        return $this->returnOrder;
    }

    public function setReturnOrder(?ReturnOrderEntity $returnOrder): void
    {
        if ($returnOrder) {
            $this->returnOrderId = $returnOrder->getId();
        }
        $this->returnOrder = $returnOrder;
    }

    public function getSupplierOrderId(): ?string
    {
        return $this->supplierOrderId;
    }

    public function setSupplierOrderId(?string $supplierOrderId): void
    {
        if ($supplierOrderId && $this->supplierOrder && $this->supplierOrder->getId() !== $supplierOrderId) {
            $this->supplierOrder = null;
        }
        $this->supplierOrderId = $supplierOrderId;
    }

    public function getSupplierOrder(): ?SupplierOrderEntity
    {
        if ($this->supplierOrderId && !$this->supplierOrder) {
            throw new AssociationNotLoadedException('supplierOrder', $this);
        }

        return $this->supplierOrder;
    }

    public function setSupplierOrder(?SupplierOrderEntity $supplierOrder): void
    {
        if ($supplierOrder) {
            $this->supplierOrderId = $supplierOrder->getId();
        } else {
            $this->supplierOrderId = null;
        }
        $this->supplierOrder = $supplierOrder;
    }

    public function getStockContainerId(): ?string
    {
        return $this->stockContainerId;
    }

    public function setStockContainerId(?string $stockContainerId): void
    {
        if ($stockContainerId && $this->stockContainer && $this->stockContainer->getId() !== $stockContainerId) {
            $this->stockContainer = null;
        }
        $this->stockContainerId = $stockContainerId;
    }

    public function getStockContainer(): ?StockContainerEntity
    {
        if ($this->stockContainerId && !$this->stockContainer) {
            throw new AssociationNotLoadedException('stockContainer', $this);
        }

        return $this->stockContainer;
    }

    public function setStockContainer(?StockContainerEntity $stockContainer): void
    {
        if ($stockContainer) {
            $this->stockContainerId = $stockContainer->getId();
        } else {
            $this->stockContainerId = null;
        }
        $this->stockContainer = $stockContainer;
    }

    public function getSpecialStockLocationTechnicalName(): ?string
    {
        return $this->specialStockLocationTechnicalName;
    }

    public function setSpecialStockLocationTechnicalName(?string $specialStockLocationTechnicalName): void
    {
        if ($this->specialStockLocation
            && $this->specialStockLocation->getTechnicalName() !== $specialStockLocationTechnicalName
        ) {
            $this->specialStockLocation = null;
        }
        $this->specialStockLocationTechnicalName = $specialStockLocationTechnicalName;
    }

    public function getSpecialStockLocation(): ?SpecialStockLocationEntity
    {
        if (!$this->specialStockLocation && $this->specialStockLocationTechnicalName) {
            throw new AssociationNotLoadedException('specialStockLocation', $this);
        }

        return $this->specialStockLocation;
    }

    public function setSpecialStockLocation(?SpecialStockLocationEntity $specialStockLocation): void
    {
        if ($specialStockLocation) {
            $this->specialStockLocationTechnicalName = $specialStockLocation->getTechnicalName();
        }
        $this->specialStockLocation = $specialStockLocation;
    }

    public function createStockLocationReference(): StockLocationReference
    {
        switch ($this->getLocationTypeTechnicalName()) {
            case LocationTypeDefinition::TECHNICAL_NAME_ORDER:
                return StockLocationReference::order($this->getOrderId());
            case LocationTypeDefinition::TECHNICAL_NAME_RETURN_ORDER:
                return StockLocationReference::returnOrder($this->getReturnOrderId());
            case LocationTypeDefinition::TECHNICAL_NAME_SUPPLIER_ORDER:
                return StockLocationReference::supplierOrder($this->getSupplierOrderId());
            case LocationTypeDefinition::TECHNICAL_NAME_BIN_LOCATION:
                return StockLocationReference::binLocation($this->getBinLocationId());
            case LocationTypeDefinition::TECHNICAL_NAME_WAREHOUSE:
                return StockLocationReference::warehouse($this->getWarehouseId());
            case LocationTypeDefinition::TECHNICAL_NAME_STOCK_CONTAINER:
                return StockLocationReference::stockContainer($this->getStockContainerId());
            case LocationTypeDefinition::TECHNICAL_NAME_SPECIAL_STOCK_LOCATION:
                return StockLocationReference::specialStockLocation($this->getSpecialStockLocationTechnicalName());
            default:
                break;
        }

        throw new LogicException(sprintf(
            'Missing implementation in method %s for location type %s.',
            __METHOD__,
            $this->getLocationTypeTechnicalName(),
        ));
    }
}
