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

use InvalidArgumentException;
use League\Flysystem\FilesystemInterface;
use Pickware\DalBundle\EntityManager;
use Pickware\DocumentBundle\Model\DocumentDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Throwable;

class DocumentContentsService
{
    private FilesystemInterface $privateFilesystem;
    private EntityManager $entityManager;

    public function __construct(FilesystemInterface $privateFilesystem, EntityManager $entityManager)
    {
        $this->privateFilesystem = $privateFilesystem;
        $this->entityManager = $entityManager;
    }

    /**
     * @param resource $resource A resource for the stream
     * @param array $options Possible options are: string documentTypeTechnicalName (required), ?string memeType,
     *        ?PageFormat pageFormat, ?string orientation ("portrait" or "landscape"), ?string fileName,
     *        ?array extensions
     * @return string the id of the created document
     */
    public function saveStreamAsDocument($resource, Context $context, array $options = []): string
    {
        return $this->saveFileAsDocument(function (FilesystemInterface $filesystem, string $filePath) use ($resource): void {
            $filesystem->putStream($filePath, $resource);
        }, $context, $options);
    }

    /**
     * @param string $documentContents A string containing the document contents
     * @param array $options Possible options are: string documentType (required), ?string memeType,
     *        ?PageFormat pageFormat, ?string orientation ("portrait" or "landscape"), ?string fileName,
     *        ?array extensions
     * @return string the id of the created document
     */
    public function saveStringAsDocument(string $documentContents, Context $context, array $options = []): string
    {
        return $this->saveFileAsDocument(
            function (FilesystemInterface $filesystem, string $filePath) use ($documentContents): void {
                $filesystem->put($filePath, $documentContents);
            },
            $context,
            $options,
        );
    }

    private function saveFileAsDocument(callable $saveCallback, Context $context, array $options = []): string
    {
        if (!isset($options['documentTypeTechnicalName'])) {
            throw new InvalidArgumentException('Option "documentTypeTechnicalName" is required.');
        }
        if (isset($options['deepLinkCode'])) {
            throw new InvalidArgumentException(
                'Option "deepLinkCode" is not allowed. The deepLinkCode is generated automatically.',
            );
        }

        $documentId = Uuid::randomHex();
        $filePath = sprintf('documents/%s', $documentId);
        $saveCallback($this->privateFilesystem, $filePath);

        $payload = array_merge($options, [
            'id' => $documentId,
            'fileSizeInBytes' => $this->privateFilesystem->getSize($filePath),
            'pathInPrivateFileSystem' => $filePath,
        ]);
        try {
            $this->entityManager->create(DocumentDefinition::class, [$payload], $context);
        } catch (Throwable $e) {
            $this->privateFilesystem->delete($filePath);
            throw $e;
        }

        return $documentId;
    }
}
