<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\BarcodeLabel\Controller;

use Pickware\PickwareErpStarter\BarcodeLabel\BarcodeLabelConfiguration;
use Pickware\PickwareErpStarter\BarcodeLabel\BarcodeLabelService;
use Pickware\ShopwareExtensionsBundle\GeneratedDocument\GeneratedDocumentExtension as DocumentBundleResponseFactory;
use Pickware\ValidationBundle\Annotation\JsonValidation;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class BarcodeLabelController
{
    private BarcodeLabelService $barcodeLabelService;

    public function __construct(BarcodeLabelService $barcodeLabelService)
    {
        $this->barcodeLabelService = $barcodeLabelService;
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route(
     *     "/api/_action/pickware-erp/barcode-label/create-barcode-labels",
     *     methods={"POST"}
     * )
     * @JsonValidation(schemaFilePath="payload-create-barcode-labels.schema.json")
     */
    public function createBarcodeLabels(Request $request, Context $context): Response
    {
        $labelConfiguration = BarcodeLabelConfiguration::fromArray($request->request->all());
        $generatedDocument = $this->barcodeLabelService->createBarcodeLabels($labelConfiguration, $context);

        return DocumentBundleResponseFactory::createPdfResponse($generatedDocument);
    }
}
