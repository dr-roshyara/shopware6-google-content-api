<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\SupplierOrder;

use Doctrine\DBAL\Connection;
use Pickware\PickwareErpStarter\SupplierOrder\Model\SupplierOrderDefinition;
use Pickware\PickwareErpStarter\SupplierOrder\Model\SupplierOrderLineItemDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class IncomingStockUpdater implements EventSubscriberInterface
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SupplierOrderLineItemDefinition::ENTITY_WRITTEN_EVENT => 'supplierOrderLineItemWritten',
            SupplierOrderLineItemDefinition::ENTITY_DELETED_EVENT => 'supplierOrderLineItemDeleted',
            SupplierOrderDefinition::ENTITY_WRITTEN_EVENT => 'supplierOrderWritten',
        ];
    }

    public function supplierOrderLineItemDeleted(EntityDeletedEvent $entityDeletedEvent): void
    {
        if ($entityDeletedEvent->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $productIds = [];
        foreach ($entityDeletedEvent->getWriteResults() as $writeResult) {
            $changeSet = $writeResult->getChangeSet();
            $productIds[] = bin2hex($changeSet->getBefore('product_id'));
        }

        if (count($productIds) === 0) {
            return;
        }

        $this->recalculateIncomingStock($productIds);
    }

    public function supplierOrderLineItemWritten(EntityWrittenEvent $entityWrittenEvent): void
    {
        if ($entityWrittenEvent->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $supplierOrderLineItemIds = [];
        foreach ($entityWrittenEvent->getWriteResults() as $writeResult) {
            $payload = $writeResult->getPayload();
            $supplierOrderLineItemIds[] = $payload['id'];
        }

        if (count($supplierOrderLineItemIds) === 0) {
            return;
        }

        $supplierOrderLineItemProductIds = $this->db->fetchAllAssociative(
            'SELECT DISTINCT LOWER(HEX(product_id)) AS productId
            FROM pickware_erp_supplier_order_line_item
            WHERE id IN (:supplierOrderLineItemIds)',
            ['supplierOrderLineItemIds' => array_map('hex2bin', $supplierOrderLineItemIds)],
            ['supplierOrderLineItemIds' => Connection::PARAM_STR_ARRAY],
        );
        $productIds = array_filter(array_column($supplierOrderLineItemProductIds, 'productId'));

        $this->recalculateIncomingStock($productIds);
    }

    public function supplierOrderWritten(EntityWrittenEvent $entityWrittenEvent): void
    {
        if ($entityWrittenEvent->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $supplierOrderIds = [];
        foreach ($entityWrittenEvent->getWriteResults() as $writeResult) {
            $payload = $writeResult->getPayload();
            $supplierOrderIds[] = $payload['id'];
        }

        if (count($supplierOrderIds) === 0) {
            return;
        }

        $supplierOrderProductIds = $this->db->fetchAllAssociative(
            'SELECT DISTINCT LOWER(HEX(product_id)) AS productId
            FROM pickware_erp_supplier_order_line_item
            JOIN pickware_erp_supplier_order ON pickware_erp_supplier_order_line_item.supplier_order_id = pickware_erp_supplier_order.id
            WHERE pickware_erp_supplier_order.id IN (:supplierOrderIds)',
            ['supplierOrderIds' => array_map('hex2bin', $supplierOrderIds)],
            ['supplierOrderIds' => Connection::PARAM_STR_ARRAY],
        );
        $productIds = array_filter(array_column($supplierOrderProductIds, 'productId'));

        $this->recalculateIncomingStock($productIds);
    }

    public function recalculateIncomingStock(array $productIds): void
    {
        if (count($productIds) === 0) {
            return;
        }

        $this->db->executeStatement(
            'UPDATE `pickware_erp_pickware_product`
            LEFT JOIN `product`
                ON `product`.`id` = `pickware_erp_pickware_product`.`product_id`
                AND `product`.`version_id` = `pickware_erp_pickware_product`.`product_version_id`
            LEFT JOIN (
                SELECT
                    SUM(`supplierOrderLineItem`.`quantity`) AS `quantity`,
                    `supplierOrderLineItem`.`product_id`,
                    `supplierOrderLineItem`.`product_version_id`
                FROM `pickware_erp_supplier_order_line_item` AS `supplierOrderLineItem`
                INNER JOIN `pickware_erp_supplier_order` AS `supplierOrder`
                    ON `supplierOrder`.`id` = `supplierOrderLineItem`.`supplier_order_id`
                LEFT JOIN `state_machine_state` AS `orderState`
                    ON `orderState`.`id` = `supplierOrder`.`state_id`
                WHERE `orderState`.`technical_name` NOT IN (:completedSupplierOrderStateTechnicalNames)
                GROUP BY `supplierOrderLineItem`.`product_id`
            ) AS `openSupplierOrderLineItems`
                ON `openSupplierOrderLineItems`.`product_id` = `product`.`id`
                AND `openSupplierOrderLineItems`.`product_version_id` = `product`.`version_id`
            SET `pickware_erp_pickware_product`.`incoming_stock` = IFNULL(`openSupplierOrderLineItems`.`quantity`, 0)
            WHERE `product`.`version_id` = :liveVersionId
            AND `product`.`id` IN (:productIds)',
            [
                'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
                'productIds' => array_map('hex2bin', $productIds),
                'completedSupplierOrderStateTechnicalNames' => [
                    SupplierOrderStateMachine::STATE_DELIVERED,
                    SupplierOrderStateMachine::STATE_CANCELLED,
                    SupplierOrderStateMachine::STATE_COMPLETED,
                ],
            ],
            [
                'productIds' => Connection::PARAM_STR_ARRAY,
                'completedSupplierOrderStateTechnicalNames' => Connection::PARAM_STR_ARRAY,
            ],
        );
    }
}
