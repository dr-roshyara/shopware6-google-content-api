<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DocumentBundle\Installation;

use Doctrine\DBAL\Connection;
use League\Flysystem\FilesystemInterface;
use ReflectionClass;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Framework\Adapter\Filesystem\PrefixFilesystem;
use Symfony\Component\DependencyInjection\ContainerInterface;

class DocumentUninstaller
{
    private Connection $db;
    private PrefixFilesystem $documentBundlePrivateFileSystem;

    public function __construct(Connection $db, FilesystemInterface $shopwarePrivateFileSystem)
    {
        $this->db = $db;
        $this->documentBundlePrivateFileSystem = new PrefixFilesystem(
            $shopwarePrivateFileSystem,
            'plugins/document_bundle',
        );
    }

    public static function createForContainer(ContainerInterface $container): self
    {
        // We use the public service "FileSaver" to get the shopware.filesystem.private service as this service is
        // private in the DI Container.
        $fileSaver = $container->get(FileSaver::class);
        $reflectionClass = new ReflectionClass($fileSaver);
        $reflectionProperty = $reflectionClass->getProperty('filesystemPrivate');
        $reflectionProperty->setAccessible(true);
        /** @var FilesystemInterface $privateFilesystem */
        $privateFilesystem = $reflectionProperty->getValue($fileSaver);

        return new self($container->get(Connection::class), $privateFilesystem);
    }

    /**
     * @param string[] $documentIds
     */
    public function removeDocuments(array $documentIds): void
    {
        if (count($documentIds) === 0) {
            return;
        }

        $fileNames = $this->db->fetchAllAssociative(
            'SELECT
                `path_in_private_file_system` AS fileName
            FROM `pickware_document`
            WHERE `id` IN (:documentIds)',
            ['documentIds' => array_map('hex2bin', $documentIds)],
            ['documentIds' => Connection::PARAM_STR_ARRAY],
        );
        $fileNames = array_column($fileNames, 'fileName');

        array_map(function (string $fileName): void {
            if ($this->documentBundlePrivateFileSystem->has($fileName)) {
                $this->documentBundlePrivateFileSystem->delete($fileName);
            }
        }, $fileNames);

        $this->db->executeStatement(
            'DELETE FROM pickware_document WHERE id IN (:documentIds)',
            ['documentIds' => array_map('hex2bin', $documentIds)],
            ['documentIds' => Connection::PARAM_STR_ARRAY],
        );
    }

    private function removeDocumentsForDocumentType(string $documentTypeTechnicalName): void
    {
        $documentIds = $this->db->fetchFirstColumn(
            'SELECT `id`
            FROM `pickware_document`
            LEFT JOIN `pickware_document_type`
                ON `pickware_document`.`document_type_technical_name` = `pickware_document_type`.`technical_name`
            WHERE `pickware_document_type`.`technical_name` = :documentTypeTechnicalName',
            ['documentTypeTechnicalName' => $documentTypeTechnicalName],
        );

        $this->removeDocuments(array_map('bin2hex', $documentIds));
    }

    public function removeDocumentType(string $documentTypeTechnicalName): void
    {
        $this->removeDocumentsForDocumentType($documentTypeTechnicalName);

        $this->db->executeStatement(
            'DELETE FROM `pickware_document_type` WHERE `technical_name` = :technicalName',
            ['technicalName' => $documentTypeTechnicalName],
        );
    }
}
