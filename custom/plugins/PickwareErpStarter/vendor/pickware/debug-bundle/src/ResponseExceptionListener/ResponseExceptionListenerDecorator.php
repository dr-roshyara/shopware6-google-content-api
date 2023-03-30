<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DebugBundle\ResponseExceptionListener;

use League\OAuth2\Server\Exception\OAuthServerException;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Api\EventListener\ResponseExceptionListener;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Throwable;

class ResponseExceptionListenerDecorator implements EventSubscriberInterface
{
    private const PICKWARE_APP_USER_AGENT_DETECTION_SUBSTRINGS = [
        'com.pickware.wms',
        'com.viison.pickware.POS',
    ];

    private ResponseExceptionListener $decoratedService;

    private LoggerInterface $errorLogger;
    private JwtValidator $jwtValidator;

    /**
     * We can't use a php type hint for the ResponseExceptionListener here since it does not implement an interface that
     * contains the non-static methods, and it could be decorated by a different plugin as well.
     *
     * @param ResponseExceptionListener $decoratedService
     */
    public function __construct($decoratedService, LoggerInterface $errorLogger, JwtValidator $jwtValidator)
    {
        $this->decoratedService = $decoratedService;
        $this->errorLogger = $errorLogger;
        $this->jwtValidator = $jwtValidator;
    }

    /**
     * Unfortunately, static methods can not be decorated, so we need to call the original method directly and hope that
     * no other plugin wraps this method and changes its return value.
     *
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return ResponseExceptionListener::getSubscribedEvents();
    }

    public function onKernelException(ExceptionEvent $originalEvent)
    {
        $event = $this->decoratedService->onKernelException($originalEvent);
        $exception = $event->getThrowable();

        if ($exception instanceof OAuthServerException) {
            return $event;
        }

        if ($this->shouldAddTraceToResponse($event->getRequest(), $event->getResponse())) {
            $event->setResponse($this->addTraceToResponse($event->getResponse(), $event->getThrowable()));
        }

        if ($this->shouldLogTrace($event->getRequest())) {
            $this->errorLogger->error($exception->getMessage(), [
                'exception' => $this->getTrace($exception),
            ]);
        }

        return $event;
    }

    private function shouldAddTraceToResponse(Request $request, ?Response $response): bool
    {
        if (!$this->containsValidDebugHeader($request)) {
            return false;
        }

        if (!$response) {
            return false;
        }

        if (!($response instanceof JsonResponse)) {
            return false;
        }

        if ($response->getStatusCode() === 401) {
            return false;
        }

        return true;
    }

    private function containsValidDebugHeader(Request $request): bool
    {
        $debugHeader = $request->headers->get('X-Pickware-Show-Trace');

        return $debugHeader && $this->jwtValidator->isJwtTokenValid($debugHeader);
    }

    private function addTraceToResponse(JsonResponse $response, Throwable $throwable): Response
    {
        $content = json_decode($response->getContent(), true);
        $content['trace'] = $this->getTrace($throwable);

        $response->setData($content);

        return $response;
    }

    private function shouldLogTrace(Request $request): bool
    {
        if ($this->containsValidDebugHeader($request)) {
            return true;
        }

        $userAgent = $request->headers->get('User-Agent');
        if (!$userAgent) {
            return false;
        }

        foreach (self::PICKWARE_APP_USER_AGENT_DETECTION_SUBSTRINGS as $substring) {
            if (str_contains(mb_strtolower($userAgent), mb_strtolower($substring))) {
                return true;
            }
        }

        return false;
    }

    private function getTrace(Throwable $throwable): array
    {
        // Remove args so that no credentials are logged.
        return array_map(function ($element) {
            unset($element['args']);

            return $element;
        }, $throwable->getTrace());
    }
}
