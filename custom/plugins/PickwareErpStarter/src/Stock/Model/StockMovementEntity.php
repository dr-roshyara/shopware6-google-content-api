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
use Shopware\Core\System\User\UserEntity;

class StockMovementEntity extends Entity
{
    use EntityIdTrait;

    protected int $quantity;
    protected ?string $comment = null;
    protected string $productId;
    protected ?ProductEntity $product = null;
    protected ?array $sourceLocationSnapshot = null;
    protected string $sourceLocationTypeTechnicalName;
    protected ?LocationTypeEntity $sourceLocationType = null;
    protected ?string $sourceWarehouseId = null;
    protected ?WarehouseEntity $sourceWarehouse = null;
    protected ?string $sourceBinLocationId = null;
    protected ?BinLocationEntity $sourceBinLocation = null;
    protected ?string $sourceOrderId = null;
    protected ?string $sourceOrderVersionId = null;
    protected ?OrderEntity $sourceOrder = null;
    protected ?string $sourceReturnOrderId = null;
    protected ?ReturnOrderEntity $sourceReturnOrder = null;
    protected ?string $sourceSupplierOrderId = null;
    protected ?SupplierOrderEntity $sourceSupplierOrder = null;
    protected ?string $sourceStockContainerId = null;
    protected ?StockContainerEntity $sourceStockContainer = null;
    protected ?string $sourceSpecialStockLocationTechnicalName = null;
    protected ?SpecialStockLocationEntity $sourceSpecialStockLocation = null;
    protected ?array $destinationLocationSnapshot = null;
    protected string $destinationLocationTypeTechnicalName;
    protected ?LocationTypeEntity $destinationLocationType = null;
    protected ?string $destinationWarehouseId = null;
    protected ?WarehouseEntity $destinationWarehouse = null;
    protected ?string $destinationBinLocationId = null;
    protected ?BinLocationEntity $destinationBinLocation = null;
    protected ?string $destinationOrderId = null;
    protected ?string $destinationOrderVersionId = null;
    protected ?OrderEntity $destinationOrder = null;
    protected ?string $destinationReturnOrderId = null;
    protected ?ReturnOrderEntity $destinationReturnOrder = null;
    protected ?string $destinationSupplierOrderId = null;
    protected ?SupplierOrderEntity $destinationSupplierOrder = null;
    protected ?string $destinationStockContainerId = null;
    protected ?StockContainerEntity $destinationStockContainer = null;
    protected ?string $destinationSpecialStockLocationTechnicalName = null;
    protected ?SpecialStockLocationEntity $destinationSpecialStockLocation = null;
    protected ?string $userId = null;
    protected ?UserEntity $user = null;

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): void
    {
        $this->comment = $comment;
    }

    public function getSourceLocationSnapshot(): ?array
    {
        return $this->sourceLocationSnapshot;
    }

    public function setSourceLocationSnapshot(?array $sourceLocationSnapshot): void
    {
        $this->sourceLocationSnapshot = $sourceLocationSnapshot;
    }

    public function getSourceLocationTypeTechnicalName(): string
    {
        return $this->sourceLocationTypeTechnicalName;
    }

    public function setSourceLocationTypeTechnicalName(string $sourceLocationTypeTechnicalName): void
    {
        if ($this->sourceLocationType
            && $this->sourceLocationType->getTechnicalName() !== $sourceLocationTypeTechnicalName
        ) {
            $this->sourceLocationType = null;
        }
        $this->sourceLocationTypeTechnicalName = $sourceLocationTypeTechnicalName;
    }

    public function getSourceLocationType(): LocationTypeEntity
    {
        if (!$this->sourceLocationType) {
            throw new AssociationNotLoadedException('sourceLocationType', $this);
        }

        return $this->sourceLocationType;
    }

    public function setSourceLocationType(LocationTypeEntity $sourceLocationType): void
    {
        $this->sourceLocationType = $sourceLocationType;
        $this->sourceLocationTypeTechnicalName = $sourceLocationType->getTechnicalName();
    }

    public function getSourceWarehouseId(): ?string
    {
        return $this->sourceWarehouseId;
    }

    public function setSourceWarehouseId(?string $sourceWarehouseId): void
    {
        if ($this->sourceWarehouse && $this->sourceWarehouse->getId() !== $sourceWarehouseId) {
            $this->sourceWarehouse = null;
        }
        $this->sourceWarehouseId = $sourceWarehouseId;
    }

    public function getSourceWarehouse(): ?WarehouseEntity
    {
        if (!$this->sourceWarehouse && $this->sourceWarehouseId) {
            throw new AssociationNotLoadedException('sourceWarehouse', $this);
        }

        return $this->sourceWarehouse;
    }

    public function setSourceWarehouse(?WarehouseEntity $sourceWarehouse): void
    {
        if ($sourceWarehouse) {
            $this->sourceWarehouseId = $sourceWarehouse->getId();
        }
        $this->sourceWarehouse = $sourceWarehouse;
    }

    public function getSourceBinLocationId(): ?string
    {
        return $this->sourceBinLocationId;
    }

    public function setSourceBinLocationId(?string $sourceBinLocationId): void
    {
        if ($this->sourceBinLocation && $this->sourceBinLocation->getId() !== $sourceBinLocationId) {
            $this->sourceBinLocation = null;
        }
        $this->sourceBinLocationId = $sourceBinLocationId;
    }

    public function getSourceBinLocation(): ?BinLocationEntity
    {
        if (!$this->sourceBinLocation && $this->sourceBinLocationId) {
            throw new AssociationNotLoadedException('sourceBinLocation', $this);
        }

        return $this->sourceBinLocation;
    }

    public function setSourceBinLocation(?BinLocationEntity $sourceBinLocation): void
    {
        if ($sourceBinLocation) {
            $this->sourceBinLocationId = $sourceBinLocation->getId();
        }
        $this->sourceBinLocation = $sourceBinLocation;
    }

    public function getSourceOrderId(): ?string
    {
        return $this->sourceOrderId;
    }

    public function setSourceOrderId(?string $sourceOrderId): void
    {
        if ($this->sourceOrder
            && $this->sourceOrder->getId() !== $sourceOrderId) {
            $this->sourceOrder = null;
        }
        $this->sourceOrderId = $sourceOrderId;
    }

    public function getSourceOrderVersionId(): ?string
    {
        return $this->sourceOrderVersionId;
    }

    public function setSourceOrderVersionId(?string $sourceOrderVersionId): void
    {
        if ($this->sourceOrder
            && $this->sourceOrder->getVersionId() !== $sourceOrderVersionId) {
            $this->sourceOrder = null;
        }
        $this->sourceOrderVersionId = $sourceOrderVersionId;
    }

    public function getSourceOrder(): ?OrderEntity
    {
        if (!$this->sourceOrder && $this->sourceOrderId) {
            throw new AssociationNotLoadedException('sourceOrder', $this);
        }

        return $this->sourceOrder;
    }

    public function setSourceOrder(?OrderEntity $sourceOrder): void
    {
        if ($sourceOrder) {
            $this->sourceOrderId = $sourceOrder->getId();
        }
        $this->sourceOrder = $sourceOrder;
    }

    public function getSourceReturnOrderId(): ?string
    {
        return $this->sourceReturnOrderId;
    }

    public function setSourceReturnOrderId(?string $sourceReturnOrderId): void
    {
        if ($this->sourceReturnOrder && $this->sourceReturnOrder->getId() !== $sourceReturnOrderId) {
            $this->sourceReturnOrder = null;
        }
        $this->sourceReturnOrderId = $sourceReturnOrderId;
    }

    public function getSourceReturnOrder(): ?ReturnOrderEntity
    {
        if (!$this->sourceReturnOrder && $this->sourceReturnOrderId) {
            throw new AssociationNotLoadedException('sourceReturnOrder', $this);
        }

        return $this->sourceReturnOrder;
    }

    public function setSourceReturnOrder(?ReturnOrderEntity $sourceReturnOrder): void
    {
        if ($sourceReturnOrder) {
            $this->sourceReturnOrderId = $sourceReturnOrder->getId();
        }
        $this->sourceReturnOrder = $sourceReturnOrder;
    }

    public function getSourceSupplierOrderId(): ?string
    {
        return $this->sourceSupplierOrderId;
    }

    public function setSourceSupplierOrderId(?string $sourceSupplierOrderId): void
    {
        if ($this->sourceSupplierOrder && $this->sourceSupplierOrder->getId() !== $sourceSupplierOrderId) {
            $this->sourceSupplierOrder = null;
        }
        $this->sourceSupplierOrderId = $sourceSupplierOrderId;
    }

    public function getSourceSupplierOrder(): ?SupplierOrderEntity
    {
        if (!$this->sourceSupplierOrder && $this->sourceSupplierOrderId) {
            throw new AssociationNotLoadedException('sourceSupplierOrder', $this);
        }

        return $this->sourceSupplierOrder;
    }

    public function setSourceSupplierOrder(?SupplierOrderEntity $sourceSupplierOrder): void
    {
        if ($sourceSupplierOrder) {
            $this->sourceSupplierOrderId = $sourceSupplierOrder->getId();
        } else {
            $this->sourceSupplierOrderId = null;
        }
        $this->sourceSupplierOrder = $sourceSupplierOrder;
    }

    public function getSourceStockContainerId(): ?string
    {
        return $this->sourceStockContainerId;
    }

    public function setSourceStockContainerId(?string $sourceStockContainerId): void
    {
        if ($sourceStockContainerId && $this->sourceStockContainer && $this->sourceStockContainer->getId() !== $sourceStockContainerId) {
            $this->sourceStockContainer = null;
        }
        $this->sourceStockContainerId = $sourceStockContainerId;
    }

    public function getSourceStockContainer(): ?StockContainerEntity
    {
        if ($this->sourceStockContainerId && !$this->sourceStockContainer) {
            throw new AssociationNotLoadedException('sourceStockContainer', $this);
        }

        return $this->sourceStockContainer;
    }

    public function setSourceStockContainer(?StockContainerEntity $sourceStockContainer): void
    {
        if ($sourceStockContainer) {
            $this->sourceStockContainerId = $sourceStockContainer->getId();
        } else {
            $this->sourceStockContainerId = null;
        }
        $this->sourceStockContainer = $sourceStockContainer;
    }

    public function getSourceSpecialStockLocationTechnicalName(): ?string
    {
        return $this->sourceSpecialStockLocationTechnicalName;
    }

    public function setSourceSpecialStockLocationTechnicalName(?string $sourceSpecialStockLocationTechnicalName): void
    {
        if ($this->sourceSpecialStockLocation
            && $this->sourceSpecialStockLocation->getTechnicalName() !== $sourceSpecialStockLocationTechnicalName
        ) {
            $this->sourceSpecialStockLocation = null;
        }
        $this->sourceSpecialStockLocationTechnicalName = $sourceSpecialStockLocationTechnicalName;
    }

    public function getSourceSpecialStockLocation(): ?SpecialStockLocationEntity
    {
        if (!$this->sourceSpecialStockLocation && $this->sourceSpecialStockLocationTechnicalName) {
            throw new AssociationNotLoadedException('sourceSpecialStockLocation', $this);
        }

        return $this->sourceSpecialStockLocation;
    }

    public function setSourceSpecialStockLocation(?SpecialStockLocationEntity $sourceSpecialStockLocation): void
    {
        if ($sourceSpecialStockLocation) {
            $this->sourceSpecialStockLocationTechnicalName = $sourceSpecialStockLocation->getTechnicalName();
        }
        $this->sourceSpecialStockLocation = $sourceSpecialStockLocation;
    }

    public function getDestinationLocationSnapshot(): ?array
    {
        return $this->destinationLocationSnapshot;
    }

    public function setDestinationLocationSnapshot(?array $destinationLocationSnapshot): void
    {
        $this->destinationLocationSnapshot = $destinationLocationSnapshot;
    }

    public function getDestinationLocationTypeTechnicalName(): string
    {
        return $this->destinationLocationTypeTechnicalName;
    }

    public function setDestinationLocationTypeTechnicalName(string $destinationLocationTypeTechnicalName): void
    {
        if ($this->destinationLocationType
            && $this->destinationLocationType->getTechnicalName() !== $destinationLocationTypeTechnicalName
        ) {
            $this->destinationLocationType = null;
        }
        $this->destinationLocationTypeTechnicalName = $destinationLocationTypeTechnicalName;
    }

    public function getDestinationLocationType(): LocationTypeEntity
    {
        if (!$this->destinationLocationType) {
            throw new AssociationNotLoadedException('destinationLocationType', $this);
        }

        return $this->destinationLocationType;
    }

    public function setDestinationLocationType(LocationTypeEntity $destinationLocationType): void
    {
        $this->destinationLocationType = $destinationLocationType;
        $this->destinationLocationTypeTechnicalName = $destinationLocationType->getTechnicalName();
    }

    public function getDestinationWarehouseId(): ?string
    {
        return $this->destinationWarehouseId;
    }

    public function setDestinationWarehouseId(?string $destinationWarehouseId): void
    {
        if ($this->destinationWarehouse && $this->destinationWarehouse->getId() !== $destinationWarehouseId) {
            $this->destinationWarehouse = null;
        }
        $this->destinationWarehouseId = $destinationWarehouseId;
    }

    public function getDestinationWarehouse(): ?WarehouseEntity
    {
        if (!$this->destinationWarehouse && $this->destinationWarehouseId) {
            throw new AssociationNotLoadedException('destinationWarehouse', $this);
        }

        return $this->destinationWarehouse;
    }

    public function setDestinationWarehouse(?WarehouseEntity $destinationWarehouse): void
    {
        if ($destinationWarehouse) {
            $this->destinationWarehouseId = $destinationWarehouse->getId();
        }
        $this->destinationWarehouse = $destinationWarehouse;
    }

    public function getDestinationBinLocationId(): ?string
    {
        return $this->destinationBinLocationId;
    }

    public function setDestinationBinLocationId(?string $destinationBinLocationId): void
    {
        if ($this->destinationBinLocation && $this->destinationBinLocation->getId() !== $destinationBinLocationId) {
            $this->destinationBinLocation = null;
        }
        $this->destinationBinLocationId = $destinationBinLocationId;
    }

    public function getDestinationBinLocation(): ?BinLocationEntity
    {
        if (!$this->destinationBinLocation && $this->destinationBinLocationId) {
            throw new AssociationNotLoadedException('destinationBinLocation', $this);
        }

        return $this->destinationBinLocation;
    }

    public function setDestinationBinLocation(?BinLocationEntity $destinationBinLocation): void
    {
        if ($destinationBinLocation) {
            $this->destinationBinLocationId = $destinationBinLocation->getId();
        }
        $this->destinationBinLocation = $destinationBinLocation;
    }

    public function getDestinationOrderId(): ?string
    {
        return $this->destinationOrderId;
    }

    public function setDestinationOrderId(?string $destinationOrderId): void
    {
        if ($this->destinationOrder &&
            $this->destinationOrder->getId() !== $destinationOrderId) {
            $this->destinationOrder = null;
        }
        $this->destinationOrderId = $destinationOrderId;
    }

    public function getDestinationOrderVersionId(): ?string
    {
        return $this->destinationOrderVersionId;
    }

    public function setDestinationOrderVersionId(?string $destinationOrderVersionId): void
    {
        if ($this->destinationOrder && $this->destinationOrder->getVersionId() !== $destinationOrderVersionId) {
            $this->destinationOrder = null;
        }
        $this->destinationOrderVersionId = $destinationOrderVersionId;
    }

    public function getDestinationOrder(): ?OrderEntity
    {
        if (!$this->destinationOrder && $this->destinationOrderId) {
            throw new AssociationNotLoadedException('destinationOrder', $this);
        }

        return $this->destinationOrder;
    }

    public function setDestinationOrder(?OrderEntity $destinationOrder): void
    {
        if ($destinationOrder) {
            $this->destinationOrderId = $destinationOrder->getId();
        } else {
            $this->destinationOrderId = null;
        }
        $this->destinationOrder = $destinationOrder;
    }

    public function getDestinationReturnOrderId(): ?string
    {
        return $this->destinationReturnOrderId;
    }

    public function setDestinationReturnOrderId(?string $destinationReturnOrderId): void
    {
        if ($this->destinationReturnOrder && $this->destinationReturnOrder->getId() !== $destinationReturnOrderId) {
            $this->destinationReturnOrder = null;
        }
        $this->destinationReturnOrderId = $destinationReturnOrderId;
    }

    public function getdestinationReturnOrder(): ?ReturnOrderEntity
    {
        if (!$this->destinationReturnOrder && $this->destinationReturnOrderId) {
            throw new AssociationNotLoadedException('destinationReturnOrder', $this);
        }

        return $this->destinationReturnOrder;
    }

    public function setdestinationReturnOrder(?ReturnOrderEntity $destinationReturnOrder): void
    {
        if ($destinationReturnOrder) {
            $this->destinationReturnOrderId = $destinationReturnOrder->getId();
        }
        $this->destinationReturnOrder = $destinationReturnOrder;
    }

    public function getDestinationSupplierOrderId(): ?string
    {
        return $this->destinationSupplierOrderId;
    }

    public function setDestinationSupplierOrderId(?string $destinationSupplierOrderId): void
    {
        if ($destinationSupplierOrderId && $this->destinationSupplierOrder) {
            $this->destinationSupplierOrder->setId($destinationSupplierOrderId);
        }

        $this->destinationSupplierOrderId = $destinationSupplierOrderId;
    }

    public function getDestinationSupplierOrder(): ?SupplierOrderEntity
    {
        if (!$this->destinationSupplierOrder && $this->destinationSupplierOrderId) {
            throw new AssociationNotLoadedException('destinationSupplierOrder', $this);
        }

        return $this->destinationSupplierOrder;
    }

    public function setDestinationSupplierOrder(?SupplierOrderEntity $destinationSupplierOrder): void
    {
        if ($destinationSupplierOrder) {
            $this->destinationSupplierOrderId = $destinationSupplierOrder->getId();
        } else {
            $this->destinationSupplierOrderId = null;
        }
        $this->destinationSupplierOrder = $destinationSupplierOrder;
    }

    public function getDestinationStockContainerId(): ?string
    {
        return $this->destinationStockContainerId;
    }

    public function setDestinationStockContainerId(?string $destinationStockContainerId): void
    {
        if ($destinationStockContainerId && $this->destinationStockContainer && $this->destinationStockContainer->getId() !== $destinationStockContainerId) {
            $this->destinationStockContainer = null;
        }
        $this->destinationStockContainerId = $destinationStockContainerId;
    }

    public function getDestinationStockContainer(): ?StockContainerEntity
    {
        if ($this->destinationStockContainerId && !$this->destinationStockContainer) {
            throw new AssociationNotLoadedException('sourceStockContainer', $this);
        }

        return $this->destinationStockContainer;
    }

    public function setDestinationStockContainer(?StockContainerEntity $destinationStockContainer): void
    {
        if ($destinationStockContainer) {
            $this->destinationStockContainerId = $destinationStockContainer->getId();
        } else {
            $this->destinationStockContainerId = null;
        }
        $this->destinationStockContainer = $destinationStockContainer;
    }

    public function getDestinationSpecialStockLocationTechnicalName(): ?string
    {
        return $this->destinationSpecialStockLocationTechnicalName;
    }

    public function setDestinationSpecialStockLocationTechnicalName(
        ?string $destinationSpecialStockLocationTechnicalName
    ): void {
        if ($this->destinationSpecialStockLocation
            && $this->destinationSpecialStockLocation->getTechnicalName() !== $destinationSpecialStockLocationTechnicalName
        ) {
            $this->destinationSpecialStockLocation = null;
        }
        $this->destinationSpecialStockLocationTechnicalName = $destinationSpecialStockLocationTechnicalName;
    }

    public function getDestinationSpecialStockLocation(): ?SpecialStockLocationEntity
    {
        if (!$this->destinationSpecialStockLocation && $this->destinationSpecialStockLocationTechnicalName) {
            throw new AssociationNotLoadedException('destinationSpecialStockLocation', $this);
        }

        return $this->destinationSpecialStockLocation;
    }

    public function setDestinationSpecialStockLocation(
        ?SpecialStockLocationEntity $destinationSpecialStockLocation
    ): void {
        if ($destinationSpecialStockLocation) {
            $this->destinationSpecialStockLocationTechnicalName = $destinationSpecialStockLocation->getTechnicalName();
        }
        $this->destinationSpecialStockLocation = $destinationSpecialStockLocation;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(?string $userId): void
    {
        if ($this->user && $this->user->getId() !== $userId) {
            $this->user = null;
        }
        $this->userId = $userId;
    }

    public function getUser(): ?UserEntity
    {
        if (!$this->user && $this->userId) {
            throw new AssociationNotLoadedException('user', $this);
        }

        return $this->user;
    }

    public function setUser(?UserEntity $user): void
    {
        if ($user) {
            $this->userId = $user->getId();
        }
        $this->user = $user;
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

    public function getProduct(): ?ProductEntity
    {
        if (!$this->product && $this->productId) {
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

    public function createStockLocationReferenceFromSource(): StockLocationReference
    {
        switch ($this->getSourceLocationTypeTechnicalName()) {
            case LocationTypeDefinition::TECHNICAL_NAME_ORDER:
                return StockLocationReference::order($this->getSourceOrderId());
            case LocationTypeDefinition::TECHNICAL_NAME_RETURN_ORDER:
                return StockLocationReference::returnOrder($this->getSourceReturnOrderId());
            case LocationTypeDefinition::TECHNICAL_NAME_SUPPLIER_ORDER:
                return StockLocationReference::supplierOrder($this->getSourceSupplierOrderId());
            case LocationTypeDefinition::TECHNICAL_NAME_BIN_LOCATION:
                return StockLocationReference::binLocation($this->getSourceBinLocationId());
            case LocationTypeDefinition::TECHNICAL_NAME_WAREHOUSE:
                return StockLocationReference::warehouse($this->getSourceWarehouseId());
            case LocationTypeDefinition::TECHNICAL_NAME_STOCK_CONTAINER:
                return StockLocationReference::stockContainer($this->getSourceStockContainerId());
            case LocationTypeDefinition::TECHNICAL_NAME_SPECIAL_STOCK_LOCATION:
                return StockLocationReference::specialStockLocation($this->getSourceSpecialStockLocationTechnicalName());
            default:
                break;
        }

        throw new LogicException(sprintf(
            'Missing implementation in method %s for location type %s.',
            __METHOD__,
            $this->getSourceLocationTypeTechnicalName(),
        ));
    }

    public function createStockLocationReferenceFromDestination(): StockLocationReference
    {
        switch ($this->getDestinationLocationTypeTechnicalName()) {
            case LocationTypeDefinition::TECHNICAL_NAME_ORDER:
                return StockLocationReference::order($this->getDestinationOrderId());
            case LocationTypeDefinition::TECHNICAL_NAME_RETURN_ORDER:
                return StockLocationReference::returnOrder($this->getDestinationReturnOrderId());
            case LocationTypeDefinition::TECHNICAL_NAME_SUPPLIER_ORDER:
                return StockLocationReference::supplierOrder($this->getDestinationSupplierOrderId());
            case LocationTypeDefinition::TECHNICAL_NAME_BIN_LOCATION:
                return StockLocationReference::binLocation($this->getDestinationBinLocationId());
            case LocationTypeDefinition::TECHNICAL_NAME_WAREHOUSE:
                return StockLocationReference::warehouse($this->getDestinationWarehouseId());
            case LocationTypeDefinition::TECHNICAL_NAME_STOCK_CONTAINER:
                return StockLocationReference::stockContainer($this->getDestinationStockContainerId());
            case LocationTypeDefinition::TECHNICAL_NAME_SPECIAL_STOCK_LOCATION:
                return StockLocationReference::specialStockLocation($this->getDestinationSpecialStockLocationTechnicalName());
            default:
                break;
        }

        throw new LogicException(sprintf(
            'Missing implementation in method %s for location type %s.',
            __METHOD__,
            $this->getDestinationLocationTypeTechnicalName(),
        ));
    }

    public function isAnySourcePrimaryKeySet(): bool
    {
        return $this->sourceBinLocationId ||
            $this->sourceWarehouseId ||
            $this->sourceOrderId ||
            $this->sourceSupplierOrderId ||
            $this->sourceReturnOrderId ||
            $this->sourceStockContainerId ||
            $this->sourceSpecialStockLocationTechnicalName;
    }

    public function isAnyDestinationPrimaryKeySet(): bool
    {
        return $this->destinationBinLocationId ||
            $this->destinationWarehouseId ||
            $this->destinationOrderId ||
            $this->destinationSupplierOrderId ||
            $this->destinationReturnOrderId ||
            $this->destinationStockContainerId ||
            $this->destinationSpecialStockLocationTechnicalName;
    }
}
