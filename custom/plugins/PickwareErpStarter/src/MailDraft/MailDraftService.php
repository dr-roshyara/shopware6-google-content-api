<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\MailDraft;

use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\MailDraft\DependencyInjection\MailTemplateContentGeneratorRegistry;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Mail\Service\AbstractMailSender;
use Shopware\Core\Content\Mail\Service\MailFactory;
use Shopware\Core\Content\MailTemplate\MailTemplateDefinition;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Content\MailTemplate\Service\Event\MailSentEvent;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\Context;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;

class MailDraftService
{
    private EntityManager $entityManager;
    private StringTemplateRenderer $templateRenderer;
    private MailTemplateContentGeneratorRegistry $generatorRegistry;
    private MailFactory $mailFactory;
    private AbstractMailSender $mailSender;
    private MailDraftValidator $mailDraftValidator;
    private MailDraftAttachmentFactory $mailDraftAttachmentFactory;
    private LoggerInterface $logger;
    private EventDispatcherInterface $eventDispatcher;
    private MailDraftConfigurationService $mailDraftConfigurationService;

    public function __construct(
        EntityManager $entityManager,
        StringTemplateRenderer $templateRenderer,
        MailTemplateContentGeneratorRegistry $generatorRegistry,
        MailFactory $mailFactory,
        AbstractMailSender $mailSender,
        MailDraftValidator $mailDraftValidator,
        MailDraftAttachmentFactory $mailDraftAttachmentFactory,
        LoggerInterface $logger,
        EventDispatcherInterface $eventDispatcher,
        MailDraftConfigurationService $mailDraftConfigurationService
    ) {
        $this->entityManager = $entityManager;
        $this->templateRenderer = $templateRenderer;
        $this->generatorRegistry = $generatorRegistry;
        $this->mailFactory = $mailFactory;
        $this->mailSender = $mailSender;
        $this->mailDraftValidator = $mailDraftValidator;
        $this->mailDraftAttachmentFactory = $mailDraftAttachmentFactory;
        $this->logger = $logger;
        $this->eventDispatcher = $eventDispatcher;
        $this->mailDraftConfigurationService = $mailDraftConfigurationService;
    }

    public function create(
        string $mailTemplateId,
        array $recipients,
        array $recipientsBcc,
        array $templateVariables,
        array $templateContentGeneratorOptions,
        Context $localizedContext
    ): MailDraft {
        /** @var MailTemplateEntity $mailTemplate */
        $mailTemplate = $this->entityManager->getByPrimaryKey(
            MailTemplateDefinition::class,
            $mailTemplateId,
            $localizedContext,
            ['mailTemplateType'],
        );
        $mailDraftData = [
            'recipients' => $recipients,
            'recipientsBcc' => $recipientsBcc,
            'senderMail' => $this->mailDraftConfigurationService->getDefaultSenderEmailAddress(),
        ];
        $translatableMailTemplateProperties = [
            'senderName',
            'contentPlain',
            'contentHtml',
            'subject',
        ];

        $mailTemplateTypeTechnicalName = $mailTemplate->getMailTemplateType()->getTechnicalName();

        $generatedTemplateVariables = [];
        $generator = $this->generatorRegistry->getGeneratorByTechnicalName($mailTemplateTypeTechnicalName);
        if ($generator) {
            $generatedTemplateVariables = $generator->generateContent($localizedContext, $templateContentGeneratorOptions);
        }

        foreach ($translatableMailTemplateProperties as $translatableMailTemplateProperty) {
            $mailDraftData[$translatableMailTemplateProperty] = $this->getRenderedMailTemplateProperty(
                $mailTemplate,
                $translatableMailTemplateProperty,
                array_merge($generatedTemplateVariables, $templateVariables),
                $localizedContext,
            );
        }
        // Note that attachments are not added here but can be added to the mail draft (by the Administration) before
        // the mail is sent.

        return MailDraft::fromArray($mailDraftData);
    }

    public function send(MailDraft $mailDraft, Context $context): void
    {
        $this->mailDraftValidator->validate($mailDraft);

        $attachments = array_map(function (array $attachment) use ($context) {
            return $this->mailDraftAttachmentFactory
                ->createAttachment($attachment, $context)
                ->jsonSerialize();
        }, $mailDraft->getAttachments());

        $mail = $this->mailFactory->create(
            $mailDraft->getSubject(),
            $mailDraft->getCombinedSender(),
            $mailDraft->getCombinedRecipients(),
            $mailDraft->getContents(),
            [],
            $mailDraft->getAdditionalData(),
            [],
        );

        $mail->addBcc(...$this->formatMailAddresses($mailDraft->getCombinedRecipientsBcc()));

        foreach ($attachments as $attachment) {
            $mail->attach(
                $attachment['content'],
                $attachment['fileName'],
                $attachment['mimeType'],
            );
        }

        try {
            $this->mailSender->send($mail);

            $this->eventDispatcher->dispatch(
                new MailSentEvent(
                    $mailDraft->getSubject(),
                    $mailDraft->getCombinedRecipients(),
                    $mailDraft->getContents(),
                    $context,
                ),
                MailSentEvent::EVENT_NAME,
            );
        } catch (Throwable $exception) {
            $this->logger->error('Could not send mail', [
                'message' => $exception->getMessage(),
                'stackTrace' => $exception->getTraceAsString(),
                'code' => $exception->getCode(),
                'mail' => $mailDraft,
            ]);

            throw $exception;
        }
    }

    private function getRenderedMailTemplateProperty(
        MailTemplateEntity $mailTemplate,
        string $propertyName,
        array $templateVariables,
        Context $context
    ): string {
         return $this->templateRenderer->render(
             $mailTemplate->getTranslation($propertyName) ?? '',
             $templateVariables,
             $context,
         );
    }

    private function formatMailAddresses(array $addresses): array
    {
        $formattedAddresses = [];
        foreach ($addresses as $mail => $name) {
            $formattedAddresses[] = $name . ' <' . $mail . '>';
        }

        return $formattedAddresses;
    }
}
