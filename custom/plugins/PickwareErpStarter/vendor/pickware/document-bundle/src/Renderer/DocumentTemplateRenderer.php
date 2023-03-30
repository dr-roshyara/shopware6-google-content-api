<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DocumentBundle\Renderer;

use InvalidArgumentException;
use Pickware\DalBundle\ContextFactory;
use Pickware\DalBundle\EntityManager;
use Shopware\Core\Checkout\Document\DocumentService;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\Language\LanguageEntity;
use Twig\Environment as TwigEnvironment;

class DocumentTemplateRenderer
{
    private TwigEnvironment $twig;
    private TemplateFinder $templateFinder;
    private Translator $translator;
    private ContextFactory $contextFactory;
    private EntityManager $entityManager;

    public function __construct(
        TwigEnvironment $twig,
        TemplateFinder $templateFinder,
        Translator $translator,
        ContextFactory $contextFactory,
        EntityManager $entityManager
    ) {
        $this->twig = $twig;
        $this->templateFinder = $templateFinder;
        $this->translator = $translator;
        $this->contextFactory = $contextFactory;
        $this->entityManager = $entityManager;
    }

    /**
     * @param string|Context $languageIdOrContext @deprecated next-major: will be `string $languageId`
     * @param Context|string|null $contextOrOptionalLocale @deprecated next-major: will be `Context $context`
     */
    public function render(
        string $templateName,
        array $parameters,
        $languageIdOrContext,
        $contextOrOptionalLocale
    ): string {
        // Parameter 3 and 4 should be a `string $languageId` and `Context $context`. This code still supports the old
        // parameters `Context $context` and `?string $localeCode` for backwards compatibility.
        if (is_string($languageIdOrContext) && $contextOrOptionalLocale instanceof Context) {
            /** @var LanguageEntity $language */
            $language = $this->entityManager->getByPrimaryKey(
                LanguageDefinition::class,
                $languageIdOrContext,
                $contextOrOptionalLocale,
                ['locale'],
            );
            $localeCode = $language->getLocale()->getCode();
            $documentContext = $this->contextFactory->createLocalizedContext($languageIdOrContext, $contextOrOptionalLocale);
        } elseif ($languageIdOrContext instanceof Context && is_string($contextOrOptionalLocale)) {
            $localeCode = $contextOrOptionalLocale;
            $documentContext = $this->contextFactory->createLocalizedContext($contextOrOptionalLocale, $languageIdOrContext);
        } elseif ($languageIdOrContext instanceof Context && !$contextOrOptionalLocale) {
            $localeCode = null;
            $documentContext = Context::createFrom($languageIdOrContext);
        } else {
            throw new InvalidArgumentException('Arguments 3 and 4 should be `string $languageId` and `Context $context`');
        }

        $template = $this->templateFinder->find($templateName);

        $documentContext->addState(DocumentService::GENERATING_PDF_STATE);
        // The 'context' template variable will be used in twig filters (e.g. currency formatting)
        $parameters['context'] = $documentContext;

        // Even if no locale code is provided, this preparation step is necessary to load all plugin snippets.
        $this->translator->loadCustomTranslations($localeCode, $documentContext);
        $rendered = $this->twig->render($template, $parameters);
        $this->translator->unloadCustomTranslations();

        return $rendered;
    }
}
