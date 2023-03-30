<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Picklist;

use Pickware\DalBundle\EntityManager;
use Shopware\Core\Checkout\Document\DocumentConfiguration;
use Shopware\Core\Checkout\Document\DocumentDefinition;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\Document\DocumentIdStruct;
use Shopware\Core\Checkout\Document\DocumentService as ShopwareDocumentService;
use Shopware\Core\Checkout\Document\GeneratedDocument;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Request;

/**
 * Decorates Shopware's DocumentService to add the order number for picklist documents. This is necessary because
 * creating picklist document via the flow builder (GenerateDocumentAction.php) misses information in the document
 * configuration. And we are unable to inject it otherwise.
 */
class PicklistCreationDocumentServiceDecorator extends ShopwareDocumentService
{
    private ShopwareDocumentService $decoratedInstance;
    private EntityManager $entityManager;

    /**
     * There is no parent constructor call which is acceptable since we do not call any parent functions.
     */
    public function __construct(
        ShopwareDocumentService $decoratedInstance,
        EntityManager $entityManager
    ) {
        $this->decoratedInstance = $decoratedInstance;
        $this->entityManager = $entityManager;
    }

    public function create(
        string $orderId,
        string $documentTypeName,
        string $fileType,
        DocumentConfiguration $config,
        Context $context,
        ?string $referencedDocumentId = null,
        bool $static = false
    ): DocumentIdStruct {
        if ($documentTypeName === PicklistDocumentType::TECHNICAL_NAME) {
            $config = $this->addPicklistDocumentConfiguration($orderId, $config, $context);
        }

        $documentIdStruct = $this->decoratedInstance->create(
            $orderId,
            $documentTypeName,
            $fileType,
            $config,
            $context,
            $referencedDocumentId,
            $static,
        );

        if ($documentTypeName === PicklistDocumentType::TECHNICAL_NAME) {
            $this->generatePicklistDocument($documentIdStruct->getId(), $context);
        }

        return $documentIdStruct;
    }

    public function preview(
        string $orderId,
        string $deepLinkCode,
        string $documentTypeName,
        string $fileType,
        DocumentConfiguration $config,
        Context $context
    ): GeneratedDocument {
        if ($documentTypeName === PicklistDocumentType::TECHNICAL_NAME) {
            $config = $this->addPicklistDocumentConfiguration($orderId, $config, $context);
        }

        return $this->decoratedInstance->preview(
            $orderId,
            $deepLinkCode,
            $documentTypeName,
            $fileType,
            $config,
            $context,
        );
    }

    public function getDocument(DocumentEntity $document, Context $context): GeneratedDocument
    {
        return $this->decoratedInstance->getDocument($document, $context);
    }

    public function uploadFileForDocument(
        string $documentId,
        Context $context,
        Request $uploadedFileRequest
    ): DocumentIdStruct {
        return $this->decoratedInstance->uploadFileForDocument($documentId, $context, $uploadedFileRequest);
    }

    private function addPicklistDocumentConfiguration(
        string $orderId,
        DocumentConfiguration $config,
        Context $context
    ): DocumentConfiguration {
        $order = $this->entityManager->getByPrimaryKey(OrderDefinition::class, $orderId, $context);

        // Note that the warehouse id was already added to the document configuration when creating picklist documents
        // via the administration or the flow builder. See administration overrides:
        // picklist/sw-order-document-settings-pickware-erp-picklist-modal.vue and
        // flow/sw-flow-generate-document-modal.vue
        $config->documentNumber = '';
        $config->orderNumber = $order->getOrderNumber();

        return $config;
    }

    // Documents are not actually rendered (content created) when the document entity is created.
    // This is done when the document is first downloaded. Because picklists rely on a snapshot of the 'current' stocks
    // in the order, we need the content of the picklist to be created when the document entity is created.
    // Therefore, we generate the picklist document manually here.
    private function generatePicklistDocument(
        string $documentId,
        Context $context
    ): void {
         $document = $this->entityManager->getByPrimaryKey(DocumentDefinition::class, $documentId, $context, [
             'documentType',
         ]);

        $this->decoratedInstance->getDocument($document, $context);
    }
}
