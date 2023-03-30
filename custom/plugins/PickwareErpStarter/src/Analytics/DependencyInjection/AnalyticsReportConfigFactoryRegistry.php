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

use Pickware\PickwareErpStarter\Analytics\AnalyticsReportConfigFactory;
use Pickware\PickwareErpStarter\Registry\AbstractRegistry;

class AnalyticsReportConfigFactoryRegistry extends AbstractRegistry
{
    public function __construct(iterable $reportConfigFactories)
    {
        parent::__construct(
            $reportConfigFactories,
            [AnalyticsReportConfigFactory::class],
            'pickware_erp_starter.analytics_report_config_factory',
        );
    }

    /**
     * @param AnalyticsReportConfigFactory $instance
     */
    protected function getKey($instance): string
    {
        return $instance->getReportTechnicalName();
    }

    public function getAnalyticsReportConfigFactoryByReportTechnicalName(string $reportTechnicalName): AnalyticsReportConfigFactory
    {
        return $this->getRegisteredInstanceByKey($reportTechnicalName);
    }
}
