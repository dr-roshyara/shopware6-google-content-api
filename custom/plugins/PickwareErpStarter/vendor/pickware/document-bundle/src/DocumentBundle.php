<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DocumentBundle;

use Doctrine\DBAL\Connection;
use Pickware\BundleInstaller\BundleInstaller;
use Pickware\DalBundle\DalBundle;
use Pickware\DocumentBundle\Installation\DocumentFileSizeMigrator;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Migration\MigrationSource;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Struct\Collection;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

class DocumentBundle extends Bundle
{
    /**
     * @var class-string<Bundle>[]
     */
    private const ADDITIONAL_BUNDLES = [DalBundle::class];

    private static ?self $instance = null;
    private static bool $registered = false;
    private static bool $migrationsRegistered = false;

    public static function register(Collection $bundleCollection): void
    {
        if (self::$registered) {
            return;
        }

        $bundleCollection->add(self::getInstance());
        foreach (self::ADDITIONAL_BUNDLES as $bundle) {
            $bundle::register($bundleCollection);
        }

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
        $migrationSource->addDirectory(__DIR__ . '/MigrationOldNamespace', 'Pickware\\ShopwarePlugins\\DocumentBundle\\Migration');

        self::$migrationsRegistered = true;
    }

    public function install(InstallContext $installContext): void
    {
        BundleInstaller::createForContainerAndClass($this->container, self::class)
            ->install(self::ADDITIONAL_BUNDLES, $installContext);
    }

    public function onAfterActivate(InstallContext $activateContext): void
    {
        $documentFileSizeMigrator = $this->container->get(DocumentFileSizeMigrator::class);
        $documentFileSizeMigrator->migrateFileSize();

        BundleInstaller::createForContainerAndClass($this->container, self::class)
            ->onAfterActivate(self::ADDITIONAL_BUNDLES, $activateContext);
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
        $loader->load('DependencyInjection/controller.xml');
        $loader->load('DependencyInjection/decorator.xml');
        $loader->load('DependencyInjection/model.xml');
        $loader->load('DependencyInjection/service.xml');
        $loader->load('DependencyInjection/subscriber.xml');
        $loader->load('Installation/DependencyInjection/service.xml');
        $loader->load('Renderer/DependencyInjection/service.xml');
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
            DROP TABLE IF EXISTS `pickware_document`;
            DROP TABLE IF EXISTS `pickware_document_type`;
            SET FOREIGN_KEY_CHECKS=1;
        ');

        // We need eight backslashes, as we need to match a single one and double the count for each of the following:
        // 1. The PHP parser
        // 2. The MySQL parser
        // 3. The MySQL pattern matcher (only when using LIKE)
        $db->executeStatement("DELETE FROM `migration` WHERE `class` LIKE 'Pickware\\\\\\\\DocumentBundle\\\\\\\\%'");
        $db->executeStatement("DELETE FROM `migration` WHERE `class` LIKE 'Pickware\\\\\\\\ShopwarePlugins\\\\\\\\DocumentBundle\\\\\\\\%'");

        BundleInstaller::createForContainerAndClass($this->container, self::class)->uninstall($uninstallContext);
    }
}
