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
use JsonSerializable;
use Pickware\PickwareErpStarter\Stock\Model\LocationTypeDefinition;
use Pickware\PickwareErpStarter\Stock\Model\SpecialStockLocationDefinition;
use ReturnTypeWillChange;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

class StockLocationReference implements JsonSerializable
{
    public const JSON_SCHEMA_FILE = __DIR__ . '/../Resources/json-schema/stock-location.schema.json';
    public const POSITION_SOURCE = 'source';
    public const POSITION_DESTINATION = 'destination';
    public const POSITIONS = [
        self::POSITION_SOURCE,
        self::POSITION_DESTINATION,
    ];

    private string $locationTypeTechnicalName;

    /**
     * @var string Name of the field that represents the primary key, e.g. "id", "technicalName"
     */
    private string $primaryKeyFieldName;

    /**
     * @var string Value of the field that represents the primary key, e.g. a UUID of a bin location or the technical
     *      name of a special stock location
     */
    private string $primaryKey;

    /**
     * @var string|null Name of the primary key's version field, if the primary key references a versioned entity
     */
    private ?string $primaryKeyVersionFieldName = null;

    private ?array $snapshot = null;

    private function __construct()
    {
        // The static methods should be used to create instances of this class.
    }

    /**
     * Jsonserializes this stock location reference. Corresponds to the supported input of the self::create function.
     *
     * @return string|array
     */
    #[ReturnTypeWillChange]
    public function jsonSerialize()/*: string|array */
    {
        if ($this->locationTypeTechnicalName === LocationTypeDefinition::TECHNICAL_NAME_SPECIAL_STOCK_LOCATION) {
            return $this->primaryKeyFieldName;
        }

        return [
            self::snakeToCamelCase($this->locationTypeTechnicalName) => [
                $this->getPrimaryKeyFieldName() => $this->getPrimaryKey(),
            ],
        ];
    }

    public static function binLocation(string $id): self
    {
        $self = new self();
        $self->locationTypeTechnicalName = LocationTypeDefinition::TECHNICAL_NAME_BIN_LOCATION;
        $self->primaryKey = $id;
        $self->primaryKeyFieldName = 'id';

        return $self;
    }

    public static function warehouse(string $id): self
    {
        $self = new self();
        $self->locationTypeTechnicalName = LocationTypeDefinition::TECHNICAL_NAME_WAREHOUSE;
        $self->primaryKey = $id;
        $self->primaryKeyFieldName = 'id';

        return $self;
    }

    public static function specialStockLocation(string $technicalName): self
    {
        $self = new self();
        $self->locationTypeTechnicalName = LocationTypeDefinition::TECHNICAL_NAME_SPECIAL_STOCK_LOCATION;
        $self->primaryKey = $technicalName;
        $self->primaryKeyFieldName = 'technicalName';

        return $self;
    }

    public static function order(string $id): self
    {
        $self = new self();
        $self->locationTypeTechnicalName = LocationTypeDefinition::TECHNICAL_NAME_ORDER;
        $self->primaryKey = $id;
        $self->primaryKeyFieldName = 'id';
        $self->primaryKeyVersionFieldName = 'versionId';

        return $self;
    }

    public static function returnOrder($id): self
    {
        $self = new self();
        $self->locationTypeTechnicalName = LocationTypeDefinition::TECHNICAL_NAME_RETURN_ORDER;
        $self->primaryKey = $id;
        $self->primaryKeyFieldName = 'id';
        $self->primaryKeyVersionFieldName = 'versionId';

        return $self;
    }

    public static function supplierOrder(string $id): self
    {
        $self = new self();
        $self->locationTypeTechnicalName = LocationTypeDefinition::TECHNICAL_NAME_SUPPLIER_ORDER;
        $self->primaryKey = $id;
        $self->primaryKeyFieldName = 'id';

        return $self;
    }

    public static function stockContainer(string $id): self
    {
        $self = new self();
        $self->locationTypeTechnicalName = LocationTypeDefinition::TECHNICAL_NAME_STOCK_CONTAINER;
        $self->primaryKey = $id;
        $self->primaryKeyFieldName = 'id';

        return $self;
    }

    public static function unknown(): self
    {
        return self::specialStockLocation(SpecialStockLocationDefinition::TECHNICAL_NAME_UNKNOWN);
    }

    public static function initialization(): self
    {
        return self::specialStockLocation(SpecialStockLocationDefinition::TECHNICAL_NAME_INITIALIZATION);
    }

    public static function productTotalStockChange(): self
    {
        return self::specialStockLocation(SpecialStockLocationDefinition::TECHNICAL_NAME_PRODUCT_TOTAL_STOCK_CHANGE);
    }

    public static function stockCorrection(): self
    {
        return self::specialStockLocation(SpecialStockLocationDefinition::TECHNICAL_NAME_STOCK_CORRECTION);
    }

    public static function import(): self
    {
        return self::specialStockLocation(SpecialStockLocationDefinition::TECHNICAL_NAME_IMPORT);
    }

    public static function shopwareMigration(): self
    {
        return self::specialStockLocation(SpecialStockLocationDefinition::TECHNICAL_NAME_SHOPWARE_MIGRATION);
    }

    /**
     * @param array|string $stockLocation
     */
    public static function create($stockLocation): self
    {
        if (!is_array($stockLocation) && !is_string($stockLocation)) {
            throw new InvalidArgumentException('Argument must be of type string or array.');
        }
        if (is_string($stockLocation)) {
            return StockLocationReference::specialStockLocation($stockLocation);
        }
        if (array_key_exists('warehouse', $stockLocation)) {
            return StockLocationReference::warehouse($stockLocation['warehouse']['id']);
        }
        if (array_key_exists('binLocation', $stockLocation)) {
            return StockLocationReference::binLocation($stockLocation['binLocation']['id']);
        }
        if (array_key_exists('order', $stockLocation)) {
            return StockLocationReference::order($stockLocation['order']['id']);
        }
        if (array_key_exists('stockContainer', $stockLocation)) {
            return StockLocationReference::stockContainer($stockLocation['stockContainer']['id']);
        }
        if (array_key_exists('returnOrder', $stockLocation)) {
            return StockLocationReference::returnOrder($stockLocation['returnOrder']['id']);
        }
        if (array_key_exists('supplierOrder', $stockLocation)) {
            return StockLocationReference::supplierOrder($stockLocation['supplierOrder']['id']);
        }

        // Due to the type checks and "specialStockLocation from string" wildcard, only an invalid array remains
        throw new InvalidArgumentException(sprintf(
            'Stock location could not be created from array with key(s): %s.',
            implode(', ', array_keys($stockLocation)),
        ));
    }

    public function toSourcePayload(): array
    {
        return $this->toPayloadWithPrefix(self::POSITION_SOURCE);
    }

    public function toDestinationPayload(): array
    {
        return $this->toPayloadWithPrefix(self::POSITION_DESTINATION);
    }

    public function toPayload(): array
    {
        return $this->toPayloadWithPrefix('');
    }

    private function toPayloadWithPrefix(string $prefix): array
    {
        $locationTypeTechnicalKeyName = lcfirst(sprintf('%sLocationTypeTechnicalName', $prefix));
        $snapshotKeyName = lcfirst(sprintf('%sLocationSnapshot', $prefix));
        $referencingKeyName = lcfirst(sprintf(
            '%s%s%s',
            $prefix,
            ucfirst(self::snakeToCamelCase($this->locationTypeTechnicalName)),
            ucfirst(self::snakeToCamelCase($this->primaryKeyFieldName)),
        ));

        $payload = [
            $locationTypeTechnicalKeyName => $this->locationTypeTechnicalName,
            $snapshotKeyName => $this->snapshot,
            $referencingKeyName => $this->primaryKey,
        ];

        if ($this->primaryKeyVersionFieldName) {
            $referencingVersionKeyName = lcfirst(sprintf(
                '%s%s%s',
                $prefix,
                ucfirst(self::snakeToCamelCase($this->locationTypeTechnicalName)),
                ucfirst(self::snakeToCamelCase($this->primaryKeyVersionFieldName)),
            ));
            $payload[$referencingVersionKeyName] = Defaults::LIVE_VERSION;
        }

        return $payload;
    }

    public function getFilterForStockDefinition(): EqualsFilter
    {
        return new EqualsFilter(
            self::snakeToCamelCase($this->locationTypeTechnicalName) . '.' . $this->primaryKeyFieldName,
            $this->primaryKey,
        );
    }

    private static function snakeToCamelCase($string): string
    {
        $converter = new CamelCaseToSnakeCaseNameConverter();

        return $converter->denormalize($string);
    }

    private static function camelCaseToSnakeCase($string): string
    {
        $converter = new CamelCaseToSnakeCaseNameConverter();

        return $converter->normalize($string);
    }

    public function getSnapshot(): ?array
    {
        return $this->snapshot;
    }

    public function setSnapshot(?array $snapshot): void
    {
        $this->snapshot = $snapshot;
    }

    public function getLocationTypeTechnicalName(): string
    {
        return $this->locationTypeTechnicalName;
    }

    public function getPrimaryKey(): string
    {
        return $this->primaryKey;
    }

    public function getPrimaryKeyFieldName(): string
    {
        return $this->primaryKeyFieldName;
    }

    public function getDatabasePrimaryKeyFieldName(string $stockLocationPosition): string
    {
        $this->validateStockMovementDirectionArgument($stockLocationPosition);

        return sprintf(
            '%s_%s_%s',
            $stockLocationPosition,
            $this->locationTypeTechnicalName,
            self::camelCaseToSnakeCase($this->primaryKeyFieldName),
        );
    }

    public function getDatabaseVersionFieldName(string $stockLocationPosition): ?string
    {
        $this->validateStockMovementDirectionArgument($stockLocationPosition);
        if (!$this->primaryKeyVersionFieldName) {
            return null;
        }

        return sprintf(
            '%s_%s_%s',
            $stockLocationPosition,
            $this->locationTypeTechnicalName,
            self::camelCaseToSnakeCase($this->primaryKeyVersionFieldName),
        );
    }

    private function validateStockMovementDirectionArgument(string $stockLocationPosition): void
    {
        if (!in_array($stockLocationPosition, self::POSITIONS)) {
            throw new InvalidArgumentException(sprintf(
                'Stock location position "%s" is invalid. Valid positions: %s.',
                $stockLocationPosition,
                implode(', ', self::POSITIONS),
            ));
        }
    }
}
