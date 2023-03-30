<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\HttpUtils\JsonApi;

use Countable;
use JsonSerializable;

class JsonApiErrors implements JsonSerializable, Countable
{
    /**
     * @var JsonApiError[]
     */
    private array $errors;

    public function __construct(array $errors = [])
    {
        $this->errors = $errors;
    }

    public static function noError(): self
    {
        return new self();
    }

    public function jsonSerialize(): array
    {
        return $this->errors;
    }

    public function count(): int
    {
        return count($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function setErrors(array $errors): void
    {
        $this->errors = $errors;
    }

    public function addError(JsonApiError $error): void
    {
        $this->errors[] = $error;
    }

    public function addErrors(JsonApiError ...$errors): void
    {
        foreach ($errors as $error) {
            $this->errors[] = $error;
        }
    }

    public function getCondensedStatus(): ?string
    {
        $statusCodes = array_map(fn (JsonApiError $error) => $error->getStatus() ? ((int) $error->getStatus()) : null, $this->errors);

        $statusCodes = array_values(array_unique(array_filter($statusCodes)));

        if (count($statusCodes) === 0) {
            return null;
        }
        if (count($statusCodes) === 1) {
            return (string) $statusCodes[0];
        }

        $highestStatusCode = max($statusCodes);

        $condensedStatusCode = ((int) floor($highestStatusCode / 100)) * 100;

        return (string) $condensedStatusCode;
    }

    public function toJsonApiErrorResponse(): JsonApiErrorResponse
    {
        return new JsonApiErrorResponse($this);
    }
}
