<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Installation;

use Doctrine\DBAL\Connection;
use Pickware\DalBundle\EntityManager;
use Pickware\DocumentBundle\Installation\DocumentUninstaller as PickwareDocumentUninstaller;
use Pickware\DocumentBundle\Installation\EnsureDocumentTypeInstallationStep as EnsurePickwareDocumentTypeInstallationStep;
use Pickware\InstallationLibrary\DocumentType\DocumentTypeInstaller;
use Pickware\InstallationLibrary\MailTemplate\MailTemplateInstaller;
use Pickware\InstallationLibrary\MailTemplate\MailTemplateUninstaller;
use Pickware\InstallationLibrary\NumberRange\NumberRangeInstaller;
use Pickware\InstallationLibrary\SqlView\SqlViewInstaller;
use Pickware\InstallationLibrary\StateMachine\StateMachineInstaller;
use Pickware\PickwareErpStarter\DemandPlanning\AnalyticsProfile\DemandPlanningAnalyticsAggregation;
use Pickware\PickwareErpStarter\DemandPlanning\AnalyticsProfile\DemandPlanningAnalyticsReport;
use Pickware\PickwareErpStarter\Installation\Analytics\AnalyticsInstaller;
use Pickware\PickwareErpStarter\Installation\Steps\CreateConfigInstallationStep;
use Pickware\PickwareErpStarter\Installation\Steps\CreateInitialWarehouseInstallationStep;
use Pickware\PickwareErpStarter\Installation\Steps\CreateReorderNotificationFlowInstallationStep;
use Pickware\PickwareErpStarter\Installation\Steps\InitializeStockInstallationStep;
use Pickware\PickwareErpStarter\Installation\Steps\UpsertImportExportProfilesInstallationStep;
use Pickware\PickwareErpStarter\Installation\Steps\UpsertLocationTypesInstallationStep;
use Pickware\PickwareErpStarter\Installation\Steps\UpsertProductSupplierConfigurationsInstallationStep;
use Pickware\PickwareErpStarter\Installation\Steps\UpsertSpecialStockLocationsInstallationStep;
use Pickware\PickwareErpStarter\InvoiceCorrection\InvoiceCorrectionDocumentType;
use Pickware\PickwareErpStarter\InvoiceCorrection\InvoiceCorrectionNumberRange;
use Pickware\PickwareErpStarter\Order\Model\OrderPickabilityViewSqlView;
use Pickware\PickwareErpStarter\Picklist\PicklistDocumentType;
use Pickware\PickwareErpStarter\Picklist\PicklistNumberRange;
use Pickware\PickwareErpStarter\PickwareErpStarter;
use Pickware\PickwareErpStarter\Reorder\ReorderMailTemplate;
use Pickware\PickwareErpStarter\Reporting\ImportExportProfile\StockValuationExporter;
use Pickware\PickwareErpStarter\Reporting\Model\StockValuationViewSqlView;
use Pickware\PickwareErpStarter\ReturnOrder\ReturnOrderNumberRange;
use Pickware\PickwareErpStarter\ReturnOrder\ReturnOrderRefundStateMachine;
use Pickware\PickwareErpStarter\ReturnOrder\ReturnOrderStateMachine;
use Pickware\PickwareErpStarter\Stock\ImportExportProfile\AbsoluteStock\AbsoluteStockImporter;
use Pickware\PickwareErpStarter\Stock\ImportExportProfile\RelativeStockChange\RelativeStockChangeImporter;
use Pickware\PickwareErpStarter\Stock\ImportExportProfile\StockPerProduct\StockPerProductExporter;
use Pickware\PickwareErpStarter\Stock\ImportExportProfile\StockPerStockLocation\StockPerStockLocationExporter;
use Pickware\PickwareErpStarter\Stock\ImportExportProfile\StockPerWarehouse\StockPerWarehouseExporter;
use Pickware\PickwareErpStarter\Stocktaking\ImportExportProfile\StocktakeExporter;
use Pickware\PickwareErpStarter\Stocktaking\StocktakeCountingProcessNumberRange;
use Pickware\PickwareErpStarter\Stocktaking\StocktakeNumberRange;
use Pickware\PickwareErpStarter\Supplier\ImportExportProfile\ProductSupplierConfigurationExporter;
use Pickware\PickwareErpStarter\Supplier\ImportExportProfile\SupplierImporter;
use Pickware\PickwareErpStarter\Supplier\SupplierNumberRange;
use Pickware\PickwareErpStarter\SupplierOrder\ImportExportProfile\SupplierOrderExporter;
use Pickware\PickwareErpStarter\SupplierOrder\SupplierOrderDocumentType;
use Pickware\PickwareErpStarter\SupplierOrder\SupplierOrderMailTemplate;
use Pickware\PickwareErpStarter\SupplierOrder\SupplierOrderNumberRange;
use Pickware\PickwareErpStarter\SupplierOrder\SupplierOrderPaymentStateMachine;
use Pickware\PickwareErpStarter\SupplierOrder\SupplierOrderStateMachine;
use Pickware\PickwareErpStarter\Warehouse\Import\BinLocationImporter;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class PickwareErpInstaller
{
    private Connection $db;
    private MailTemplateInstaller $mailTemplateInstaller;
    private MailTemplateUninstaller $mailTemplateUninstaller;
    private NumberRangeInstaller $numberRangeInstaller;
    private StateMachineInstaller $stateMachineInstaller;
    private DocumentTypeInstaller $documentTypeInstaller;
    private PickwareDocumentUninstaller $pickwareDocumentUninstaller;
    private SqlViewInstaller $viewInstaller;
    private EntityManager $entityManager;
    private EventDispatcherInterface $eventDispatcher;
    private LoggerInterface $businessEventsLogger;

    private function __construct()
    {
        // Create an instance with ::initFromContainer()
    }

    public static function initFromContainer(ContainerInterface $container): self
    {
        $self = new self();

        $self->db = $container->get(Connection::class);
        $self->entityManager = new EntityManager($container, $self->db, null);
        $self->mailTemplateInstaller = new MailTemplateInstaller($self->entityManager);
        $self->mailTemplateUninstaller = new MailTemplateUninstaller($self->entityManager);
        $self->numberRangeInstaller = new NumberRangeInstaller($self->entityManager);
        $self->stateMachineInstaller = new StateMachineInstaller($self->entityManager);
        $self->documentTypeInstaller = new DocumentTypeInstaller($self->entityManager);
        $self->pickwareDocumentUninstaller = PickwareDocumentUninstaller::createForContainer($container);
        $self->viewInstaller = new SqlViewInstaller($self->db);
        $self->businessEventsLogger = $container->get('monolog.logger.business_events');
        $self->eventDispatcher = $container->get('event_dispatcher');

        return $self;
    }

    public function postInstall(): void
    {
        $this->postUpdate();
    }

    public function postUpdate(): void
    {
        (new UpsertLocationTypesInstallationStep($this->db))->install();
        $this->mailTemplateInstaller->installMailTemplate(new ReorderMailTemplate(), $this->businessEventsLogger);
        (new CreateReorderNotificationFlowInstallationStep($this->entityManager))->install();
        (new UpsertSpecialStockLocationsInstallationStep($this->db))->install();
        (new CreateInitialWarehouseInstallationStep($this->db))->install();
        $this->documentTypeInstaller->installDocumentType(new PicklistDocumentType());
        $this->documentTypeInstaller->installDocumentType(new InvoiceCorrectionDocumentType());
        $this->documentTypeInstaller->installDocumentType(new SupplierOrderDocumentType());
        $this->numberRangeInstaller->ensureNumberRange(new PicklistNumberRange());
        (new CreateConfigInstallationStep($this->db))->install();
        (new InitializeStockInstallationStep($this->db, $this->eventDispatcher))->install();
        $this->numberRangeInstaller->ensureNumberRange(new SupplierNumberRange());
        (new EnsurePickwareDocumentTypeInstallationStep(
            $this->db,
            PickwareErpStarter::DOCUMENT_TYPE_TECHNICAL_NAME_DESCRIPTION_MAPPING,
        ))->install();
        (new UpsertImportExportProfilesInstallationStep($this->db, [
            RelativeStockChangeImporter::TECHNICAL_NAME,
            AbsoluteStockImporter::TECHNICAL_NAME,
            BinLocationImporter::TECHNICAL_NAME,
            StockPerProductExporter::TECHNICAL_NAME,
            StockPerWarehouseExporter::TECHNICAL_NAME,
            StockPerStockLocationExporter::TECHNICAL_NAME,
            ProductSupplierConfigurationExporter::TECHNICAL_NAME,
            SupplierOrderExporter::TECHNICAL_NAME,
            StockValuationExporter::TECHNICAL_NAME,
            SupplierImporter::TECHNICAL_NAME,
            StocktakeExporter::TECHNICAL_NAME,
        ]))->install();
        $this->numberRangeInstaller->ensureNumberRange(new SupplierOrderNumberRange());
        $this->stateMachineInstaller->ensureStateMachine(new SupplierOrderStateMachine());
        $this->stateMachineInstaller->ensureStateMachine(new SupplierOrderPaymentStateMachine());
        $this->stateMachineInstaller->ensureStateMachine(new ReturnOrderStateMachine());
        $this->stateMachineInstaller->ensureStateMachine(new ReturnOrderRefundStateMachine());
        (new UpsertProductSupplierConfigurationsInstallationStep($this->db))->install();
        $this->mailTemplateInstaller->installMailTemplate(new SupplierOrderMailTemplate(), $this->businessEventsLogger);
        $this->viewInstaller->ensureSqlViewExists(new OrderPickabilityViewSqlView());
        $this->viewInstaller->ensureSqlViewExists(new StockValuationViewSqlView());
        $this->numberRangeInstaller->ensureNumberRange(new ReturnOrderNumberRange());
        $this->numberRangeInstaller->ensureNumberRange(new InvoiceCorrectionNumberRange());
        $this->numberRangeInstaller->ensureNumberRange(new StocktakeNumberRange());
        $this->numberRangeInstaller->ensureNumberRange(new StocktakeCountingProcessNumberRange());
        (new AnalyticsInstaller($this->db))->installAggregations([new DemandPlanningAnalyticsAggregation()]);
        (new AnalyticsInstaller($this->db))->installReports([new DemandPlanningAnalyticsReport()]);
    }

    public function uninstall(): void
    {
        $this->mailTemplateUninstaller->uninstallMailTemplate(new ReorderMailTemplate());
        (new CreateReorderNotificationFlowInstallationStep($this->entityManager))->uninstall();
        $this->mailTemplateUninstaller->uninstallMailTemplate(new SupplierOrderMailTemplate());
        $pickwareDocumentTypes = array_keys(PickwareErpStarter::DOCUMENT_TYPE_TECHNICAL_NAME_DESCRIPTION_MAPPING);
        foreach ($pickwareDocumentTypes as $pickwareDocumentType) {
            $this->pickwareDocumentUninstaller->removeDocumentType($pickwareDocumentType);
        }
    }
}
