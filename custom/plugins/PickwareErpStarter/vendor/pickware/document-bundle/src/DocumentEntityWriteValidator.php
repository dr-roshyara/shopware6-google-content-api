<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DocumentBundle;

use Pickware\DocumentBundle\Model\DocumentDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * This whole class is there just to ensure that a documents deep link code is exactly 32 characters long.
 */
class DocumentEntityWriteValidator implements EventSubscriberInterface
{
    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            PreWriteValidationEvent::class => 'onPreWriteValidation',
        ];
    }

    public function onPreWriteValidation(PreWriteValidationEvent $event): void
    {
        $documentCommands = array_values(array_filter($event->getCommands(), fn (WriteCommand $command) => $command->getDefinition()->getClass() === DocumentDefinition::class && !($command instanceof DeleteCommand)));

        $violations = $this->getDeepLinkCodeViolations($documentCommands);

        if ($violations->count() > 0) {
            $event->getExceptions()->add(new WriteConstraintViolationException($violations));
        }
    }

    /**
     * Checks every value of the deepLinkCode properties of the $documentCommands. If they do not have the expected
     * length, an appropriate violation is returned.
     *
     * @param WriteCommand[] $documentCommands
     */
    private function getDeepLinkCodeViolations(array $documentCommands): ConstraintViolationList
    {
        $violationMessageTemplate = 'The length of the property "deepLinkCode" must be exactly {{ length }} characters.';
        $parameters = ['{{ length }}' => DocumentDefinition::DEEP_LINK_CODE_LENGTH];
        $violationMessage = strtr($violationMessageTemplate, $parameters);

        $violations = new ConstraintViolationList();
        foreach ($documentCommands as $documentCommand) {
            $payload = $documentCommand->getPayload();
            if ($documentCommand->getEntityExistence()->exists() && !isset($payload['deep_link_code'])) {
                // Update without a change of the deepLinkCode
                continue;
            }
            if (mb_strlen($payload['deep_link_code']) !== DocumentDefinition::DEEP_LINK_CODE_LENGTH) {
                $violations[] = new ConstraintViolation(
                    $violationMessage,
                    $violationMessageTemplate,
                    $parameters,
                    null, // ???
                    $documentCommand->getPath() . '/deepLinkCode',
                    $payload['deep_link_code'],
                );
            }
        }

        return $violations;
    }
}
