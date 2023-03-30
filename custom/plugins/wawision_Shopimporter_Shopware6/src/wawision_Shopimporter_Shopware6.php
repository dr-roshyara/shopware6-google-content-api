<?php declare(strict_types=1);
/**
 * Shopware
 * Copyright © 2020
 *
 * @category   Shopware
 * @package    Shopimporter_Shopware6
 * @subpackage wawision_Shopimporter_Shopware6.php
 *
 * @copyright  2020 Iguana-Labs GmbH
 * @author     Module Factory <info at module-factory.com>
 * @license    https://www.module-factory.com/eula
 */

namespace wawision\Shopimporter_Shopware6;

use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use wawision\Shopimporter_Shopware6\Utils\CustomFieldInstaller;
use wawision\Shopimporter_Shopware6\Utils\InstallUninstall;

class wawision_Shopimporter_Shopware6 extends Plugin
{
    /**
     *
     * @param InstallContext $installContext
     */
    public function install(InstallContext $installContext): void
    {
        parent::install($installContext);

        (new InstallUninstall($this->container))->install($installContext->getContext());
    }

    /**
     *
     * @param UninstallContext $uninstallContext
     */
    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        if ($uninstallContext->keepUserData()) {
            return;
        }

        (new InstallUninstall($this->container))->uninstall($uninstallContext->getContext());
        (new CustomFieldInstaller($this->container))->uninstall($uninstallContext);
    }

    /**
     *
     * @param ActivateContext $activateContext
     */
    public function activate(ActivateContext $activateContext): void
    {
        (new CustomFieldInstaller($this->container))->activate($activateContext);

        parent::activate($activateContext);
    }

    /**
     *
     * @param DeactivateContext $deactivateContext
     */
    public function deactivate(DeactivateContext $deactivateContext): void
    {
        (new CustomFieldInstaller($this->container))->deactivate($deactivateContext);

        parent::deactivate($deactivateContext);
    }

}
