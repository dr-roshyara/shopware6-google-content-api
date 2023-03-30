<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShopwareExtensionsBundle\GeneratedDocument;

use Shopware\Core\Checkout\Document\GeneratedDocument;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

class GeneratedDocumentExtension
{
    /**
     * Returns a response for the content of a document. See also
     * Shopware\Core\Checkout\Document\Controller::previewDocument()
     */
    public static function createPdfResponse(GeneratedDocument $generatedDocument): Response
    {
        $disposition = HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_INLINE, $generatedDocument->getFilename());
        $response = new Response($generatedDocument->getFileBlob());
        $response->headers->set('Content-Type', $generatedDocument->getContentType());
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }
}
