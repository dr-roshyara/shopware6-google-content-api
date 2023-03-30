<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Analytics\DependencyInjection;

use Pickware\PickwareErpStarter\Analytics\AnalyticsReportListItemDefinition;
use Pickware\PickwareErpStarter\Registry\AbstractRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;

class AnalyticsReportListItemDefinitionRegistry extends AbstractRegistry
{
    public function __construct(iterable $reportListItemDefinitions)
    {
        parent::__construct(
            $reportListItemDefinitions,
            [
                EntityDefinition::class,
                AnalyticsReportListItemDefinition::class,
            ],
            'pickware_erp_starter.analytics_report_list_item_definition',
        );
    }

    /**
     * @param AnalyticsReportListItemDefinition $instance
     */
    protected function getKey($instance): string
    {
        return $instance->getReportTechnicalName();
    }

    public function getAnalyticsReportListItemDefinitionByReportTechnicalName(string $reportTechnicalName): EntityDefinition
    {
        return $this->getRegisteredInstanceByKey($reportTechnicalName);
    }
}
