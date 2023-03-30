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

use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @deprecated Will be removed in 4.0.0. Use EntityUpdateRestrictor instead.
 */
abstract class AbstractEntityUpdateRestrictor implements EventSubscriberInterface
{
    public const ERROR_CODE_NAMESPACE = 'PICKWARE_DAL_BUNDLE__ENTITY_UPDATE_RESTRICTOR';

    private const IGNORED_UPDATE_FIELDS = [
        'updated_at',
    ];

    /**
     * @var string[]
     */
    private array $classNames;

    /**
     * @var string[]
     */
    private array $updateAllowedFieldsByClassName;

    /**
     * @var string[]
     */
    private array $contextScopes;

    /**
     * @param string[] $classNames
     * @param string[][] $updateAllowedFieldsByClassName field names must be the storage name (i.e. database column name
     * in snake_case). Can be left empty per entity to restrict all updates on this entity.
     * @param string[] $contextScopes
     */
    public function __construct(array $classNames, array $updateAllowedFieldsByClassName, array $contextScopes = [])
    {
        $this->classNames = $classNames;
        $this->updateAllowedFieldsByClassName = $updateAllowedFieldsByClassName;
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
            if (!($command instanceof UpdateCommand) || !in_array($className, $this->classNames, true)) {
                continue;
            }
            $entityName = $command->getDefinition()->getEntityName();

            $updatedAllowedFields = [];
            if (array_key_exists($className, $this->updateAllowedFieldsByClassName)) {
                $updatedAllowedFields = $this->updateAllowedFieldsByClassName[$className];
            }

            if (count($updatedAllowedFields) === 0) {
                // No fields are allowed. The entity is generally updated-restricted.
                $message = sprintf('A %s cannot be updated.', $entityName);
                $errorCode = sprintf(
                    '%s__UPDATE__%s',
                    self::ERROR_CODE_NAMESPACE,
                    mb_strtoupper($entityName),
                );
                $violations->add(new ConstraintViolation(
                    $message,
                    $message,
                    ['entity' => $entityName],
                    null,
                    '/',
                    null,
                    null,
                    $errorCode,
                ));

                continue;
            }

            $prohibitedUpdatedFields = [];
            foreach ($command->getPayload() as $key => $value) {
                if (!in_array($key, self::IGNORED_UPDATE_FIELDS, true)
                    && !in_array($key, $updatedAllowedFields, true)) {
                    $prohibitedUpdatedFields[] = $key;
                }
            }
            if (count($prohibitedUpdatedFields) === 0) {
                continue;
            }

            $message = sprintf(
                'Only the following fields of %s are allowed to be updated: %s. Update-prohibited fields found: %s.',
                $entityName,
                implode(', ', $updatedAllowedFields),
                implode(', ', array_unique($prohibitedUpdatedFields)),
            );
            $errorCode = sprintf(
                '%s__PARTIAL_UPDATE__%s',
                self::ERROR_CODE_NAMESPACE,
                mb_strtoupper($entityName),
            );
            $violations->add(new ConstraintViolation(
                $message,
                $message,
                [
                    'allowedFields' => $updatedAllowedFields,
                    'notAllowedFields' => $prohibitedUpdatedFields,
                ],
                null,
                '/',
                null,
                null,
                $errorCode,
            ));
        }

        if ($violations->count() > 0) {
            $event->getExceptions()->add(new WriteConstraintViolationException($violations));
        }
    }
}
