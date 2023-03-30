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

use Exception;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\ContextSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\Language\LanguageEntity;

class ContextFactory
{
    private EntityManager $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function deriveOrderContext(string $orderId, Context $context): Context
    {
        /** @var OrderEntity $order */
        $order = $this->entityManager->getByPrimaryKey(OrderDefinition::class, $orderId, $context, [
            'currency',
            'language',
        ]);

        // See Shopware's code:
        // https://github.com/shopware/platform/blob/d36be415b939a03d5db294e294fc8004ee840889/src/Core/Checkout/Order/SalesChannel/OrderService.php#L500-L518
        return new Context(
            $context->getSource(),
            $context->getRuleIds(),
            $order->getCurrencyId(),
            self::makeLanguageIdChain($order->getLanguage()),
            $context->getVersionId(),
            $order->getCurrencyFactor(),
            // Unlike Shopware, I don't think it is a good idea to change the inheritance behavior when you change into
            // the context of an order.
            $context->considerInheritance(),
            $order->getTaxStatus(),
        );
    }

    public function createWithSource(ContextSource $source, ?Context $context = null): Context
    {
        if (!$context) {
            return Context::createDefaultContext($source);
        }

        return new Context(
            $source,
            $context->getRuleIds(),
            $context->getCurrencyId(),
            $context->getLanguageIdChain(),
            $context->getVersionId(),
            $context->getCurrencyFactor(),
            $context->considerInheritance(),
            $context->getTaxState(),
            $context->getRounding(),
        );
    }

    /**
     * @param string $languageIdOrLocale @deprecated next-major: will be a `string $languageId`
     */
    public function createLocalizedContext(string $languageIdOrLocale, Context $context): Context
    {
        // The first parameter should be a language id. To keep the code backwards compatible with the old locale code
        // parameter, we match the string and fetch the entity accordingly.
        /** @var LanguageEntity $language */
        if (preg_match('/[a-z]{2}-[A-Z]{2}/', $languageIdOrLocale)) {
            $language = $this->entityManager->findFirstBy(
                LanguageDefinition::class,
                new FieldSorting('name', FieldSorting::DESCENDING),
                $context,
                ['locale.code' => $languageIdOrLocale],
            );
            if (!$language) {
                throw new Exception(sprintf('No language with locale code "%s" found.', $languageIdOrLocale));
            }
        } else {
            $language = $this->entityManager->getByPrimaryKey(
                LanguageDefinition::class,
                $languageIdOrLocale,
                $context,
            );
        }

        /** @var Context $localizedContext */
        $localizedContext = Context::createFrom($context);
        $localizedContext->assign([
            'languageIdChain' => self::makeLanguageIdChain($language),
        ]);

        return $localizedContext;
    }

    /**
     * @return string[]
     */
    private static function makeLanguageIdChain(LanguageEntity $language): array
    {
        if ($language->getId() === Defaults::LANGUAGE_SYSTEM) {
            return [Defaults::LANGUAGE_SYSTEM];
        }

        return array_values(array_unique(array_filter([
            $language->getId(),
            $language->getParentId(),
            Defaults::LANGUAGE_SYSTEM,
        ])));
    }
}
