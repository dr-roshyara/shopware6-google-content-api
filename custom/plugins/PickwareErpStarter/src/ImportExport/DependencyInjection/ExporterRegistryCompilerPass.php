<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\ImportExport\DependencyInjection;

use LogicException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class ExporterRegistryCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(ExporterRegistry::class)) {
            throw new LogicException(sprintf(
                'The container is missing the service definition "%s". Did you forget to define this service?',
                ExporterRegistry::class,
            ));
        }

        $registry = $container->findDefinition(ExporterRegistry::class);
        $carriers = $container->findTaggedServiceIds('pickware_erp_starter.exporter');
        foreach ($carriers as $serviceName => $tagAttributes) {
            $technicalName = $tagAttributes[0]['profileTechnicalName'];
            $registry->addMethodCall('addExporter', [$technicalName, new Reference($serviceName)]);
        }
    }
}
