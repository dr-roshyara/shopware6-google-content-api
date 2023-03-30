<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShopwareExtensionsBundle\OrderConfiguration;

use Shopware\Core\Framework\Context;

class OrderConfigurationUpdatedEvent
{
    public const EVENT_NAME = 'pickware_shopware_extensions_bundle.order_configuration.order_configuration_updated';

    /**
     * @var string[]
     */
    private array $orderIds;
    private Context $context;

    public function __construct(array $orderIds, Context $context)
    {
        $this->orderIds = $orderIds;
        $this->context = $context;
    }

    /**
     * @return string[]
     */
    public function getOrderIds(): array
    {
        return $this->orderIds;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
