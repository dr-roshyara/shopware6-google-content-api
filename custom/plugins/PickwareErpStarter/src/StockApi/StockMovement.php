<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\StockApi;

use InvalidArgumentException;
use Shopware\Core\Framework\Uuid\Uuid;

class StockMovement
{
    public const JSON_SCHEMA_FILE = __DIR__ . '/../Resources/json-schema/stock-movements.schema.json';

    private string $id;
    private string $productId;
    private int $quantity;
    private StockLocationReference $source;
    private StockLocationReference $destination;
    private ?string $comment;
    private ?string $userId;

    private function __construct()
    {
        // Use static method create, as it has "named parameters" (in the form of an assoc array)
    }

    public static function create(array $array): self
    {
        $self = new self();

        if (!($array['source'] instanceof StockLocationReference)) {
            $array['source'] = StockLocationReference::create($array['source']);
        }
        if (!($array['destination'] instanceof StockLocationReference)) {
            $array['destination'] = StockLocationReference::create($array['destination']);
        }

        // The setters are used as they validate type and other constraints.
        $self->setId($array['id'] ?? Uuid::randomHex());
        $self->setProductId($array['productId']);
        if ($array['quantity'] >= 0) {
            $self->setQuantity($array['quantity']);
            $self->setSource($array['source']);
            $self->setDestination($array['destination']);
        } else {
            $self->setQuantity(-1 * $array['quantity']);
            // Swap source and destination
            $self->setSource($array['destination']);
            $self->setDestination($array['source']);
        }
        $self->setComment($array['comment'] ?? null);
        $self->setUserId($array['userId'] ?? null);

        return $self;
    }

    public function toPayload(): array
    {
        return array_merge(
            [
                'id' => $this->id,
                'quantity' => $this->quantity,
                'productId' => $this->productId,
                'comment' => $this->comment,
                'userId' => $this->userId,
            ],
            $this->getSource()->toSourcePayload(),
            $this->getDestination()->toDestinationPayload(),
        );
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        if (!Uuid::isValid($id)) {
            throw new InvalidArgumentException(sprintf(
                'Property id of class %s has to be a UUID.',
                self::class,
            ));
        }
        $this->id = $id;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function setProductId(string $productId): void
    {
        if (!Uuid::isValid($productId)) {
            throw new InvalidArgumentException(sprintf(
                'Property productId of class %s has to be a UUID.',
                self::class,
            ));
        }
        $this->productId = $productId;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): void
    {
        if ($quantity < 1) {
            throw new InvalidArgumentException(sprintf(
                'Property quantity of class %s has to be greater than 0.',
                self::class,
            ));
        }
        $this->quantity = $quantity;
    }

    public function getSource(): StockLocationReference
    {
        return $this->source;
    }

    public function setSource(StockLocationReference $source): void
    {
        $this->source = $source;
    }

    public function getDestination(): StockLocationReference
    {
        return $this->destination;
    }

    public function setDestination(StockLocationReference $destination): void
    {
        $this->destination = $destination;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): void
    {
        $this->comment = $comment;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(?string $userId): void
    {
        if ($userId !== null && !Uuid::isValid($userId)) {
            throw new InvalidArgumentException(sprintf(
                'Property userId of class %s has to be a UUID.',
                self::class,
            ));
        }
        $this->userId = $userId;
    }
}
