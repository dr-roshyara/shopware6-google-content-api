<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\ImportExport\ReadWrite\Csv;

use Opis\JsonSchema\Schema;
use Opis\JsonSchema\Validator as OpisValidator;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\PickwareErpStarter\ImportExport\CsvErrorFactory;
use Shopware\Core\Framework\Context;

class Validator
{
    private CsvRowNormalizer $normalizer;
    private OpisValidator $validator;
    private Schema $validationSchema;

    public function __construct(CsvRowNormalizer $normalizer, array $validationSchemaArray)
    {
        $this->normalizer = $normalizer;
        $this->validator = new OpisValidator();
        $this->validationSchema = Schema::fromJsonString(json_encode($validationSchemaArray));
    }

    public function validateHeaderRow(array $headerRow, Context $context): JsonApiErrors
    {
        $errors = new JsonApiErrors();
        // Check for missing header
        $actualColumns = $this->normalizer->normalizeColumnNames($headerRow);
        if (count($actualColumns) === 0) {
            $errors->addError(CsvErrorFactory::missingHeaderRow());

            return $errors;
        }

        // Check for required columns
        $missingColumns = array_values(array_diff($this->validationSchema->resolve()->required, $actualColumns));
        foreach ($missingColumns as $missingColumn) {
            $errors->addError(CsvErrorFactory::missingColumn($missingColumn));
        }

        $additionalProperties = $this->validationSchema->resolve()->additionalProperties ?? true;

        if (!$additionalProperties) {
            $invalidColumns = array_values(array_diff($actualColumns, array_keys((array)$this->validationSchema->resolve()->properties)));
            foreach ($invalidColumns as $invalidColumn) {
                $errors->addError(CsvErrorFactory::invalidColumn($invalidColumn));
            }
        }

        // Check for duplicated columns
        $columnCounts = array_count_values($actualColumns);
        $normalizedToOriginalColumnNameMapping = $this->normalizer->mapNormalizedToOriginalColumnNames($headerRow);
        foreach ($columnCounts as $normalizedColumnName => $columnCount) {
            if ($columnCount === 1) {
                continue;
            }
            $errors->addError(CsvErrorFactory::duplicatedColumns(
                $normalizedColumnName,
                $normalizedToOriginalColumnNameMapping[$normalizedColumnName],
            ));
        }

        return $errors;
    }

    public function validateRow(array $normalizedRow, array $normalizedToOriginalColumnNameMapping): JsonApiErrors
    {
        $errors = new JsonApiErrors();
        // Check for required cell values
        foreach ($this->validationSchema->resolve()->required as $mandatoryColumn) {
            if ($normalizedRow[$mandatoryColumn] === '') {
                $errors->addError(CsvErrorFactory::missingCellValue(
                    $mandatoryColumn,
                    $normalizedToOriginalColumnNameMapping[$mandatoryColumn][0],
                ));
            }
        }

        if (count($errors)) {
            return $errors;
        }

        // Check the remaining constraints with validation
        $result = $this->validator->schemaValidation((object) $normalizedRow, $this->validationSchema);
        foreach ($result->getErrors() as $error) {
            $errors->addError(CsvErrorFactory::invalidCellValue($error));
        }

        return $errors;
    }
}
