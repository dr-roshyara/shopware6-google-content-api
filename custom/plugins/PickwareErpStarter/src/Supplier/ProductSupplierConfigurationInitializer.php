<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Supplier;

use Doctrine\DBAL\Connection;
use Pickware\DalBundle\Sql\SqlUuid;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductSupplierConfigurationInitializer implements EventSubscriberInterface
{
    /**
     * @var Connection
     */
    private $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductEvents::PRODUCT_WRITTEN_EVENT => 'productWritten',
        ];
    }

    public function productWritten(EntityWrittenEvent $entityWrittenEvent): void
    {
        if ($entityWrittenEvent->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $productIds = [];
        foreach ($entityWrittenEvent->getWriteResults() as $writeResult) {
            if ($writeResult->getOperation() === EntityWriteResult::OPERATION_INSERT) {
                $productIds[] = $writeResult->getPrimaryKey();
            }
        }

        $this->ensureProductSupplierConfigurationExistsForProducts($productIds);
    }

    public function ensureProductSupplierConfigurationExistsForProducts(array $productIds): void
    {
        if (count($productIds) === 0) {
            return;
        }

        $this->db->executeStatement(
            'INSERT INTO `pickware_erp_product_supplier_configuration` (
                `id`,
                `product_id`,
                `product_version_id`,
                `created_at`
            ) SELECT
                ' . SqlUuid::UUID_V4_GENERATION . ',
                `id`,
                `version_id`,
                NOW(3)
            FROM `product`
            WHERE `id` IN (:productIds) AND `version_id` = :liveVersionId
            ON DUPLICATE KEY UPDATE `pickware_erp_product_supplier_configuration`.`id` = `pickware_erp_product_supplier_configuration`.`id`',
            [
                'productIds' => array_map('hex2bin', $productIds),
                'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
            ],
            [
                'productIds' => Connection::PARAM_STR_ARRAY,
            ],
        );
    }
}
