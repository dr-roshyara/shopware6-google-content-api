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

use \Exception;
use Doctrine\DBAL\DBALException;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;

class UniqueIndexExceptionHandler implements ExceptionHandlerInterface
{
    /**
     * @var UniqueIndexExceptionMapping[]
     */
    private array $uniqueIndexExceptionMappings;

    public function __construct(array $uniqueIndexExceptionMappings)
    {
        $this->uniqueIndexExceptionMappings = $uniqueIndexExceptionMappings;
    }

    public function getPriority(): int
    {
        return ExceptionHandlerInterface::PRIORITY_DEFAULT;
    }

    /**
     * Note: the second parameter of this function is deprecated (FEATURE_NEXT_16640) and will be removed. We already
     * updated this function and do not use the deprecated parameter anymore.
     */
    public function matchException(Exception $exception, ?WriteCommand $command = null): ?Exception
    {
        if (!$exception instanceof DBALException) {
            return null;
        }

        foreach ($this->uniqueIndexExceptionMappings as $uniqueIndexExceptionMapping) {
            $indexViolationPattern = sprintf(
                '/SQLSTATE\\[23000\\]:.*1062 Duplicate entry .*%s.*/',
                $uniqueIndexExceptionMapping->getUniqueIndexName(),
            );
            if (preg_match($indexViolationPattern, $exception->getMessage())) {
                return UniqueIndexHttpException::create(
                    $uniqueIndexExceptionMapping,
                    $exception,
                );
            }
        }

        return null;
    }
}
