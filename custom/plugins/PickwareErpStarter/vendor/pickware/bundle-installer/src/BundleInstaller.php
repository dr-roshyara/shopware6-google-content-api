<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\BundleInstaller;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Symfony\Component\DependencyInjection\ContainerInterface;

class BundleInstaller
{
    private const UNSUPPORTED_PLUGIN_VERSIONS = [
        'PickwareErpStarter' => '2.27.3',
        'PickwarePos' => '1.4.0',
        'PickwareDhl' => '1.15.1',
        'PickwareGls' => '1.1.5',
    ];

    private ContainerInterface $container;
    private Connection $connection;
    private KernelPluginLoader $pluginLoader;
    private string $usingClassName;

    public function __construct(ContainerInterface $container, Connection $connection, KernelPluginLoader $pluginLoader, string $usingClassName)
    {
        $this->container = $container;
        $this->connection = $connection;
        $this->pluginLoader = $pluginLoader;
        $this->usingClassName = $usingClassName;
    }

    public static function createForContainerAndClass(ContainerInterface $container, string $usingClassName): self
    {
        return new self(
            $container,
            $container->get(Connection::class),
            $container->get(KernelPluginLoader::class),
            $usingClassName,
        );
    }

    public function install(array $bundleClassNames, InstallContext $installContext): void
    {
        $this->ensureBundleUsageTableExists();

        foreach ($bundleClassNames as $bundleClassName) {
            $this->connection->executeStatement(
                'INSERT IGNORE INTO `pickware_bundle_installer_bundle_usage` (
                    `bundle_class_name`,
                    `used_by_class_name`,
                    `created_at`
                ) VALUES (
                    :bundleClassName,
                    :usingClassName,
                    NOW()
                )',
                [
                    'bundleClassName' => $bundleClassName,
                    'usingClassName' => $this->usingClassName,
                ],
            );

            $bundle = $bundleClassName::getInstance();
            $bundle->setContainer($this->container);
            if (method_exists($bundle, 'install')) {
                $bundle->install($installContext);
            }
        }
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        if ($uninstallContext->keepUserData()) {
            return;
        }

        $this->ensureBundleUsageTableExists();

        $bundlesUsedByUsingClass = array_map(
            fn ($row) => $row['bundle_class_name'],
            $this->connection->fetchAllAssociative(
                'SELECT `bundle_class_name`
                    FROM `pickware_bundle_installer_bundle_usage`
                    WHERE `used_by_class_name` = :usingClassName',
                ['usingClassName' => $this->usingClassName],
            ),
        );

        $this->connection->executeStatement(
            'DELETE FROM `pickware_bundle_installer_bundle_usage`
                    WHERE `used_by_class_name` = :usingClassName AND `bundle_class_name` IN (:bundleClassNames)',
            [
                'bundleClassNames' => $bundlesUsedByUsingClass,
                'usingClassName' => $this->usingClassName,
            ],
            ['bundleClassNames' => Connection::PARAM_STR_ARRAY],
        );

        $otherBundlesUsingDependencies = array_map(
            fn ($row) => $row['bundle_class_name'],
            $this->connection->fetchAllAssociative(
                'SELECT `bundle_class_name`
                    FROM `pickware_bundle_installer_bundle_usage`
                    WHERE `bundle_class_name` IN (:bundleClassNames) GROUP BY `bundle_class_name`',
                ['bundleClassNames' => $bundlesUsedByUsingClass],
                ['bundleClassNames' => Connection::PARAM_STR_ARRAY],
            ),
        );

        if ($this->areAllPluginsAboveRequiredVersion()) {
            foreach ($bundlesUsedByUsingClass as $bundleClassName) {
                if (in_array($bundleClassName, $otherBundlesUsingDependencies ?: [])) {
                    continue;
                }

                $bundle = $bundleClassName::getInstance();
                $bundle->setContainer($this->container);
                if (method_exists($bundle, 'uninstall')) {
                    $bundle->uninstall($uninstallContext);
                }
            }
        }

        $this->removeDependencyMappingTableIfExistsAndEmpty();
    }

    private function ensureBundleUsageTableExists(): void
    {
        $this->connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `pickware_bundle_installer_bundle_usage` (
                `bundle_class_name` VARCHAR(255),
                `used_by_class_name` VARCHAR(255),
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                CONSTRAINT `pickware_bundle_installer_bundle_usage.pk.bundle_usages`
                    PRIMARY KEY (`bundle_class_name`, `used_by_class_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        );
    }

    private function removeDependencyMappingTableIfExistsAndEmpty(): void
    {
        $result = $this->connection->fetchAssociative(
            'SELECT
                IF(COUNT(*) > 0, true, false) as tableExists
            FROM
                information_schema.TABLES
            WHERE
                TABLE_SCHEMA = :dbName AND
                TABLE_TYPE = "BASE TABLE" AND
                TABLE_NAME = "pickware_bundle_installer_bundle_usage"',
            ['dbName' => $this->connection->getDatabase()],
        );
        if (!$result || !$result['tableExists']) {
            return;
        }

        $dependencyMapping = $this->connection->fetchAssociative(
            'SELECT * FROM `pickware_bundle_installer_bundle_usage`',
        );
        if ($dependencyMapping) {
            return;
        }

        $this->connection->executeStatement(
            'DROP TABLE IF EXISTS `pickware_bundle_installer_bundle_usage`',
        );
    }

    public function onAfterActivate(array $bundleClassNames, InstallContext $installContext): void
    {
        foreach ($bundleClassNames as $bundleClass) {
            $bundle = $bundleClass::getInstance();
            $bundle->setContainer($this->container);
            if (method_exists($bundle, 'onAfterActivate')) {
                $bundle->onAfterActivate($installContext);
            }
        }
    }

    /**
     * The bundle installer will check the versions of the plugins registered in Shopwares `plugin` table. If any plugin
     * version in that table indicates that the plugin does not yet use bundle installer functionality, meaning it does
     * not declare its dependencies (as seen above), no bundle is uninstalled, regardless of if that plugin actually
     * uses the relevant bundles or not.
     */
    private function areAllPluginsAboveRequiredVersion(): bool
    {
        $plugins = $this->pluginLoader->getPluginInfos();
        foreach ($plugins as $plugin) {
            if (array_key_exists($plugin['name'], self::UNSUPPORTED_PLUGIN_VERSIONS)
                && version_compare($plugin['version'], self::UNSUPPORTED_PLUGIN_VERSIONS[$plugin['name']], '<=')
            ) {
                return false;
            }
        }

        return true;
    }
}
