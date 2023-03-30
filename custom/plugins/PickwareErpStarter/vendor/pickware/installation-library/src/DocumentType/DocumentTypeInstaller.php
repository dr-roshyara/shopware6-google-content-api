<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\InstallationLibrary\DocumentType;

use Doctrine\DBAL\Connection;
use InvalidArgumentException;
use Pickware\DalBundle\EntityManager;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigDefinition;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigEntity;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeDefinition;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

class DocumentTypeInstaller
{
    private ?Connection $db = null;
    private ?EntityManager $entityManager = null;

    public function __construct($argument)
    {
        /**
         * @deprecated tag:next-major Constructor will be changed to only accept an EntityManager as argument.
         */
        if ($argument instanceof Connection) {
            $this->db = $argument;
        } elseif ($argument instanceof EntityManager) {
            $this->entityManager = $argument;
        } else {
            throw new InvalidArgumentException(
                'Constructor argument must be of type %s or %s.',
                Connection::class,
                EntityManager::class,
            );
        }
    }

    public function installDocumentType(DocumentType $documentType): void
    {
        if ($this->entityManager) {
            $this->installDocumentTypeWithEntityManager($documentType);

            return;
        }

        // Note that the handling of the document installer was changed for this issue:
        // https://github.com/pickware/shopware-plugins/issues/2889 Note that these changes were only implemented for
        // the "...with entity manager" path of this installer and _not_ the deprecated SQL functions.
        $this->ensureDocumentTypeExists(
            $documentType->getTechnicalName(),
            $documentType->getTranslations(),
        );

        if ($documentType->getBaseConfigurationDocumentTypeTechnicalName()) {
            $this->copyDocumentConfigIfNotExists(
                $documentType->getBaseConfigurationDocumentTypeTechnicalName(),
                $documentType->getTechnicalName(),
                $documentType->getFilenamePrefix(),
                $documentType->getConfigOverwrite(),
            );
        } else {
            $this->upsertDocumentBaseConfig($documentType);
        }
    }

    private function installDocumentTypeWithEntityManager(DocumentType $documentType): void
    {
        $this->entityManager->runInTransactionWithRetry(function () use ($documentType): void {
            $documentTypeId = $this->ensureDocumentTypeExistsWithEntityManager($documentType);
            $this->ensureGlobalDocumentBaseConfigExistsWithEntityManager($documentType, $documentTypeId);
        });
    }

    private function ensureDocumentTypeExistsWithEntityManager(DocumentType $documentType): string
    {
        /** @var DocumentEntity|null $existingDocumentType */
        $existingDocumentType = $this->entityManager->findOneBy(
            DocumentTypeDefinition::class,
            ['technicalName' => $documentType->getTechnicalName()],
            Context::createDefaultContext(),
        );
        $documentTypeId = $existingDocumentType ? $existingDocumentType->getId() : Uuid::randomHex();

        $this->entityManager->upsert(
            DocumentTypeDefinition::class,
            [
                [
                    'id' => $documentTypeId,
                    'technicalName' => $documentType->getTechnicalName(),
                    'name' => $documentType->getTranslations(),
                ],
            ],
            Context::createDefaultContext(),
        );

        return $documentTypeId;
    }

    private function ensureGlobalDocumentBaseConfigExistsWithEntityManager(
        DocumentType $documentType,
        string $documentTypeId
    ): void {
        $existingGlobalBaseConfigs = $this->entityManager->findBy(
            DocumentBaseConfigDefinition::class,
            [
                'documentTypeId' => $documentTypeId,
                'global' => true,
            ],
            Context::createDefaultContext(),
        );
        if ($existingGlobalBaseConfigs->count() > 0) {
            return;
        }

        $this->entityManager->create(
            DocumentBaseConfigDefinition::class,
            [
                [
                    'id' => Uuid::randomHex(),
                    'documentTypeId' => $documentTypeId,
                    'name' => $documentType->getTechnicalName(),
                    'filenamePrefix' => $documentType->getFilenamePrefix(),
                    'config' => $this->determineDocumentConfiguration($documentType),
                    'global' => true,
                ],
            ],
            Context::createDefaultContext(),
        );
    }

    private function determineDocumentConfiguration(DocumentType $documentType): array
    {
        $config = $documentType->getConfigOverwrite();

        if ($documentType->getBaseConfigurationDocumentTypeTechnicalName()) {
            /** @var DocumentBaseConfigEntity $baseDocumentBaseConfig */
            $documentBaseConfigs = $this->entityManager->findBy(
                DocumentBaseConfigDefinition::class,
                [
                    'documentType.technicalName' => $documentType->getBaseConfigurationDocumentTypeTechnicalName(),
                    'global' => true,
                ],
                Context::createDefaultContext(),
            );

            $baseConfig = $documentBaseConfigs->count() > 0 ? $documentBaseConfigs->first()->getConfig() : [];
            $config = array_merge($baseConfig, $config);
        }

        return $config;
    }

    /**
     * Ensures that a document type exists for the given technical name.
     *
     * @param string $documentTypeTechnicalName Matches document_type.technical_name
     * @param array $nameTranslations Document type name for the locale codes de-DE and en-GB. Eg. [
     *   'de-DE' => 'Mein Dokument',
     *   'en-GB' => 'My Document',
     *]
     * @deprecated tag:next-major Method will be marked private with next major release. Install document types with
     * self::installDocumentType().
     */
    public function ensureDocumentTypeExists(
        string $documentTypeTechnicalName,
        array $nameTranslations
    ): void {
        if (!array_key_exists('de-DE', $nameTranslations) || !array_key_exists('en-GB', $nameTranslations)) {
            throw new InvalidArgumentException(
                'Document type translations must support locale codes "de-DE" and "en-GB"',
            );
        }

        $this->db->executeStatement(
            'INSERT INTO `document_type` (
                    `id`,
                    `technical_name`,
                    `created_at`
                ) VALUES (
                    :documentTypeId,
                    :technicalName,
                    NOW()
                ) ON DUPLICATE KEY UPDATE `id` = `id`',
            [
                'documentTypeId' => Uuid::randomBytes(),
                'technicalName' => $documentTypeTechnicalName,
            ],
        );
        $documentTypeId = $this->getDocumentTypeIdByTechnicalName($documentTypeTechnicalName);

        $this->db->executeStatement(
            'INSERT INTO `document_type_translation` (
                `document_type_id`,
                `language_id`,
                `name`,
                `created_at`
            )
            SELECT
                :documentTypeId,
                `language`.`id`,
                IF (locale.code = "de-DE", :labelDeDe, :labelEnGb),
                NOW(3)
            FROM `language`
            INNER JOIN `locale` ON `language`.`locale_id` = `locale`.`id`
            WHERE
                `locale`.`code` = "de-DE"
                OR `locale`.`code` = "en-GB"
            ON DUPLICATE KEY UPDATE `document_type_id` = `document_type_id`',
            [
                'documentTypeId' => $documentTypeId,
                'labelDeDe' => $nameTranslations['de-DE'],
                'labelEnGb' => $nameTranslations['en-GB'],
            ],
        );
    }

    private function upsertDocumentBaseConfig(DocumentType $documentType): void
    {
        $documentTypeId = $this->getDocumentTypeIdByTechnicalName($documentType->getTechnicalName());
        if (!$documentTypeId) {
            throw new InvalidArgumentException(
                sprintf('No document type was found for technical name "%s"', $documentType->getTechnicalName()),
            );
        }

        $existingDocumentBaseConfigId = $this->db->fetchOne(
            'SELECT `id`
            FROM `document_base_config`
            WHERE `document_type_id` = :documentTypeId',
            ['documentTypeId' => $documentTypeId],
        );

        $config = $documentType->getConfigOverwrite();
        if ($existingDocumentBaseConfigId) {
            $this->db->executeStatement(
                'UPDATE `document_base_config` SET `config` = :config WHERE `id` = :existingDocumentBaseConfigId',
                [
                    'config' => json_encode($config),
                    'existingDocumentBaseConfigId' => $existingDocumentBaseConfigId,
                ],
            );
        } else {
            $this->db->executeStatement(
                'INSERT INTO `document_base_config` (
                `id`,
                `document_type_id`,
                `name`,
                `filename_prefix`,
                `config`,
                `global`,
                `created_at`
            ) VALUES (
                :id,
                :documentTypeId,
                :name,
                :prefix,
                :config,
                1,
                NOW()
            )',
                [
                    'id' => Uuid::randomBytes(),
                    'documentTypeId' => $documentTypeId,
                    'name' => $documentType->getTechnicalName(),
                    'prefix' => $documentType->getFilenamePrefix(),
                    'config' => json_encode($config),
                ],
            );
        }
    }

    /**
     * Ensures that a document base config for the given document type exists. A new configuration will be copied from
     * a base document and can be (partially) overwritten with the given overwrite config.
     *
     * @deprecated tag:next-major Method will be marked private with next major release. Install document base
     * configuration with self::installDocumentBaseConfiguration().
     */
    public function copyDocumentConfigIfNotExists(
        string $baseDocumentTypeTechnicalName,
        string $destinationDocumentTypeTechnicalName,
        string $documentFilePrefix,
        array $configOverwrite = []
    ): void {
        $documentTypeId = $this->getDocumentTypeIdByTechnicalName($destinationDocumentTypeTechnicalName);
        if (!$documentTypeId) {
            throw new InvalidArgumentException(
                sprintf('No document type was found for technical name "%s"', $destinationDocumentTypeTechnicalName),
            );
        }

        // Since the document base config is not unique for each document type, check if 'any' document base config
        // exists for the document, add one otherwise
        $existingDocumentBaseConfigId = $this->db->fetchOne(
            'SELECT `id`
            FROM `document_base_config`
            WHERE `document_type_id` = :documentTypeId',
            [
                'documentTypeId' => $documentTypeId,
            ],
        );
        if ($existingDocumentBaseConfigId) {
            return;
        }

        $baseDocumentBaseConfig = $this->db->fetchOne(
            'SELECT `document_base_config`.`config`
            FROM `document_base_config`
            INNER JOIN `document_type` ON `document_type`.`id` = `document_base_config`.`document_type_id`
            WHERE `document_type`.`technical_name` = :technicalName',
            [
                'technicalName' => $baseDocumentTypeTechnicalName,
            ],
        );

        $config = json_decode($baseDocumentBaseConfig ?: '{}');
        foreach ($configOverwrite as $key => $value) {
            $config->{$key} = $value;
        }

        $this->db->executeStatement(
            'INSERT INTO `document_base_config` (
                `id`,
                `document_type_id`,
                `name`,
                `filename_prefix`,
                `config`,
                `global`,
                `created_at`
            ) VALUES (
                :id,
                :documentTypeId,
                :name,
                :prefix,
                :config,
                1,
                NOW()
            )',
            [
                'id' => Uuid::randomBytes(),
                'documentTypeId' => $documentTypeId,
                'name' => $destinationDocumentTypeTechnicalName,
                'prefix' => $documentFilePrefix,
                'config' => json_encode($config),
            ],
        );
    }

    private function getDocumentTypeIdByTechnicalName(string $documentTypeTechnicalName): ?string
    {
        $id = $this->db->fetchOne(
            'SELECT `id` FROM `document_type` WHERE `technical_name` = :technicalName',
            [
                'technicalName' => $documentTypeTechnicalName,
            ],
        );

        return is_string($id) ? $id : null;
    }
}
