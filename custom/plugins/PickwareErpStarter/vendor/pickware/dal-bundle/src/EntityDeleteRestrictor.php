<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DalBundle;

use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

class EntityDeleteRestrictor implements EventSubscriberInterface
{
    public const ERROR_CODE_NAMESPACE = 'PICKWARE_DAL_BUNDLE__ENTITY_DELETE_RESTRICTOR';

    /**
     * @var string[]
     */
    private array $entityDefinitionClassNames;

    /**
     * @var string[]
     */
    private array $allowedContextScopes;

    public function __construct(array $entityDefinitionClassNames, array $allowedContextScopes)
    {
        $this->entityDefinitionClassNames = $entityDefinitionClassNames;
        $this->allowedContextScopes = $allowedContextScopes;
    }

    public static function getSubscribedEvents(): array
    {
        return [PreWriteValidationEvent::class => 'preValidate'];
    }

    public function preValidate(PreWriteValidationEvent $event): void
    {
        if (in_array($event->getContext()->getScope(), $this->allowedContextScopes, true)) {
            return;
        }

        $commands = $event->getCommands();
        $violations = new ConstraintViolationList();

        foreach ($commands as $command) {
            $className = $command->getDefinition()->getClass();
            if (!($command instanceof DeleteCommand)
                || !in_array($className, $this->entityDefinitionClassNames, true)) {
                continue;
            }

            $entityName = $command->getDefinition()->getEntityName();
            $message = sprintf('A %s cannot be deleted.', $entityName);
            $violations->add(new ConstraintViolation(
                $message,
                $message,
                [],
                null,
                '/',
                null,
                null,
                sprintf(
                    '%s__%s',
                    self::ERROR_CODE_NAMESPACE,
                    mb_strtoupper($entityName),
                ),
            ));
        }

        if ($violations->count() > 0) {
            $event->getExceptions()->add(new WriteConstraintViolationException($violations));
        }
    }
}
