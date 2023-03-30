<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ApiErrorHandlingBundle\ControllerExceptionHandling;

use Pickware\HttpUtils\JsonApi\JsonApiErrorSerializable;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\PlatformRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Throwable;

class AdminApiJsonApiErrorSerializableExceptionHandler implements EventSubscriberInterface
{
    private bool $debug;
    private LoggerInterface $logger;

    public function __construct(bool $debug, LoggerInterface $logger)
    {
        $this->debug = $debug;
        $this->logger = $logger;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Use Priority 0 because Shopware uses -1 in its  ResponseExceptionListener, and we want to run BEFORE
            // Shopware. Otherwise, Shopware would handle our error.
            ExceptionEvent::class => [
                'onKernelException',
                0,
            ],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $throwable = $event->getThrowable();
        if (!($throwable instanceof JsonApiErrorSerializable)) {
            return;
        }

        /** @var Context $context */
        $context = $event->getRequest()->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);

        // Only the Admin API uses JSON API therefore we can only respond with JSON API error here.
        if (!$context || !($context->getSource() instanceof AdminApiSource)) {
            return;
        }

        $error = $throwable->serializeToJsonApiError();
        $exceptionDetails = $this->getExceptionDetails($throwable);
        $this->logger->error(
            sprintf('Caught JsonApiErrorSerializable exception in admin controller: %s', $throwable->getMessage()),
            [
                'exception' => $exceptionDetails,
                'jsonApiError' => $error,
            ],
        );

        if ($this->debug) {
            $meta = $error->getMeta() ?? [];
            $meta['_exceptionDetails'] = $exceptionDetails;
            $error->setMeta($meta);
        }
        $event->setResponse($error->toJsonApiErrorResponse(500));
    }

    private function getExceptionDetails(Throwable $exception): array
    {
        $details = [
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $this->escapeStringsInStacktrace($exception->getTrace()),
        ];

        if ($exception->getPrevious()) {
            $details['previous'] = $this->getExceptionDetails($exception->getPrevious());
        }

        return $details;
    }

    private function escapeStringsInStacktrace(array $array): array
    {
        foreach ($array as $key => $value) {
            if (\is_array($value)) {
                $array[$key] = $this->escapeStringsInStacktrace($value);
            }

            if (\is_string($value)) {
                if (!ctype_print($value) && mb_strlen($value) === 16) {
                    $array[$key] = sprintf('ATTENTION: Converted binary string by the "%s": %s', self::class, bin2hex($value));
                } elseif (!mb_detect_encoding($value, mb_detect_order(), true)) {
                    $array[$key] = utf8_encode($value);
                }
            }
        }

        return $array;
    }
}
