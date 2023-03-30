<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DalBundle\ExceptionHandling;

use Exception;
use Shopware\Core\Framework\ShopwareHttpException;
use Throwable;

/**
 * Class UniqueIndexHttpException
 *
 * Extends Shopware's ShopwareHttpException for DBAL unique index violation exceptions.
 */
class UniqueIndexHttpException extends ShopwareHttpException
{
    private string $errorCode;

    public function __construct(string $errorCode, string $message, array $parameters = [], ?Throwable $e = null)
    {
        parent::__construct($message, $parameters, $e);

        $this->errorCode = $errorCode;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public static function create(
        UniqueIndexExceptionMapping $uniqueIndexExceptionMapping,
        Exception $previousException
    ): self {
        $errorDetail = vsprintf(
            'Entity "%s" with given fields (%s) already exists.',
            [
                $uniqueIndexExceptionMapping->getEntityName(),
                implode(', ', $uniqueIndexExceptionMapping->getFields()),
            ],
        );

        return new self(
            $uniqueIndexExceptionMapping->getErrorCodeToAssign(),
            $errorDetail,
            [
                'index' => $uniqueIndexExceptionMapping->getUniqueIndexName(),
                'entity' => $uniqueIndexExceptionMapping->getEntityName(),
                'fields' => $uniqueIndexExceptionMapping->getFields(),
            ],
            $previousException,
        );
    }
}
