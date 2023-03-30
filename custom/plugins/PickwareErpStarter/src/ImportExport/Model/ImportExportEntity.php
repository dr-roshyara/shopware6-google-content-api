<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\ImportExport\Model;

use DateTimeInterface;
use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Pickware\DocumentBundle\Model\DocumentEntity;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\User\UserEntity;

class ImportExportEntity extends Entity
{
    use EntityIdTrait;

    protected string $type;
    protected string $profileTechnicalName;
    protected array $config;
    protected ?string $userId = null;
    protected ?UserEntity $user = null;
    protected string $userComment;
    protected string $state;
    protected array $stateData;
    protected ?int $currentItem = null;
    protected ?int $totalNumberOfItems = null;
    protected bool $isDownloadReady;
    protected ?DateTimeInterface $startedAt = null;
    protected ?DateTimeInterface $completedAt = null;
    protected ?JsonApiErrors $errors = null;
    protected ?string $documentId = null;
    protected ?DocumentEntity $document = null;
    protected ?ImportExportElementCollection $importExportElements = null;

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getProfileTechnicalName(): string
    {
        return $this->profileTechnicalName;
    }

    public function setProfileTechnicalName(string $profileTechnicalName): void
    {
        $this->profileTechnicalName = $profileTechnicalName;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(?string $userId): void
    {
        if ($this->user && $this->user->getId() !== $userId) {
            $this->user = null;
        }
        $this->userId = $userId;
    }

    public function getUser(): ?UserEntity
    {
        if (!$this->user && $this->userId) {
            throw new AssociationNotLoadedException('user', $this);
        }

        return $this->user;
    }

    public function setUser(?UserEntity $user): void
    {
        if ($user) {
            $this->userId = $user->getId();
        }
        $this->user = $user;
    }

    public function getUserComment(): string
    {
        return $this->userComment;
    }

    public function setUserComment(string $userComment): void
    {
        $this->userComment = $userComment;
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function setState(string $state): void
    {
        $this->state = $state;
    }

    public function getStateData(): array
    {
        return $this->stateData;
    }

    public function setStateData(array $stateData): void
    {
        $this->stateData = $stateData;
    }

    public function getCurrentItem(): ?int
    {
        return $this->currentItem;
    }

    public function setCurrentItem(?int $currentItem): void
    {
        $this->currentItem = $currentItem;
    }

    public function getTotalNumberOfItems(): ?int
    {
        return $this->totalNumberOfItems;
    }

    public function setTotalNumberOfItems(?int $totalNumberOfItems): void
    {
        $this->totalNumberOfItems = $totalNumberOfItems;
    }

    public function getStartedAt(): ?DateTimeInterface
    {
        return $this->startedAt;
    }

    public function setStartedAt(?DateTimeInterface $startedAt): void
    {
        $this->startedAt = $startedAt;
    }

    public function getCompletedAt(): ?DateTimeInterface
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?DateTimeInterface $completedAt): void
    {
        $this->completedAt = $completedAt;
    }

    public function getErrors(): ?JsonApiErrors
    {
        return $this->errors;
    }

    public function setErrors(?JsonApiErrors $errors): void
    {
        $this->errors = $errors;
    }

    public function getDocumentId(): ?string
    {
        return $this->documentId;
    }

    public function setDocumentId(?string $documentId): void
    {
        if ($this->document && $this->document->getId() !== $documentId) {
            $this->document = null;
        }
        $this->documentId = $documentId;
    }

    public function getDocument(): ?DocumentEntity
    {
        if (!$this->document && $this->documentId) {
            throw new AssociationNotLoadedException('document', $this);
        }

        return $this->document;
    }

    public function setDocument(?DocumentEntity $document): void
    {
        if ($document) {
            $this->documentId = $document->getId();
        }
        $this->document = $document;
    }

    public function isDownloadReady(): bool
    {
        return $this->isDownloadReady;
    }

    public function getImportExportElements(): ?ImportExportElementCollection
    {
        if (!$this->importExportElements) {
            throw new AssociationNotLoadedException('importExportElements', $this);
        }

        return $this->importExportElements;
    }

    public function setImportExportElements(ImportExportElementCollection $importExportElements): void
    {
        $this->importExportElements = $importExportElements;
    }
}
