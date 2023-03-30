<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\UnitsOfMeasurement\Dimensions;

use JsonSerializable;
use Pickware\UnitsOfMeasurement\PhysicalQuantity\Length;

class BoxDimensions implements JsonSerializable
{
    /**
     * @var Length
     */
    private $height;

    /**
     * @var Length
     */
    private $width;

    /**
     * @var Length
     */
    private $length;

    public function __construct(Length $width, Length $height, Length $length)
    {
        $this->width = $width;
        $this->height = $height;
        $this->length = $length;
    }

    public function jsonSerialize(): array
    {
        return [
            'width' => $this->width,
            'height' => $this->height,
            'length' => $this->length,
        ];
    }

    public static function fromArray(array $array): self
    {
        return new self(
            Length::fromArray($array['width']),
            Length::fromArray($array['height']),
            Length::fromArray($array['length']),
        );
    }

    public function __clone()
    {
        $this->width = clone $this->width;
        $this->height = clone $this->height;
        $this->length = clone $this->length;
    }

    public function getHeight(): Length
    {
        return $this->height;
    }

    public function getWidth(): Length
    {
        return $this->width;
    }

    public function getLength(): Length
    {
        return $this->length;
    }
}
