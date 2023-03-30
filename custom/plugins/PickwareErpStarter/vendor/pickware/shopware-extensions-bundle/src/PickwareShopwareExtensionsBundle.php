<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShopwareExtensionsBundle;

use Doctrine\DBAL\Connection;
use Pickware\BundleInstaller\BundleInstaller;
use Pickware\ShopwareExtensionsBundle\OrderConfiguration\OrderConfigurationIndexer;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Migration\MigrationSource;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Struct\Collection;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

class PickwareShopwareExtensionsBundle extends Bundle
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

    public function onAfterActivate(InstallContext $installContext): void
    {
        $entityIndexerRegistry = $this->container->get('pickware.pickware_shopware_extensions.entity_indexer_registry_public');
        $entityIndexerRegistry->sendIndexingMessage([
            OrderConfigurationIndexer::NAME,
        ]);
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
        $loader->load('Mail/DependencyInjection/service.xml');
        $loader->load('OrderDelivery/DependencyInjection/controller.xml');
        $loader->load('OrderDelivery/DependencyInjection/service.xml');
        $loader->load('OrderDocument/DependencyInjection/service.xml');
        $loader->load('OrderConfiguration/DependencyInjection/indexer.xml');
        $loader->load('OrderConfiguration/DependencyInjection/model.xml');
        $loader->load('OrderConfiguration/DependencyInjection/model-extension.xml');
        $loader->load('OrderConfiguration/DependencyInjection/service.xml');
        $loader->load('StateTransitioning/DependencyInjection/service.xml');
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
        $db->executeStatement('
            SET FOREIGN_KEY_CHECKS=0;

            -- Migration1657615865AddOrderConfigurationSchema.php
            DROP TABLE IF EXISTS `pickware_shopware_extensions_order_configuration`;

            SET FOREIGN_KEY_CHECKS=1;
        ');

        // We need eight backslashes, as we need to match a single one and double the count for each of the following:
        // 1. The PHP parser
        // 2. The MySQL parser
        // 3. The MySQL pattern matcher (only when using LIKE)
        $db->executeStatement("DELETE FROM `migration` WHERE `class` LIKE 'Pickware\\\\\\\\ShopwareExtensionsBundle\\\\\\\\%'");
        BundleInstaller::createForContainerAndClass($this->container, self::class)->uninstall($uninstallContext);
    }
}
