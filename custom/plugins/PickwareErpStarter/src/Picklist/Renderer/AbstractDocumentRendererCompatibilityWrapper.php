<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);
// phpcs:ignoreFile

namespace Pickware\PickwareErpStarter\Picklist\Renderer;

use Shopware\Core\Checkout\Document\Renderer\AbstractDocumentRenderer;

// Checks if AbstractDocumentRenderer exists. This class only exists as of v6.4.14.0 and will initialize it if it's
// available. Initialize empty abstract class with no function if it's not available.
/* @deprecated next major version: Will to be removed with shopware v6.5.0.0 min compatibility
 */
if (class_exists(AbstractDocumentRenderer::class)) {
    abstract class AbstractDocumentRendererCompatibilityWrapper extends AbstractDocumentRenderer
    {
    }
} else {
    abstract class AbstractDocumentRendererCompatibilityWrapper
    {
    }
}
