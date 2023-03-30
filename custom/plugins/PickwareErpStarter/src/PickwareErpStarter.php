<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter;

use Doctrine\DBAL\Connection;
use Pickware\ApiErrorHandlingBundle\PickwareApiErrorHandlingBundle;
use Pickware\BundleInstaller\BundleInstaller;
use Pickware\ConfigBundle\PickwareConfigBundle;
use Pickware\DalBundle\DalBundle;
use Pickware\DebugBundle\ShopwarePluginsDebugBundle;
use Pickware\DocumentBundle\DocumentBundle;
use Pickware\MoneyBundle\MoneyBundle;
use Pickware\PickwareErpStarter\ImportExport\DependencyInjection\ExporterRegistryCompilerPass;
use Pickware\PickwareErpStarter\ImportExport\DependencyInjection\ImporterRegistryCompilerPass;
use Pickware\PickwareErpStarter\Installation\PickwareErpInstaller;
use Pickware\PickwareErpStarter\Stock\Indexer\StockIndexer;
use Pickware\PickwareErpStarter\Supplier\ProductSupplierConfigurationIndexer;
use Pickware\PickwareErpStarter\SupplierOrder\Indexer\IncomingStockIndexer;
use Pickware\ShopwareExtensionsBundle\PickwareShopwareExtensionsBundle;
use Pickware\ShopwarePlugins\ShopwareIntegrationTestPlugin\ShopwareIntegrationTestPlugin;
use Pickware\ValidationBundle\PickwareValidationBundle;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Shopware\Core\Framework\Migration\MigrationRuntime;
use Shopware\Core\Framework\Migration\MigrationSource;
use Shopware\Core\Framework\Parameter\AdditionalBundleParameters;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\Framework\Struct\Collection;
use SwagMigrationAssistant\Migration\Writer\WriterInterface;
use SwagMigrationAssistant\SwagMigrationAssistant;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

if (file_exists(__DIR__ . '/../vendor/pickware/dependency-loader/src/DependencyLoader.php')) {
    require_once __DIR__ . '/../vendor/pickware/dependency-loader/src/DependencyLoader.php';
}

class PickwareErpStarter extends Plugin
{
    public const GLOBAL_PLUGIN_CONFIG_DOMAIN = 'PickwareErpStarter.global-plugin-config';
    public const DOCUMENT_TYPE_TECHNICAL_NAME_IMPORT = 'pickware_erp_import';
    public const DOCUMENT_TYPE_TECHNICAL_NAME_EXPORT = 'pickware_erp_export';

    public const DOCUMENT_TYPE_TECHNICAL_NAME_DESCRIPTION_MAPPING = [
        self::DOCUMENT_TYPE_TECHNICAL_NAME_IMPORT => 'Imported file',
        self::DOCUMENT_TYPE_TECHNICAL_NAME_EXPORT => 'Exported file',
    ];

    /**
     * @var class-string<Bundle>[]
     */
    private const ADDITIONAL_BUNDLES = [
        DalBundle::class,
        DocumentBundle::class,
        MoneyBundle::class,
        PickwareApiErrorHandlingBundle::class,
        PickwareShopwareExtensionsBundle::class,
        PickwareValidationBundle::class,
        ShopwarePluginsDebugBundle::class,
        PickwareConfigBundle::class,
    ];

    public function getAdditionalBundles(AdditionalBundleParameters $parameters): array
    {
        // Ensure the bundle classes can be loaded via auto-loading.
        if (isset($GLOBALS['PICKWARE_DEPENDENCY_LOADER'])) {
            $kernelParameters = $parameters->getKernelParameters();
            $GLOBALS['PICKWARE_DEPENDENCY_LOADER']->ensureLatestDependenciesOfPluginsLoaded(
                $kernelParameters['kernel.plugin_infos'],
                $kernelParameters['kernel.project_dir'],
            );
        }

        // For some reason Collection is abstract
        // phpcs:ignore Squiz.WhiteSpace.ScopeClosingBrace.ContentBefore -- PHP CS does not understand the PHP 7 syntax
        $bundleCollection = new class() extends Collection {};
        foreach (self::ADDITIONAL_BUNDLES as $bundle) {
            $bundle::register($bundleCollection);
        }

        return $bundleCollection->getElements();
    }

    public static function getDistPackages(): array
    {
        return include __DIR__ . '/../Packages.php';
    }

    public function build(ContainerBuilder $containerBuilder): void
    {
        parent::build($containerBuilder);

        $loader = new XmlFileLoader($containerBuilder, new FileLocator(__DIR__));
        $loader->load('Address/DependencyInjection/model.xml');
        $loader->load('Address/DependencyInjection/model-extension.xml');
        $loader->load('Analytics/DependencyInjection/controller.xml');
        $loader->load('Analytics/DependencyInjection/model.xml');
        $loader->load('Analytics/DependencyInjection/model-extension.xml');
        $loader->load('Analytics/DependencyInjection/service.xml');
        $loader->load('BarcodeLabel/DependencyInjection/controller.xml');
        $loader->load('BarcodeLabel/DependencyInjection/service.xml');
        $loader->load('Cache/DependencyInjection/service.xml');
        $loader->load('Config/DependencyInjection/controller.xml');
        $loader->load('Config/DependencyInjection/service.xml');
        $loader->load('DemandPlanning/DependencyInjection/analytics.xml');
        $loader->load('DemandPlanning/DependencyInjection/controller.xml');
        $loader->load('DemandPlanning/DependencyInjection/service.xml');
        $loader->load('DemodataGeneration/DependencyInjection/command.xml');
        $loader->load('DemodataGeneration/DependencyInjection/demodata-generator.xml');
        $loader->load('ImportExport/DependencyInjection/controller.xml');
        $loader->load('ImportExport/DependencyInjection/model.xml');
        $loader->load('ImportExport/DependencyInjection/model-extension.xml');
        $loader->load('ImportExport/DependencyInjection/model-subscriber.xml');
        $loader->load('ImportExport/DependencyInjection/service.xml');
        $loader->load('ImportExport/ReadWrite/DependencyInjection/service.xml');
        $loader->load('Installation/DependencyInjection/service.xml');
        $loader->load('InvoiceCorrection/DependencyInjection/service.xml');
        $loader->load('InvoiceStack/DependencyInjection/service.xml');
        $loader->load('Logger/DependencyInjection/service.xml');
        $loader->load('MailDraft/DependencyInjection/controller.xml');
        $loader->load('MailDraft/DependencyInjection/service.xml');
        $loader->load('MessageQueueMonitoring/DependencyInjection/controller.xml');
        $loader->load('MessageQueueMonitoring/DependencyInjection/service.xml');
        $loader->load('Order/DependencyInjection/model.xml');
        $loader->load('Order/DependencyInjection/model-extension.xml');
        $loader->load('OrderCalculation/DependencyInjection/service.xml');
        $loader->load('OrderPickability/DependencyInjection/indexer.xml');
        $loader->load('OrderPickability/DependencyInjection/model.xml');
        $loader->load('OrderPickability/DependencyInjection/model-extension.xml');
        $loader->load('OrderPickability/DependencyInjection/service.xml');
        $loader->load('OrderShipping/DependencyInjection/controller.xml');
        $loader->load('OrderShipping/DependencyInjection/service.xml');
        $loader->load('Picking/DependencyInjection/service.xml');
        $loader->load('Picklist/DependencyInjection/service.xml');
        $loader->load('PriceCalculation/DependencyInjection/service.xml');
        $loader->load('Product/DependencyInjection/model.xml');
        $loader->load('Product/DependencyInjection/model-extension.xml');
        $loader->load('Product/DependencyInjection/service.xml');
        $loader->load('Product/DependencyInjection/template.xml');
        $loader->load('PurchaseList/DependencyInjection/controller.xml');
        $loader->load('PurchaseList/DependencyInjection/exception-handler.xml');
        $loader->load('PurchaseList/DependencyInjection/model.xml');
        $loader->load('PurchaseList/DependencyInjection/model-extension.xml');
        $loader->load('PurchaseList/DependencyInjection/service.xml');
        $loader->load('Reorder/DependencyInjection/scheduled-task.xml');
        $loader->load('Reorder/DependencyInjection/service.xml');
        $loader->load('Reporting/DependencyInjection/model.xml');
        $loader->load('Reporting/DependencyInjection/import-export.xml');
        $loader->load('ReturnOrder/DependencyInjection/controller.xml');
        $loader->load('ReturnOrder/DependencyInjection/model.xml');
        $loader->load('ReturnOrder/DependencyInjection/service.xml');
        $loader->load('Stock/DependencyInjection/container-override.xml');
        $loader->load('Stock/DependencyInjection/decorator.xml');
        $loader->load('Stock/DependencyInjection/import-export.xml');
        $loader->load('Stock/DependencyInjection/indexer.xml');
        $loader->load('Stock/DependencyInjection/model.xml');
        $loader->load('Stock/DependencyInjection/model-extension.xml');
        $loader->load('Stock/DependencyInjection/product-sales-update.xml');
        $loader->load('Stock/DependencyInjection/service.xml');
        $loader->load('StockApi/DependencyInjection/controller.xml');
        $loader->load('StockApi/DependencyInjection/service.xml');
        $loader->load('StockApi/DependencyInjection/subscriber.xml');
        $loader->load('StockFlow/DependencyInjection/controller.xml');
        $loader->load('StockFlow/DependencyInjection/service.xml');
        $loader->load('Stocking/DependencyInjection/service.xml');
        $loader->load('Stocktaking/DependencyInjection/controller.xml');
        $loader->load('Stocktaking/DependencyInjection/exception-handler.xml');
        $loader->load('Stocktaking/DependencyInjection/import-export.xml');
        $loader->load('Stocktaking/DependencyInjection/model.xml');
        $loader->load('Stocktaking/DependencyInjection/model-extension.xml');
        $loader->load('Stocktaking/DependencyInjection/service.xml');
        $loader->load('Stocktaking/ProductSummary/DependencyInjection/indexer.xml');
        $loader->load('Stocktaking/ProductSummary/DependencyInjection/model.xml');
        $loader->load('Stocktaking/ProductSummary/DependencyInjection/model-extension.xml');
        $loader->load('Stocktaking/ProductSummary/DependencyInjection/service.xml');
        $loader->load('Supplier/DependencyInjection/exception-handler.xml');
        $loader->load('Supplier/DependencyInjection/import-export.xml');
        $loader->load('Supplier/DependencyInjection/model.xml');
        $loader->load('Supplier/DependencyInjection/model-extension.xml');
        $loader->load('Supplier/DependencyInjection/model-subscriber.xml');
        $loader->load('Supplier/DependencyInjection/service.xml');
        $loader->load('Supplier/DependencyInjection/indexer.xml');
        $loader->load('SupplierOrder/DependencyInjection/controller.xml');
        $loader->load('SupplierOrder/DependencyInjection/import-export.xml');
        $loader->load('SupplierOrder/DependencyInjection/indexer.xml');
        $loader->load('SupplierOrder/DependencyInjection/model.xml');
        $loader->load('SupplierOrder/DependencyInjection/service.xml');
        $loader->load('Translation/DependencyInjection/service.xml');
        $loader->load('Warehouse/DependencyInjection/exception-handler.xml');
        $loader->load('Warehouse/DependencyInjection/import-export.xml');
        $loader->load('Warehouse/DependencyInjection/model.xml');
        $loader->load('Warehouse/DependencyInjection/model-extension.xml');
        $loader->load('Warehouse/DependencyInjection/service.xml');
        $loader->load('Warehouse/DependencyInjection/subscriber.xml');

        // If shopware-integration-test-plugin is loaded, register benchmarks in DIC
        if (in_array(ShopwareIntegrationTestPlugin::class, $containerBuilder->getParameter('kernel.bundles'), true)) {
            $loader->load('Benchmarking/DependencyInjection/benchmark.xml');
        }

        $containerBuilder->addCompilerPass(new ImporterRegistryCompilerPass());
        $containerBuilder->addCompilerPass(new ExporterRegistryCompilerPass());

        // Add SwagMigrationAssistant service decoration when the plugin is present.
        $activePlugins = $containerBuilder->getParameter('kernel.active_plugins');
        if (isset($activePlugins[SwagMigrationAssistant::class]) && interface_exists(WriterInterface::class)) {
            $loader->load('ShopwareMigration/DependencyInjection/service.xml');
        }
    }

    public function install(InstallContext $installContext): void
    {
        $this->loadDependenciesForSetup();

        $this->executeMigrationsOfBundles();

        BundleInstaller::createForContainerAndClass($this->container, self::class)
            ->install(self::ADDITIONAL_BUNDLES, $installContext);
    }

    public function postInstall(InstallContext $installContext): void
    {
        $installer = PickwareErpInstaller::initFromContainer($this->container);
        $installer->postInstall();
    }

    public function update(UpdateContext $updateContext): void
    {
        $this->loadDependenciesForSetup();

        $this->executeMigrationsOfBundles();

        BundleInstaller::createForContainerAndClass($this->container, self::class)
            ->install(self::ADDITIONAL_BUNDLES, $updateContext);

        // There are Migrations that require the stock-tables to be rebuilt after a plugin update.
        if ($updateContext->getPlugin()->isActive()) {
            // Only run the indexer when the plugin is activated during the update. If the plugin is disabled during the
            // update, the rerun of the indexer is triggered when activating the plugin.
            $this->runIndexer();
        }
    }

    public function postUpdate(UpdateContext $updateContext): void
    {
        $installer = PickwareErpInstaller::initFromContainer($this->container);
        $installer->postUpdate();

        if ($updateContext->getPlugin()->isActive()) {
            BundleInstaller::createForContainerAndClass($this->container, self::class)
                ->onAfterActivate(self::ADDITIONAL_BUNDLES, $updateContext);
        }
    }

    private function executeMigrationsOfBundles(): void
    {
        // All the services required for migration execution are private in the DI-Container. As a workaround the
        // services are instantiated explicitly here.
        /** @var Connection $db */
        $db = $this->container->get(Connection::class);
        // See vendor/symfony/monolog-bundle/Resources/config/monolog.xml on how the logger is defined.
        $logger = new Logger('app');
        $logger->useMicrosecondTimestamps($this->container->getParameter('monolog.use_microseconds'));
        $migrationCollectionLoader = new MigrationCollectionLoader($db, new MigrationRuntime($db, $logger));
        $migrationSource = new MigrationSource('PickwareErpStarter');

        foreach (self::ADDITIONAL_BUNDLES as $bundle) {
            $bundle::registerMigrations($migrationSource);
        }
        $migrationCollectionLoader->addSource($migrationSource);

        foreach ($migrationCollectionLoader->collectAll() as $migrationCollection) {
            $migrationCollection->sync();
            $migrationCollection->migrateInPlace();
        }
    }

    public function activate(ActivateContext $activateContext): void
    {
        // After the plugin has been activated, the indexer needs to run in order to ensure that any aggregated data
        // is consistent (i.e. stock quantities that are calculated from stock movements).
        // The StockIndexer is particularly important when the plugin is first installed, because the plugin creates
        // initial stock movements during its installation process, but the stock indexer is not yet registered so that
        // the aggregated stock quantities can not written during the initialization. Explicitly running the stock
        // indexer during activation ensures the aggregated stock entities are updated.
        $this->runIndexer();

        BundleInstaller::createForContainerAndClass($this->container, self::class)
            ->onAfterActivate(self::ADDITIONAL_BUNDLES, $activateContext);
    }

    private function runIndexer(): void
    {
        /** @var EntityIndexerRegistry $entityIndexerRegistry */
        $entityIndexerRegistry = $this->container->get('pickware.pickware_erp.entity_indexer_registry_public');
        // Removed the OrderPickabilityIndexer and ProductSalesIndexer in purpose:
        // OrderPickabilityUpdater: Is triggered by the OrderConfigurationIndexer (pickware-shopware-extensions-bundle)
        //   which is already triggered by the bundle's runIndexer().
        // ProductSalesIndexer: The product sales should be calculated correctly before ERP was installed/updated. Our
        //   indexer only replaces the calculation. Therefore, no new index run must take place.
        // See also this issue: https://github.com/pickware/shopware-plugins/issues/3410
        $entityIndexerRegistry->sendIndexingMessage([
            StockIndexer::NAME,
            IncomingStockIndexer::NAME,
            ProductSupplierConfigurationIndexer::NAME,
        ]);
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        if ($uninstallContext->keepUserData()) {
            return;
        }

        $this->loadDependenciesForSetup();

        $this->container->get(Connection::class)->executeStatement('
            SET FOREIGN_KEY_CHECKS = 0;

            -- Migration1556014423CreateAddressSchema.php
            DROP TABLE IF EXISTS `pickware_erp_address`;

            -- Migration1556014424CreateWarehouseSchema.php
            DROP TABLE IF EXISTS `pickware_erp_warehouse`;
            DROP TABLE IF EXISTS `pickware_erp_bin_location`;

            -- Migration1556118797CreateStockSchema.php
            DROP TABLE IF EXISTS `pickware_erp_special_stock_location`;
            DROP TABLE IF EXISTS `pickware_erp_location_type`;
            DROP TABLE IF EXISTS `pickware_erp_stock_movement`;
            DROP TABLE IF EXISTS `pickware_erp_stock`;

            -- Migration1584965609CreateConfigSchema.php
            DROP TABLE IF EXISTS `pickware_erp_config`;

            -- Migration1589893337AddWarehouseCustomFields.php
            DELETE FROM `custom_field_set_relation` WHERE `entity_name` = "pickware_erp_warehouse";

            -- Migration1594822086CreateProductWarehouseConfigurationSchema.php
            DROP TABLE IF EXISTS `pickware_erp_product_warehouse_configuration`;

            -- Migration1595323759CreateProductConfigurationSchema.php
            -- Migration1626766063RenameProductConfigurationAndAddIncomingStock
            DROP TABLE IF EXISTS `pickware_erp_product_configuration`;
            DROP TABLE IF EXISTS `pickware_erp_pickware_product`;

            -- Migration1597838146CreateWarehouseStockSchema.php
            DROP TABLE IF EXISTS `pickware_erp_warehouse_stock`;

            -- Migration1599487702CreateProductReorderView.php
            DROP VIEW IF EXISTS `pickware_erp_product_reorder_view`;

            -- Migration1599039841CreateSupplierSchema.php
            DROP TABLE IF EXISTS `pickware_erp_supplier`;

            -- Migration1601043196CreateProductSupplierConfigurationSchema.php
            DROP TABLE IF EXISTS `pickware_erp_product_supplier_configuration`;

            -- Migration1605002744CreateOrderPickabilityView.php
            DROP VIEW IF EXISTS `pickware_erp_order_pickability_view`;

            -- Migration1600420898CreateImportExportSchema
            DROP TABLE IF EXISTS `pickware_erp_import_export`;
            DROP TABLE IF EXISTS `pickware_erp_import_element`;
            DROP TABLE IF EXISTS `pickware_erp_import_export_profile`;

            -- Migration1606220870CreateStockValuationView.php
            DROP VIEW IF EXISTS `pickware_erp_stock_valuation_view`;

            -- Migration1616419772CreateMessageQueueMonitoring.php
            -- Migration1641313633FixMessageQueueMonitoringTableName.php
            DROP TABLE IF EXISTS `pickware_erp_message_queue_monitoring`;

            -- Migration1614587720CreateDemandPlanningListItemSchema.php
            DROP TABLE IF EXISTS `pickware_erp_demand_planning_session`;
            DROP TABLE IF EXISTS `pickware_erp_demand_planning_list_item`;

            -- Migration1614587720CreatePurchaseListItemSchema.php
            DROP TABLE IF EXISTS `pickware_erp_purchase_list_item`;

            -- Migration1618321583CreateExportElementSchemaAndImportExportConfigField
            DROP TABLE IF EXISTS `pickware_erp_import_export_element`;

            -- Migration1621419761AddSupplierOrderSchema.php
            DROP TABLE IF EXISTS `pickware_erp_supplier_order`;
            DROP TABLE IF EXISTS `pickware_erp_supplier_order_line_item`;

            -- Migration1648127351AddAnalyticsSchema.php
            DROP TABLE IF EXISTS `pickware_erp_analytics_profile`;
            DROP TABLE IF EXISTS `pickware_erp_analytics_session`;

            -- Migration1648127352MigrateDemandPlanningToAnalytics.php
            DROP TABLE IF EXISTS `pickware_erp_analytics_list_item_demand_planning`;

            -- Migration1649759025AddStockContainerSchema.php
            DROP TABLE IF EXISTS `pickware_erp_stock_container`;

            -- Migration1637931476CreateReturnOrderSchema.php
            DROP TABLE IF EXISTS `pickware_erp_return_order`;
            DROP TABLE IF EXISTS `pickware_erp_return_order_refund`;
            DROP TABLE IF EXISTS `pickware_erp_return_order_line_item`;
            DROP TABLE IF EXISTS `pickware_erp_return_order_document_mapping`;

            -- Migration1649314350ChangeAnalyticsToUseAggregationsAndReports.php
            DROP TABLE IF EXISTS `pickware_erp_analytics_aggregation`;
            DROP TABLE IF EXISTS `pickware_erp_analytics_aggregation_session`;
            DROP TABLE IF EXISTS `pickware_erp_analytics_report`;
            DROP TABLE IF EXISTS `pickware_erp_analytics_report_config`;

            -- Migration1649752132AddDemandPlanningAnalyticsAggregationSchema.php
            DROP TABLE IF EXISTS `pickware_erp_analytics_aggregation_item_demand_planning`;
            DROP TABLE IF EXISTS `pickware_erp_demand_planning_list_item`;

            -- Migration1658755075AddOrderPickabilitySchema.php
            DROP TABLE IF EXISTS `pickware_erp_order_pickability`;

            -- Migration1668172778CreateStocktakeSchema.php
            DROP TABLE IF EXISTS `pickware_erp_stocktaking_stocktake`;
            DROP TABLE IF EXISTS `pickware_erp_stocktaking_stocktake_counting_process`;
            DROP TABLE IF EXISTS `pickware_erp_stocktaking_stocktake_counting_process_item`;

            -- Migration1668172779CreateStocktakeProductSummarySchema.php
            DROP TABLE IF EXISTS `pickware_erp_stocktaking_stocktake_product_summary`;

            -- Migration1668172780CreateStocktakingStocktakeSnapshotItemSchema.php
            DROP TABLE IF EXISTS `pickware_erp_stocktaking_stocktake_snapshot_item`;

            -- Migration1675861056AddProductSalesUpdateQueueSchema.php
            DROP TABLE IF EXISTS `pickware_erp_product_sales_update_queue`;

            SET FOREIGN_KEY_CHECKS = 1;
        ');

        PickwareErpInstaller::initFromContainer($this->container)->uninstall();
        BundleInstaller::createForContainerAndClass($this->container, self::class)->uninstall($uninstallContext);
    }

    /**
     * Run the dependency loader for a setup step like install/update/uninstall
     *
     * When executing one of these steps but no Pickware plugin is activated, the dependency loader did never run until
     * the call of the corresponding method. You can trigger it with a call of this method.
     */
    private function loadDependenciesForSetup(): void
    {
        if (isset($GLOBALS['PICKWARE_DEPENDENCY_LOADER'])) {
            $plugins = $this->container->get('kernel')->getPluginLoader()->getPluginInfos();
            $projectDir = $this->container->getParameter('kernel.project_dir');
            $GLOBALS['PICKWARE_DEPENDENCY_LOADER']->ensureLatestDependenciesOfPluginsLoaded($plugins, $projectDir);
        }
    }
}
