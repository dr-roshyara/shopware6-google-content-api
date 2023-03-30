<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShopwareExtensionsBundle\OrderDocument;

use DateTime;
use DateTimeInterface;
use Pickware\DalBundle\EntityManager;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeDefinition;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeEntity;
use Shopware\Core\Checkout\Document\DocumentConfiguration;
use Shopware\Core\Checkout\Document\DocumentConfigurationFactory;
use Shopware\Core\Checkout\Document\DocumentDefinition;
use Shopware\Core\Checkout\Document\DocumentService;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;

class OrderDocumentService
{
    private EntityManager $entityManager;
    private DocumentService $documentService;
    private NumberRangeValueGeneratorInterface $numberRangeValueGenerator;
    private MediaService $mediaService;

    public function __construct(
        EntityManager $entityManager,
        DocumentService $documentService,
        NumberRangeValueGeneratorInterface $numberRangeValueGenerator,
        MediaService $mediaService
    ) {
        $this->entityManager = $entityManager;
        $this->documentService = $documentService;
        $this->numberRangeValueGenerator = $numberRangeValueGenerator;
        $this->mediaService = $mediaService;
    }

    /**
     * @param array $options
     *          $options = [
     *              'documentNumber' => (string) Use this number instead of reserving a new one. Optional.
     *              'documentConfig' => (array) An array of document config keys to add to the document entity. Optional.
     *          ]
     */
    public function createDocumentWithTechnicalName(
        string $orderId,
        string $documentTypeTechnicalName,
        Context $context,
        array $options = []
    ): string {
        /** @var OrderEntity $order */
        $order = $this->entityManager->getByPrimaryKey(OrderDefinition::class, $orderId, $context);

        // Ensure that the document type exists
        $this->entityManager->getOneBy(
            DocumentTypeDefinition::class,
            ['technicalName' => $documentTypeTechnicalName],
            $context,
        );

        $documentNumber = $options['documentNumber'] ?? null;
        if ($documentNumber === null) {
            $documentNumber = $this->numberRangeValueGenerator->getValue(
                'document_' . $documentTypeTechnicalName,
                $context,
                $order->getSalesChannelId(),
            );
        }

        $documentConfig = $options['documentConfig'] ?? [];
        $documentIdStruct = $this->documentService->create(
            $orderId,
            $documentTypeTechnicalName,
            FileTypes::PDF,
            $this->createDocumentConfiguration($documentNumber, $documentConfig),
            $context,
        );

        return $documentIdStruct->getId();
    }

    /**
     * @param array $options
     *          $options = [
     *              'documentNumber' => (string) Use this number instead of reserving a new one. Optional.
     *              'documentConfig' => (array) An array of document config keys to add to the document entity. Optional.
     *          ]
     */
    public function createDocument(
        string $orderId,
        string $documentTypeId,
        Context $context,
        array $options = []
    ): string {
        /** @var DocumentTypeEntity $documentType */
        $documentType = $this->entityManager->getByPrimaryKey(DocumentTypeDefinition::class, $documentTypeId, $context);

        return $this->createDocumentWithTechnicalName(
            $orderId,
            $documentType->getTechnicalName(),
            $context,
            $options,
        );
    }

    /**
     * @param array $options
     *          $options = [
     *              'documentNumber' => (string) Use this number instead of reserving a new one. Optional.
     *              'documentConfig' => (array) An array of document config keys to add to the document entity. Optional.
     *              'documentFile'   => [
     *                  'mimeType' => (string) The mime type of the file. Required.
     *                  'extension' => (string) The file extension. Required.
     *                  'content' => (string) Required.
     *              ]
     *          ]
     */
    public function uploadDocument(
        string $orderId,
        string $documentTypeId,
        Context $context,
        array $options = []
    ): string {
        /** @var OrderEntity $order */
        $order = $this->entityManager->getByPrimaryKey(OrderDefinition::class, $orderId, $context);
        /** @var DocumentTypeEntity $documentType */
        $documentType = $this->entityManager->getByPrimaryKey(DocumentTypeDefinition::class, $documentTypeId, $context);

        $documentNumber = $options['documentNumber'] ?? null;
        $documentConfig = $options['documentConfig'] ?? [];
        $documentIdStruct = $this->documentService->create(
            $orderId,
            $documentType->getTechnicalName(),
            FileTypes::PDF,
            $this->createDocumentConfiguration($documentNumber, $documentConfig),
            $context,
        );

        $documentIdentifier = $documentConfig['documentIdentifier'] ?? $documentNumber;
        $this->addDocumentFileToDocument(
            $documentIdStruct->getId(),
            $options['documentFile'],
            $documentType->getTechnicalName() . '_' . $documentIdentifier . '_order_' . $order->getOrderNumber(),
            $context,
        );

        return $documentIdStruct->getId();
    }

    private function createDocumentConfiguration(?string $documentNumber, array $documentConfig): DocumentConfiguration
    {
        return DocumentConfigurationFactory::createConfiguration([
            'documentNumber' => $documentNumber,
            'documentDate' => (new DateTime('now'))->format(DateTimeInterface::ATOM),
            'custom' => array_replace_recursive(['invoiceNumber' => $documentNumber], $documentConfig),
        ]);
    }

    private function addDocumentFileToDocument(
        string $documentId,
        array $documentFile,
        string $fileName,
        Context $context
    ): void {
        $mediaId = null;
        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use (
            $documentFile,
            $fileName,
            &$mediaId
        ): void {
            $mediaId = $this->mediaService->saveFile(
                $documentFile['content'],
                $documentFile['extension'],
                $documentFile['mimeType'],
                $fileName,
                $context,
                'document',
            );
        });

        $this->entityManager->update(
            DocumentDefinition::class,
            [
                [
                    'id' => $documentId,
                    'documentMediaFileId' => $mediaId,
                ],
            ],
            $context,
        );
    }
}
