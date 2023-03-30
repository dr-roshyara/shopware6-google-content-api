<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\PurchaseList\Controller;

use Pickware\PickwareErpStarter\PurchaseList\PurchaseListService;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PurchaseListController
{
    private PurchaseListService $purchaseListService;

    public function __construct(PurchaseListService $purchaseListService)
    {
        $this->purchaseListService = $purchaseListService;
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route(
     *     "/api/_action/pickware-erp/purchase-list/clear",
     *     methods={"POST"}
     * )
     */
    public function clear(): Response
    {
        $this->purchaseListService->clearPurchaseList();

        return new JsonResponse();
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route(
     *     "/api/_action/pickware-erp/purchase-list/has-purchase-list-items-without-supplier",
     *     methods={"GET"}
     * )
     */
    public function hasPurchaseListItemsWithoutSupplier(): Response
    {
        return new JsonResponse([
            'hasPurchaseListItemsWithoutSupplier' => $this->purchaseListService->hasPurchaseListItemsWithoutSupplier(),
        ]);
    }
}
