<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\OrderShipping;

use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\ShopwareExtensionsBundle\Event\PickwareValidationViolation;
use Shopware\Core\Framework\Context;
use Symfony\Contracts\EventDispatcher\Event;

class PreOrderShippingValidationEvent extends Event
{
    public const EVENT_NAME = 'pickware_erp.order_shipping.pre_order_shipping_validation';

    /**
     * @var string[]
     */
    private array $orderIds;

    /**
     * @deprecated
     */
    private ?string $warehouseId;

    private Context $context;
    private JsonApiErrors $errors;

    /**
     * @param string[] $orderIds
     * @deprecated Parameter $warehouseId will be removed with next major release
     */
    public function __construct(Context $context, array $orderIds, ?string $warehouseId = null)
    {
        $this->context = $context;
        $this->orderIds = $orderIds;
        $this->warehouseId = $warehouseId;
        $this->errors = new JsonApiErrors();
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    /**
     * @return string[]
     */
    public function getOrderIds(): array
    {
        return $this->orderIds;
    }

    /**
     * @deprecated will be removed with next major release
     */
    public function getWarehouseId(): string
    {
        return $this->warehouseId;
    }

    /**
     * @deprecated tax:next-major Use addError instead
     */
    public function addViolation(PickwareValidationViolation $violation): void
    {
        $this->addError($violation->serializeToJsonApiError());
    }

    public function addError(JsonApiError $error): void
    {
        $this->errors->addError($error);
    }

    public function getErrors(): JsonApiErrors
    {
        return $this->errors;
    }
}
