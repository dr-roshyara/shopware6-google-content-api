<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\InstallationLibrary\MailTemplate;

use Pickware\DalBundle\EntityManager;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeDefinition;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeEntity;
use Shopware\Core\Content\MailTemplate\MailTemplateActions;
use Shopware\Core\Content\MailTemplate\MailTemplateDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Event\EventAction\EventActionDefinition;

class MailTemplateUninstaller
{
    private EntityManager $entityManager;

    public function __construct(EntityManager$entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function uninstallMailTemplate(MailTemplate $mailTemplate): void
    {
        $context = Context::createDefaultContext();
        /** @var MailTemplateTypeEntity $mailTemplateType */
        $mailTemplateType = $this->entityManager->findOneBy(
            MailTemplateTypeDefinition::class,
            ['technicalName' => $mailTemplate->getTechnicalName()],
            $context,
        );

        if (!$mailTemplateType) {
            return;
        }

        $this->entityManager->deleteByCriteria(
            MailTemplateDefinition::class,
            ['mailTemplateTypeId' => $mailTemplateType->getId()],
            $context,
        );
        $this->entityManager->delete(
            MailTemplateTypeDefinition::class,
            [$mailTemplateType->getId()],
            $context,
        );
        $this->entityManager->deleteByCriteria(
            EventActionDefinition::class,
            (new Criteria())
                ->addFilter(new EqualsFilter('actionName', MailTemplateActions::MAIL_TEMPLATE_MAIL_SEND_ACTION))
                ->addFilter(new EqualsAnyFilter('eventName', $mailTemplate->getActionEvents())),
            $context,
        );
    }
}
