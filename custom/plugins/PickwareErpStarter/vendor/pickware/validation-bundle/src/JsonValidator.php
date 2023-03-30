<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ValidationBundle;

use InvalidArgumentException;
use JsonException;
use JsonSchema\Validator;
use RuntimeException;

class JsonValidator
{
    public function validateJsonAgainstSchema(string $json, string $jsonSchemaFilePath): void
    {
        try {
            $object = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw JsonValidatorException::invalidJson($exception);
        }

        if (!file_exists($jsonSchemaFilePath)) {
            throw new RuntimeException(sprintf('Could find the requested JSON schema in \'%s\'', $jsonSchemaFilePath));
        }

        try {
            $schema = json_decode(file_get_contents($jsonSchemaFilePath), false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException(
                sprintf('JSON schema does not contain valid json. File path: %s', $jsonSchemaFilePath),
                0,
                $exception,
            );
        }
        $validator = new Validator();
        $validator->validate($object, $schema);
        if (!$validator->isValid()) {
            throw JsonValidatorException::jsonDoesNotValidateAgainstSchema(
                $validator->getErrors(),
                json_decode($json, true, 512, JSON_THROW_ON_ERROR),
            );
        }
    }
}
