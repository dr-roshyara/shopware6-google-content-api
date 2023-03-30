<?php declare(strict_types=1);
/**
 * Shopware
 * Copyright © 2020
 *
 * @category   Shopware
 * @package    Shopimporter_Shopware6
 * @subpackage CustomFieldInstaller.php
 *
 * @copyright  2020 Iguana-Labs GmbH
 * @author     Module Factory <info at module-factory.com>
 * @license    https://www.module-factory.com/eula
 */

namespace wawision\Shopimporter_Shopware6\Utils;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CustomFieldInstaller
{
    public const WAWISIONSHOPIMPORTER_SET = 'wawision_shopimporter';
    public const WAWISIONSHOPIMPORTER_SYNCSTATE = 'wawision_shopimporter_syncstate';

    /** @var ContainerInterface */
    private $container;

    /** @var EntityRepositoryInterface */
    private $customFieldRepository;

    /** @var EntityRepositoryInterface */
    private $customFieldSetRepository;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->customFieldSetRepository = $container->get('custom_field_set.repository');
        $this->customFieldRepository    = $container->get('custom_field.repository');
    }

    /**
     * Hole die Fieldsets zum importieren
     *
     * @return array
     */
    private function getFieldSet()
    {
        return [
            [
                'id' => md5(self::WAWISIONSHOPIMPORTER_SET),
                'name' => self::WAWISIONSHOPIMPORTER_SET,
                'config' => [
                    'label' => [
                        'de-DE' => 'Xentral',
                        'en-GB' => 'Xentral'
                    ],
                    'translated' => false
                ]
            ]
        ];
    }

    /**
     * Hole die CustomFields zum importieren
     *
     * @return array
     */
    private function getCustomFields()
    {
         return [
            [
                'id' => md5(self::WAWISIONSHOPIMPORTER_SYNCSTATE),
                'name' => self::WAWISIONSHOPIMPORTER_SYNCSTATE,
                'type' => CustomFieldTypes::INT,
                'customFieldSetId' => md5(self::WAWISIONSHOPIMPORTER_SET),
                'config' => [
//                    "type": "number",
//	"label": {
//        "en-GB": null
//	},
//	"helpText": {
//        "en-GB": null
//	},
	                    'numberType' => 'int',
                    'label' => [
                        'de-DE' => 'Xentral SyncState',
                        'en-GB' => 'Xentral SyncState'
                    ]
                ]
            ],
        ];
    }

    /**
     *
     * @param ActivateContext $activateContext
     */
    public function activate(ActivateContext $activateContext): void
    {
        foreach ($this->getFieldSet() as $customFieldSet) {
            $this->upsertCustomFieldSet($customFieldSet, $activateContext->getContext(), false);
        }
        foreach ($this->getCustomFields() as $customField) {
            $this->upsertCustomField($customField, $activateContext->getContext(), false);
        }
    }

    /**
     *
     * @param DeactivateContext $deactivateContext
     */
    public function deactivate(DeactivateContext $deactivateContext): void
    {
        foreach ($this->getFieldSet() as $customFieldSet) {
            $this->upsertCustomFieldSet($customFieldSet, $deactivateContext->getContext(), false);
        }
        foreach ($this->getCustomFields() as $customField) {
            $this->upsertCustomField($customField, $deactivateContext->getContext(), false);
        }
    }

    /**
     *
     * @param UninstallContext $uninstallContext
     */
    public function uninstall(UninstallContext $uninstallContext): void
    {
        foreach ($this->getFieldSet() as $customFieldSet) {
            $this->deleteCustomFieldSet($customFieldSet, $uninstallContext->getContext());
        }
        foreach ($this->getCustomFields() as $customField) {
            $this->deleteCustomField($customField, $uninstallContext->getContext());
        }
    }

    private function upsertCustomFieldSet(array $customFieldSet, Context $context, bool $activate = true): void
    {
        $customFieldSet['active'] = $activate;

        $this->customFieldSetRepository->upsert([$customFieldSet], $context);
    }

    private function upsertCustomField(array $customField, Context $context, bool $activate = true): void
    {
        $customField['active'] = $activate;

        $this->customFieldRepository->upsert([$customField], $context);
    }

    private function deleteCustomFieldSet(array $customFieldSet, Context $context): void
    {
        $this->customFieldSetRepository->delete([$customFieldSet], $context);
    }

    private function deleteCustomField(array $customField, Context $context): void
    {
        $this->customFieldRepository->delete([$customField], $context);
    }
}
