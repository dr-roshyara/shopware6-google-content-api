<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\StockApi\Subscriber;

use Pickware\PickwareErpStarter\Config\Config;
use Pickware\PickwareErpStarter\StockApi\StockLocationReference;
use Pickware\PickwareErpStarter\StockApi\TotalStockWriter;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Listens to changes made to the field "stock" of a product and initiates the corresponding absolute stock change.
 */
class ProductStockSubscriber implements EventSubscriberInterface
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var TotalStockWriter
     */
    private $totalStockWriter;

    public function __construct(Config $config, TotalStockWriter $totalStockWriter)
    {
        $this->config = $config;
        $this->totalStockWriter = $totalStockWriter;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductEvents::PRODUCT_WRITTEN_EVENT => 'afterProductWritten',
            // ProductEvents::PRODUCT_WRITTEN_EVENT is triggered very late in a write operation an therefore is not in
            // the transaction anymore. If the stock of a product is set to a negative value, the TotalStockWriter would
            // throw an exception, but since this is not happening in the transaction anymore, the changes were already
            // written to the database and won't be reverted. Instead negative product stock is avoided by adding a
            // pre-write-validation.
            PreWriteValidationEvent::class => 'preWriteValidation',
        ];
    }

    public function preWriteValidation(PreWriteValidationEvent $event): void
    {
        // Filter out all WriteCommand for products that will set the stock to a negative value
        $invalidWriteCommands = array_filter($event->getCommands(), function (WriteCommand $writeCommand) {
            if ($writeCommand->getDefinition()->getClass() !== ProductDefinition::class) {
                return false;
            }
            $payload = $writeCommand->getPayload();

            return isset($payload['stock']) && $payload['stock'] < 0;
        });

        if (count($invalidWriteCommands) === 0) {
            return;
        }

        // Add violations for that WriteCommands to the Event
        $violations = new ConstraintViolationList();
        foreach ($invalidWriteCommands as $invalidWriteCommand) {
            $message = 'The value for property "stock" is not allowed to be lower than 0.';
            $violation = new ConstraintViolation(
                $message, // $message
                $message, // $messageTemplate,
                [], // $parameters,
                null, // $root
                $invalidWriteCommand->getPath() . '/stock', // $propertyPath
                $invalidWriteCommand->getPayload()['stock'], // $invalidValue
            );
            $violations->add($violation);
        }
        $event->getExceptions()->add(new WriteConstraintViolationException($violations));
    }

    public function afterProductWritten(EntityWrittenEvent $event): void
    {
        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }
        if (!$this->config->isStockInitialized()) {
            return;
        }

        $writeResults = $event->getWriteResults();
        $newProductStocks = [];
        $existingProductStocks = [];
        foreach ($writeResults as $writeResult) {
            $payload = $writeResult->getPayload();
            // Filter out instances of EntityWriteResult with empty payload. Somehow they are introduced by a bug in
            // the Shopware DAL.
            if (count($payload) === 0) {
                continue;
            }
            if ($payload['versionId'] !== Defaults::LIVE_VERSION) {
                continue;
            }
            if (!array_key_exists('stock', $payload)) {
                continue;
            }

            $isNewProduct = $writeResult->getExistence() && !$writeResult->getExistence()->exists();
            $productId = $payload['id'];
            if ($isNewProduct) {
                $newProductStocks[$productId] = $payload['stock'];
            } else {
                $existingProductStocks[$productId] = $payload['stock'];
            }
        }

        if (count($existingProductStocks) > 0) {
            $this->totalStockWriter->setTotalStockForProducts(
                $existingProductStocks,
                StockLocationReference::productTotalStockChange(),
                $event->getContext(),
            );
        }
        if (count($newProductStocks) > 0) {
            $this->totalStockWriter->setTotalStockForProducts(
                $newProductStocks,
                StockLocationReference::initialization(),
                $event->getContext(),
            );
        }
    }
}
