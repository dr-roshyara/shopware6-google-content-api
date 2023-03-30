<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShopwareExtensionsBundle\Event;

use InvalidArgumentException;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\HttpUtils\JsonApi\JsonApiErrorSerializable;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @deprecated tag:next-major. This class will be removed without successor.
 */
class PickwareValidationEvent extends Event
{
    /**
     * @var JsonApiError[]
     */
    private array $jsonApiErrors = [];

    /**
     * @var JsonApiErrorSerializable[]
     */
    private array $jsonApiErrorSerializables = [];

    /**
     * @var PickwareValidationViolation[]
     */
    private array $violations = [];

    public function __construct()
    {
    }

    /**
     * @deprecated tag:next-major Will be removed without successor.
     *
     * @param JsonApiErrorSerializable|JsonApiError|PickwareValidationViolation $error
     */
    public function addError($error): void
    {
        if (!($error instanceof JsonApiErrorSerializable) && !($error instanceof JsonApiError) && !($error instanceof PickwareValidationViolation)) {
            throw new InvalidArgumentException(sprintf(
                'Argument \'$error\' must be of type %s. Deprecated types %s and %s are also allowed.',
                PickwareValidationViolation::class,
                JsonApiErrorSerializable::class,
                JsonApiError::class,
            ));
        }

        if ($error instanceof PickwareValidationViolation) {
            $this->addViolation($error);
        } elseif ($error instanceof JsonApiErrorSerializable) {
            $this->jsonApiErrorSerializables[] = $error;
        } elseif ($error instanceof JsonApiError) {
            $this->jsonApiErrors[] = $error;
        }
    }

    /**
     * @deprecated tag:next-major Will be removed without successor.
     */
    public function addViolation(PickwareValidationViolation $violation): void
    {
        $this->violations[] = $violation;
    }

    /**
     * @deprecated tag:next-major Will be removed without successor.
     */
    public function getJsonApiErrors(): JsonApiErrors
    {
        $jsonApiErrors = new JsonApiErrors();

        foreach ($this->jsonApiErrors as $error) {
            $jsonApiErrors->addError($error);
        }
        foreach ($this->jsonApiErrorSerializables as $error) {
            $jsonApiErrors->addError($error->serializeToJsonApiError());
        }
        foreach ($this->violations as $violation) {
            $jsonApiErrors->addError($violation->serializeToJsonApiError());
        }

        return $jsonApiErrors;
    }
}
