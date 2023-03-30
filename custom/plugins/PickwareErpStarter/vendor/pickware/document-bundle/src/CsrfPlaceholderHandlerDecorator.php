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

use Shopware\Storefront\Framework\Csrf\CsrfPlaceholderHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsrfPlaceholderHandlerDecorator extends CsrfPlaceholderHandler
{
    /**
     * @var CsrfPlaceholderHandler
     */
    private $decoratedInstance;

    public function __construct(CsrfPlaceholderHandler $decoratedInstance)
    {
        $this->decoratedInstance = $decoratedInstance;
    }

    public function replaceCsrfToken(Response $response, Request $request): Response
    {
        if ($response instanceof StreamedResponse) {
            return $response;
        }

        return $this->decoratedInstance->replaceCsrfToken($response, $request);
    }
}
