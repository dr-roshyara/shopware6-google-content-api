<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\ImportExport;

use InvalidArgumentException;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Shopware\Core\Framework\Context;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;

class ImportExportScheduler
{
    private MessageBusInterface $bus;
    private ImportExportSchedulerMessageHandler $importExportSchedulerMessageHandler;
    private ImportExportStateService $importExportStateService;

    public function __construct(
        MessageBusInterface $bus,
        ImportExportSchedulerMessageHandler $importExportSchedulerMessageHandler,
        ImportExportStateService $importExportStateService
    ) {
        $this->bus = $bus;
        $this->importExportSchedulerMessageHandler = $importExportSchedulerMessageHandler;
        $this->importExportStateService = $importExportStateService;
    }

    public function scheduleImport(string $importId, Context $context, string $initialState = ImportExportSchedulerMessage::STATE_FILE_VALIDATION): void
    {
        $this->bus->dispatch(new ImportExportSchedulerMessage($importId, $initialState, $context));
    }

    public function scheduleExport(string $exportId, Context $context): void
    {
        $this->bus->dispatch(new ImportExportSchedulerMessage(
            $exportId,
            ImportExportSchedulerMessage::STATE_EXECUTE_EXPORT,
            $context,
        ));
    }

    public function __invoke(ImportExportSchedulerMessage $message): void
    {
        try {
            $this->process($message);
        } catch (Throwable $e) {
            // Catch every exception so the message is not retried by the message queue. Failed messages currently
            // cannot be retried because they are not implemented idempotently. Instead we assume that in any
            // case of an exception the import/export has failed hard.
            $errors = new JsonApiErrors([CsvErrorFactory::unknownError($e)]);
            $this->importExportStateService->fail($message->getImportExportId(), $errors, $message->getContext());
        }
    }

    private function process(ImportExportSchedulerMessage $message): void
    {
        switch ($message->getState()) {
            /** @deprecated this case will be removed with the deprecated state. Use STATE_FILE_VALIDATION instead. */
            case ImportExportSchedulerMessage::STATE_CSV_FILE_VALIDATION:
            case ImportExportSchedulerMessage::STATE_FILE_VALIDATION:
                $nextMessage = $this->importExportSchedulerMessageHandler->handleFileValidationMessage($message);
                break;
            /** @deprecated this case will be removed with the deprecated state. Use STATE_READ_FILE_TO_DATABASE instead. */
            case ImportExportSchedulerMessage::STATE_READ_CSV_TO_DATABASE:
            case ImportExportSchedulerMessage::STATE_READ_FILE_TO_DATABASE:
                $nextMessage = $this->importExportSchedulerMessageHandler->handleReadFileToDatabaseMessage($message);
                break;
            case ImportExportSchedulerMessage::STATE_EXECUTE_IMPORT:
                $nextMessage = $this->importExportSchedulerMessageHandler->handleExecuteImportMessage($message);
                break;
            case ImportExportSchedulerMessage::STATE_EXECUTE_EXPORT:
                $nextMessage = $this->importExportSchedulerMessageHandler->handleExecuteExportMessage($message);
                break;
            case ImportExportSchedulerMessage::STATE_WRITE_DATABASE_TO_CSV:
                $nextMessage = $this->importExportSchedulerMessageHandler->handleWriteDatabaseToCsvMessage($message);
                break;
            default:
                throw new InvalidArgumentException(sprintf(
                    'Invalid state passed to method %s',
                    __METHOD__,
                ));
        }
        if ($nextMessage !== null) {
            $this->bus->dispatch($nextMessage);
        }
    }
}
