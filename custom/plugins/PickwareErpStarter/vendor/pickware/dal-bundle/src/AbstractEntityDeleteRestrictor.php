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

/**
 * @deprecated Will be removed in 4.0.0. Use EntityDeleteRestrictor instead.
 */
abstract class AbstractEntityDeleteRestrictor implements EventSubscriberInterface
{
    public const ERROR_CODE_NAMESPACE = 'PICKWARE_DAL_BUNDLE__ENTITY_DELETE_RESTRICTOR';

    /**
     * @var string[]
     */
    private array $classNames;

    /**
     * @var string[]
     */
    private array $contextScopes;

    public function __construct(array $classNames, array $contextScopes = [])
    {
        $this->classNames = $classNames;
        $this->contextScopes = $contextScopes;
    }

    public static function getSubscribedEvents(): array
    {
        return [PreWriteValidationEvent::class => 'preValidate'];
    }

    public function preValidate(PreWriteValidationEvent $event): void
    {
        if (count($this->contextScopes) > 0
            && !in_array($event->getContext()->getScope(), $this->contextScopes, true)) {
            return;
        }

        $commands = $event->getCommands();
        $violations = new ConstraintViolationList();

        foreach ($commands as $command) {
            $className = $command->getDefinition()->getClass();
            if (!($command instanceof DeleteCommand) || !in_array($className, $this->classNames, true)) {
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
