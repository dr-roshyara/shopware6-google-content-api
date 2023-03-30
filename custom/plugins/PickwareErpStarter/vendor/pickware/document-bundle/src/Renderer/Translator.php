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

use Pickware\DalBundle\EntityManager;
use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\Snippet\Aggregate\SnippetSet\SnippetSetDefinition;
use Shopware\Core\System\Snippet\Aggregate\SnippetSet\SnippetSetEntity;
use Shopware\Core\System\Snippet\SnippetService;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use Symfony\Component\Translation\Formatter\MessageFormatterInterface;
use Symfony\Component\Translation\MessageCatalogueInterface;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Contracts\Translation\TranslatorTrait;

class Translator implements TranslatorInterface, TranslatorBagInterface, LocaleAwareInterface
{
    use TranslatorTrait;

    private const FALLBACK_LOCALE = 'en-GB';

    /**
     * @var TranslatorInterface|TranslatorBagInterface|WarmableInterface
     */
    private $baseTranslator;
    private EntityManager $entityManager;
    private CacheItemPoolInterface $cache;
    private MessageFormatterInterface $formatter;
    private SnippetService $snippetService;

    /**
     * When set is the base message catalogue (of the base translator and any other decorator) with all base snippets as
     * well as custom (plugin) snippets that are loaded in this translator.
     */
    private ?MessageCatalogueInterface $customMessageCatalogue = null;

    public function __construct(
        TranslatorInterface $baseTranslator,
        EntityManager $entityManager,
        CacheItemPoolInterface $cache,
        MessageFormatterInterface $formatter,
        SnippetService $snippetService
    ) {
        $this->baseTranslator = $baseTranslator;
        $this->entityManager = $entityManager;
        $this->cache = $cache;
        $this->formatter = $formatter;
        $this->snippetService = $snippetService;
    }

    public function trans(string $id, array $parameters = [], string $domain = null, string $locale = null): string
    {
        if ($this->customMessageCatalogue) {
            return $this->formatter->format(
                $this->customMessageCatalogue->get($id, $domain ?? 'messages'),
                $locale ?? self::FALLBACK_LOCALE,
                $parameters,
            );
        }

        // If the snippets of this translator are not loaded, use Shopware's base translator with their own logic (e.g.
        // more specific locale fallback logic)
        return $this->baseTranslator->trans($id, $parameters, $domain, $locale);
    }

    public function loadCustomTranslations(string $localeCode, Context $context): void
    {
        if ($this->customMessageCatalogue
            && $this->customMessageCatalogue->getLocale() === $localeCode) {
            // The message catalogue was already loaded and set for this locale
            return;
        }

        $snippetSet = $this->getSnippetSet($localeCode, $context);
        $this->customMessageCatalogue = $this->loadCatalogue($snippetSet->getId(), $localeCode);
        $this->setLocale($localeCode);
    }

    public function unloadCustomTranslations(): void
    {
        $this->customMessageCatalogue = null;
    }

    public function getCatalogue(string $locale = null): MessageCatalogueInterface
    {
        return $this->customMessageCatalogue ?? $this->baseTranslator->getCatalogue($locale);
    }

    private function loadCatalogue(string $snippetSetId, string $locale): MessageCatalogueInterface
    {
        $catalog = clone $this->baseTranslator->getCatalogue($locale);
        $snippets = $this->loadSnippetsWithCache($catalog, $snippetSetId);
        $catalog->add($snippets);

        return $catalog;
    }

    private function loadSnippetsWithCache(MessageCatalogueInterface $catalog, string $snippetSetId): array
    {
        $cacheItem = $this->cache->getItem('translation.catalog.' . $snippetSetId);
        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }

        // Loads all (custom and base) snippets from files and database
        $snippets = $this->snippetService->getStorefrontSnippets($catalog, $snippetSetId, self::FALLBACK_LOCALE);
        $cacheItem->set($snippets);
        $this->cache->save($cacheItem);

        return $snippets;
    }

    private function getSnippetSet(string $localeCode, Context $context): SnippetSetEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('iso', $localeCode),
            new EqualsFilter('baseFile', 'messages.' . $localeCode),
        );
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::ASCENDING));
        $snippetSet = $this->entityManager->findBy(
            SnippetSetDefinition::class,
            $criteria,
            $context,
        )->first();
        if (!$snippetSet) {
            throw DocumentRendererException::snippetSetNotFound($localeCode);
        }

        return $snippetSet;
    }

    public function getLocale(): string
    {
        if ($this->customMessageCatalogue) {
            return $this->locale;
        }

        return $this->baseTranslator->getLocale();
    }
}
