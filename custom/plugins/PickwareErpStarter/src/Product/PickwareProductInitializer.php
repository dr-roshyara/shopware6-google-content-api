<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Product;

use Doctrine\DBAL\Connection;
use Pickware\DalBundle\Sql\SqlUuid;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PickwareProductInitializer implements EventSubscriberInterface
{
    private Connection $db;

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
            // $writeResult->getExistence() can be null, but we have no idea why and also not what this means.
            $existence = $writeResult->getExistence();
            if (($existence === null && $writeResult->getOperation() === EntityWriteResult::OPERATION_INSERT)
                || ($existence !== null && !$existence->exists())
            ) {
                $productIds[] = $writeResult->getPrimaryKey();
            }
        }

        $this->ensurePickwareProductsExist($productIds);
    }

    public function ensurePickwareProductsExist(array $productIds): void
    {
        if (count($productIds) === 0) {
            return;
        }

        $this->db->executeStatement(
            'INSERT INTO `pickware_erp_pickware_product` (
                id,
                product_id,
                product_version_id,
                incoming_stock,
                created_at
            ) SELECT
                ' . SqlUuid::UUID_V4_GENERATION . ',
                product.id,
                product.version_id,
                0,
                NOW(3)
            FROM `product`
            WHERE `product`.`id` IN (:ids) AND `product`.`version_id` = :liveVersionId
            ON DUPLICATE KEY UPDATE `pickware_erp_pickware_product`.`id` = `pickware_erp_pickware_product`.`id`',
            [
                'ids' => array_map('hex2bin', $productIds),
                'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
            ],
            [
                'ids' => Connection::PARAM_STR_ARRAY,
            ],
        );
    }
}
