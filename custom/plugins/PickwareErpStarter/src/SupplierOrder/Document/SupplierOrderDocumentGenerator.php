<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\SupplierOrder\Document;

use DateTimeImmutable;
use Pickware\DocumentBundle\Renderer\DocumentTemplateRenderer;
use Pickware\PickwareErpStarter\Translation\Translator;
use Shopware\Core\Checkout\Document\FileGenerator\FileGeneratorRegistry;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Checkout\Document\GeneratedDocument;
use Shopware\Core\Framework\Context;

class SupplierOrderDocumentGenerator
{
    public const DOCUMENT_TEMPLATE_FILE = '@PickwareErpStarter/documents/supplier-order.html.twig';

    private DocumentTemplateRenderer $documentTemplateRenderer;
    private FileGeneratorRegistry $fileGeneratorRegistry;
    private Translator $translator;

    public function __construct(
        DocumentTemplateRenderer $documentTemplateRenderer,
        FileGeneratorRegistry $fileGeneratorRegistry,
        Translator $translator
    ) {
        $this->documentTemplateRenderer = $documentTemplateRenderer;
        $this->fileGeneratorRegistry = $fileGeneratorRegistry;
        $this->translator = $translator;
    }

    public function generate(array $templateVariables, string $languageId, Context $context): GeneratedDocument
    {
        $content = $this->documentTemplateRenderer->render(
            self::DOCUMENT_TEMPLATE_FILE,
            $templateVariables,
            $languageId,
            $context,
        );

        $fileGenerator = $this->fileGeneratorRegistry->getGenerator(FileTypes::PDF);
        $generatedDocument = new GeneratedDocument();
        $generatedDocument->setHtml($content);

        $generatedDocument->setFilename(
            sprintf(
                '%s.%s',
                $this->getFileName($templateVariables['localeCode'], $context),
                $fileGenerator->getExtension(),
            ),
        );

        $generatedDocument->setPageOrientation('portrait');
        $generatedDocument->setPageSize('a4');
        $generatedDocument->setFileBlob($fileGenerator->generate($generatedDocument));
        $generatedDocument->setContentType('application/pdf');

        return $generatedDocument;
    }

    public function getFileName(string $localeCode, Context $context): string
    {
        $this->translator->setTranslationLocale($localeCode, $context);

        return sprintf(
            $this->translator->translate('pickware-erp-starter.supplier-order-document.file-name'),
            (new DateTimeImmutable())->format('Y-m-d H_i_s'),
        );
    }
}
