<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Config;

use DateTime;
use Pickware\PickwareErpStarter\PickwareErpStarter;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class GlobalPluginConfig
{
    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    public function __construct(SystemConfigService $systemConfigService)
    {
        $this->systemConfigService = $systemConfigService;
    }

    public function setReorderNotificationTime(DateTime $reorderNotificationTime): void
    {
        $this->setConfigValue('reorderNotificationTime', $reorderNotificationTime->format('H:i:sP'));
    }

    public function getReorderNotificationTime(): ?DateTime
    {
        $timeString = $this->getConfigValue('reorderNotificationTime');
        if (!$timeString) {
            return null;
        }

        return DateTime::createFromFormat('H:i:sP', $timeString);
    }

    public function getDefaultStockMovementComments(): array
    {
        $defaultStockMovementCommentsString = $this->getConfigValue('stockMovementComments');
        if (!$defaultStockMovementCommentsString) {
            return [];
        }

        $defaultStockMovementComments = explode("\n", $defaultStockMovementCommentsString) ?? [];
        $defaultStockMovementComments = array_map(fn($comment) => trim($comment), $defaultStockMovementComments);

        return array_values(array_filter($defaultStockMovementComments));
    }

    private function getConfigValue(string $configKey)
    {
        return $this->systemConfigService->get(PickwareErpStarter::GLOBAL_PLUGIN_CONFIG_DOMAIN . '.' . $configKey);
    }

    private function setConfigValue(string $configKey, $configValue): void
    {
        $this->systemConfigService->set(PickwareErpStarter::GLOBAL_PLUGIN_CONFIG_DOMAIN . '.' . $configKey, $configValue);
    }
}
