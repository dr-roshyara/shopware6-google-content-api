<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Picklist\Renderer;

use DateTime;
use Doctrine\DBAL\Connection;
use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\Picklist\PicklistCustomProductGenerator;
use Pickware\PickwareErpStarter\Picklist\PicklistDocumentType;
use Pickware\PickwareErpStarter\Picklist\PicklistGenerator;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseDefinition;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigDefinition;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigEntity;
use Shopware\Core\Checkout\Document\DocumentConfiguration;
use Shopware\Core\Checkout\Document\DocumentConfigurationFactory;
use Shopware\Core\Checkout\Document\Renderer\AbstractDocumentRenderer;
use Shopware\Core\Checkout\Document\Renderer\DocumentRendererConfig;
use Shopware\Core\Checkout\Document\Renderer\OrderDocumentCriteriaFactory;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Checkout\Document\Renderer\RendererResult;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\Document\Twig\DocumentTemplateRenderer;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;

class PicklistDocumentRenderer extends AbstractDocumentRendererCompatibilityWrapper
{
    public const DEFAULT_TEMPLATE = '@PickwareErpStarter/documents/picklist.html.twig';

    private string $rootDir;
    private EntityManager $entityManager;
    private DocumentTemplateRenderer $documentTemplateRenderer;
    private PicklistDocumentContentGenerator $contentGenerator;
    private PicklistGenerator $picklistGenerator;
    private PicklistCustomProductGenerator $picklistCustomProductGenerator;
    private NumberRangeValueGeneratorInterface $numberRangeValueGenerator;
    private Connection $connection;
    private array $configs = [];

    public function __construct(
        string $rootDir,
        EntityManager $entityManager,
        PicklistGenerator $picklistGenerator,
        PicklistCustomProductGenerator $picklistCustomProductGenerator,
        DocumentTemplateRenderer $documentTemplateRenderer,
        PicklistDocumentContentGenerator $contentGenerator,
        NumberRangeValueGeneratorInterface $numberRangeValueGenerator,
        Connection $connection
    ) {
        $this->rootDir = $rootDir;
        $this->entityManager = $entityManager;
        $this->picklistGenerator = $picklistGenerator;
        $this->picklistCustomProductGenerator = $picklistCustomProductGenerator;
        $this->documentTemplateRenderer = $documentTemplateRenderer;
        $this->contentGenerator = $contentGenerator;
        $this->numberRangeValueGenerator = $numberRangeValueGenerator;
        $this->connection = $connection;
    }

    public function render(array $operations, Context $context, DocumentRendererConfig $rendererConfig): RendererResult
    {
        $result = new RendererResult();
        $orderIds = array_map(fn (DocumentGenerateOperation $operation) => $operation->getOrderId(), $operations);
        if (empty($orderIds)) {
            return $result;
        }

        $warehousesById = [];
        $orderIdsByLanguageId = $this->getOrdersLanguageId(array_values($orderIds), $context->getVersionId(), $this->connection);
        foreach ($orderIdsByLanguageId as ['language_id' => $languageId, 'ids' => $orderIds]) {
            // Assigns the corresponding of the orderId to the languageIdChain. If there is no languageId given it sets
            // default languageId of the context. It filters the array to unique values, so that there are no duplicates
            // of languageIds. Used for rendering process by shopware
            $context = $context->assign([
                'languageIdChain' => array_unique(array_filter([$languageId, $context->getLanguageId()])),
            ]);
            $criteria = OrderDocumentCriteriaFactory::create(explode(',', $orderIds), $rendererConfig->deepLinkCode);
            $criteria->addSorting(new FieldSorting('orderNumber'));
            /** @var OrderCollection $orders */
            $orders = $this->entityManager->findBy(
                OrderDefinition::class,
                $criteria,
                $context,
            );
            foreach ($orders as $order) {
                $operation = $operations[$order->getId()];
                $warehouseId = $operation->getConfig()['warehouseId'];
                $config = clone $this->load(PicklistDocumentType::TECHNICAL_NAME, $order->getSalesChannelId(), $context);
                $number = $config->getDocumentNumber() ?: $this->getNextPicklistDocumentNumber($context, $order, $operation);
                $date = $operation->getConfig()['documentDate'] ?: (new DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

                if (!array_key_exists($warehouseId, $warehousesById)) {
                    $warehousesById[$warehouseId] = $this->entityManager->getByPrimaryKey(
                        WarehouseDefinition::class,
                        $warehouseId,
                        $context,
                    );
                }
                $warehouse = $warehousesById[$warehouseId];

                $config->merge([
                    'documentNumber' => $number,
                    'orderNumber' => $order->getOrderNumber(),
                    'warehouseId' => $warehouseId,
                    'documentDate' => $date,
                    'documentComment' => $operation->getConfig()['documentComment'],
                ]);

                $html = $this->documentTemplateRenderer->render(
                    self::DEFAULT_TEMPLATE,
                    [
                        'order' => $order,
                        'warehouse' => $warehouse,
                        'pickingRouteNodes' => $this->contentGenerator->createDocumentPickingRouteNodes(
                            $this->picklistGenerator->generatePicklist($warehouseId, $order->getId(), $context),
                            $order->getLineItems()->getIds(),
                            $context,
                        ),
                        'customProducts' => $this->picklistCustomProductGenerator->generatorCustomProductDefinitions(
                            $order->getLineItems(),
                        ),
                        'config' => $config,
                        'rootDir' => $this->rootDir,
                        'context' => $context,
                    ],
                    $context,
                    $order->getSalesChannelId(),
                    $order->getLanguageId(),
                    $order->getLanguage()->getLocale()->getCode(),
                );
                $renderedDocument = new RenderedDocument(
                    $html,
                    $number,
                    $config->buildName(),
                    $operation->getFileType(),
                    $config->jsonSerialize(),
                );

                $result->addSuccess($order->getId(), $renderedDocument);
            }
        }

        return $result;
    }

    private function getNextPicklistDocumentNumber(Context $context, OrderEntity $order, DocumentGenerateOperation $operation): string
    {
        return $this->numberRangeValueGenerator->getValue(
            'document_' . PicklistDocumentType::TECHNICAL_NAME,
            $context,
            $order->getSalesChannelId(),
            $operation->isPreview(),
        );
    }

    public function getDecorated(): AbstractDocumentRenderer
    {
        throw new DecorationPatternException(self::class);
    }

    public function supports(): string
    {
        return PicklistDocumentType::TECHNICAL_NAME;
    }

    public function load(string $documentType, string $salesChannelId, Context $context): DocumentConfiguration
    {
        if (!empty($this->configs[$documentType][$salesChannelId])) {
            return $this->configs[$documentType][$salesChannelId];
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('documentType.technicalName', $documentType));
        $criteria->addAssociation('logo');
        $criteria->getAssociation('salesChannels')->addFilter(new EqualsFilter('salesChannelId', $salesChannelId));
        $criteria->addFilter(new EqualsFilter('global', true));
        /** @var DocumentBaseConfigEntity $documentConfigs */
        $documentConfigs = $this->entityManager->findFirstBy(DocumentBaseConfigDefinition::class, new FieldSorting('createdAt', FieldSorting::ASCENDING), $context, $criteria);

        $salesChannelConfig = $documentConfigs->getSalesChannels()->first();
        $config = DocumentConfigurationFactory::createConfiguration([], $documentConfigs, $salesChannelConfig);
        $this->configs[$documentType] = $this->configs[$documentType] ?? [];
        $this->configs[$documentType][$salesChannelId] = $config;

        return $config;
    }

    /**
     * @deprecated Will to be removed with shopware v6.4.15.0 min compatibility
     *
     * This function is a copy of shopwares getOrdersLanguageId of the AbstractDocumentRenderer which only exists since
     * v6.4.15.0 to make it compatible with v6.4.14.0 which supports the batch creation but does not have this function.
     * Further references: https://github.com/pickware/shopware-plugins/issues/3393
     */
    protected function getOrdersLanguageId(array $ids, string $versionId, Connection $connection): array
    {
        return $connection->fetchAllAssociative(
            '
            SELECT LOWER(HEX(language_id)) as language_id, GROUP_CONCAT(DISTINCT LOWER(HEX(id))) as ids
            FROM `order`
            WHERE `id` IN (:ids)
            AND `version_id` = :versionId
            AND `language_id` IS NOT NULL
            GROUP BY `language_id`',
            [
                'ids' => Uuid::fromHexToBytesList($ids),
                'versionId' => Uuid::fromHexToBytes($versionId),
            ],
            [
                'ids' => Connection::PARAM_STR_ARRAY,
            ],
        );
    }
}
