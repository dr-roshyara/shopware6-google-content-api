<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ApiErrorHandlingBundle;

use Doctrine\DBAL\Connection;
use Pickware\BundleInstaller\BundleInstaller;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Migration\MigrationSource;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Struct\Collection;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

class PickwareApiErrorHandlingBundle extends Bundle
{
    private static ?self $instance = null;
    private static bool $registered = false;
    private static bool $migrationsRegistered = false;

    public static function register(Collection $bundleCollection): void
    {
        if (self::$registered) {
            return;
        }

        $bundleCollection->add(self::getInstance());
        self::$registered = true;
    }

    public static function registerMigrations(MigrationSource $migrationSource): void
    {
        if (self::$migrationsRegistered) {
            return;
        }
        $migrationsPath = self::getInstance()->getMigrationPath();
        $migrationNamespace = self::getInstance()->getMigrationNamespace();
        $migrationSource->addDirectory($migrationsPath, $migrationNamespace);

        self::$migrationsRegistered = true;
    }

    public static function getInstance(): self
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function build(ContainerBuilder $containerBuilder): void
    {
        parent::build($containerBuilder);

        $loader = new XmlFileLoader($containerBuilder, new FileLocator(__DIR__));
        $loader->load('ControllerExceptionHandling/DependencyInjection/subscriber.xml');
    }

    public function shutdown(): void
    {
        parent::shutdown();

        // Shopware may reboot the kernel under certain circumstances (e.g. plugin un-/installation) within a single
        // request. After the kernel was rebooted, our bundles have to be registered again.
        // We reset the registration flag when the kernel is shut down. This will cause the bundles to be registered
        // again in the (re)boot process.
        self::$registered = false;
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        if ($uninstallContext->keepUserData()) {
            return;
        }

        $db = $this->container->get(Connection::class);

        // We need eight backslashes, as we need to match a single one and double the count for each of the following:
        // 1. The PHP parser
        // 2. The MySQL parser
        // 3. The MySQL pattern matcher (only when using LIKE)
        $db->executeStatement("DELETE FROM `migration` WHERE `class` LIKE 'Pickware\\\\\\\\ApiErrorHandlingBundle\\\\\\\\%'");
        BundleInstaller::createForContainerAndClass($this->container, self::class)->uninstall($uninstallContext);
    }
}
