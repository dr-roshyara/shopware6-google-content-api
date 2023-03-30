<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Translation;

use Pickware\DalBundle\EntityManager;
use Shopware\Core\Framework\Context;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\Snippet\Aggregate\SnippetSet\SnippetSetDefinition;
use Shopware\Core\System\Snippet\Aggregate\SnippetSet\SnippetSetEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

class Translator
{
    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var EntityManager
     */
    private $entityManager;

    public function __construct(
        TranslatorInterface $translator,
        RequestStack $requestStack,
        EntityManager $entityManager
    ) {
        $this->translator = $translator;
        $this->requestStack = $requestStack;
        $this->entityManager = $entityManager;
    }

    public function setTranslationLocale(string $localeCode, Context $context): void
    {
        // The Shopware Translator uses a SnippetSet to translate snippets. It expects the SnippetSetId to be present
        // on the request as an attribute. As the SnippetSetId is only set automatically for sales channel api and
        // storefront requests we need to set it manually when handling admin api requests.
        $snippetSet = $this->getSnippetSetForLocale($localeCode, $context);
        if (!$snippetSet) {
            throw TranslationException::noSnippetSetFoundForLocale($localeCode);
        }

        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            $request = new Request();
            $this->requestStack->push($request);
        }
        $request->attributes->set(
            SalesChannelRequest::ATTRIBUTE_DOMAIN_SNIPPET_SET_ID,
            $snippetSet->getId(),
        );
    }

    public function translate(string $snippetId, array $parameters = []): string
    {
        return $this->translator->trans($snippetId, $parameters);
    }

    private function getSnippetSetForLocale(string $localeCode, Context $context): ?SnippetSetEntity
    {
        return $this->entityManager->findBy(
            SnippetSetDefinition::class,
            [
                'iso' => $localeCode,
                'baseFile' => 'messages.' . $localeCode,
            ],
            $context,
        )->first();
    }
}
