<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\SupplierOrder\Controller;

use Pickware\DalBundle\CriteriaJsonSerializer;
use Pickware\HttpUtils\ResponseFactory;
use Pickware\PickwareErpStarter\PriceCalculation\OrderRecalculationService;
use Pickware\PickwareErpStarter\PurchaseList\Model\PurchaseListItemDefinition;
use Pickware\PickwareErpStarter\SupplierOrder\Document\SupplierOrderDocumentContentGenerator;
use Pickware\PickwareErpStarter\SupplierOrder\Document\SupplierOrderDocumentGenerator;
use Pickware\PickwareErpStarter\SupplierOrder\SupplierOrderCreationService;
use Pickware\PickwareErpStarter\SupplierOrder\SupplierOrderStockingService;
use Pickware\ShopwareExtensionsBundle\GeneratedDocument\GeneratedDocumentExtension as DocumentBundleResponseFactory;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SupplierOrderController
{
    private SupplierOrderCreationService $supplierOrderCreationService;
    private SupplierOrderStockingService $supplierOrderStockingService;
    private OrderRecalculationService $orderRecalculationService;
    private CriteriaJsonSerializer $criteriaJsonSerializer;
    private SupplierOrderDocumentContentGenerator $supplierOrderDocumentContentGenerator;
    private SupplierOrderDocumentGenerator $supplierOrderDocumentGenerator;

    public function __construct(
        SupplierOrderCreationService $supplierOrderCreationService,
        SupplierOrderStockingService $supplierOrderStockingService,
        OrderRecalculationService $orderRecalculationService,
        CriteriaJsonSerializer $criteriaJsonSerializer,
        SupplierOrderDocumentContentGenerator $supplierOrderDocumentContentGenerator,
        SupplierOrderDocumentGenerator $supplierOrderDocumentGenerator
    ) {
        $this->supplierOrderCreationService = $supplierOrderCreationService;
        $this->supplierOrderStockingService = $supplierOrderStockingService;
        $this->orderRecalculationService = $orderRecalculationService;
        $this->criteriaJsonSerializer = $criteriaJsonSerializer;
        $this->supplierOrderDocumentContentGenerator = $supplierOrderDocumentContentGenerator;
        $this->supplierOrderDocumentGenerator = $supplierOrderDocumentGenerator;
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route(
     *     "/api/_action/pickware-erp/supplier-order/create-supplier-orders-from-purchase-list-items",
     *     methods={"POST"}
     * )
     */
    public function createSupplierOrdersFromPurchaseListItems(Request $request, Context $context): Response
    {
        $purchaseListItemIds = $request->get('purchaseListItemIds', []);
        if (count($purchaseListItemIds) === 0) {
            return ResponseFactory::createParameterMissingResponse('purchaseListItemIds');
        }

        $supplierOrderIds = $this->supplierOrderCreationService->createSupplierOrdersFromPurchaseListItems(
            $purchaseListItemIds,
            $context,
        );

        return new JsonResponse(['supplierOrderIds' => $supplierOrderIds]);
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route(
     *     "/api/_action/pickware-erp/supplier-order/create-supplier-orders-from-purchase-list-item-criteria",
     *     methods={"POST"}
     * )
     */
    public function createSupplierOrdersFromPurchaseListItemCriteria(Request $request, Context $context): Response
    {
        $serializedCriteria = $request->get('criteria');
        if ($serializedCriteria === null || !is_array($serializedCriteria)) {
            return ResponseFactory::createParameterMissingResponse('criteria');
        }

        $criteria = $this->criteriaJsonSerializer->deserializeFromArray(
            $serializedCriteria,
            PurchaseListItemDefinition::class,
        );

        $supplierOrderIds = $this->supplierOrderCreationService->createSupplierOrdersFromPurchaseListItemCriteria(
            $criteria,
            $context,
        );

        return new JsonResponse(['supplierOrderIds' => $supplierOrderIds]);
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route(
     *     "/api/_action/pickware-erp/supplier-order/{supplierOrderId}/stock-supplier-order",
     *     methods={"POST"}
     * )
     */
    public function stockSupplierOrder(string $supplierOrderId, Context $context): Response
    {
        $this->supplierOrderStockingService->stockSupplierOrder($supplierOrderId, $context);

        return new Response();
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route(
     *     "/api/_action/pickware-erp/supplier-order/{supplierOrderId}/document",
     *     methods={"GET"},
     * )
     */
    public function getDocument(Request $request, string $supplierOrderId, Context $context): Response
    {
        $languageId = $request->query->get('languageId');
        if (!$languageId) {
            return ResponseFactory::createParameterMissingResponse('languageId');
        }
        $templateVariables = $this->supplierOrderDocumentContentGenerator->generateFromSupplierOrder(
            $supplierOrderId,
            $languageId,
            $context,
        );
        $generatedDocument = $this->supplierOrderDocumentGenerator->generate($templateVariables, $languageId, $context);

        return DocumentBundleResponseFactory::createPdfResponse($generatedDocument);
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route(
     *     "/api/_action/pickware-erp/recalculate-supplier-orders",
     *     methods={"POST"}
     * )
     */
    public function recalculate(Request $request, Context $context): Response
    {
        $supplierOrderIds = $request->get('supplierOrderIds', []);
        if (!$supplierOrderIds || count($supplierOrderIds) === 0) {
            return ResponseFactory::createParameterMissingResponse('supplierOrderIds');
        }

        $this->orderRecalculationService->recalculateSupplierOrders($supplierOrderIds, $context);

        return new Response('', Response::HTTP_OK);
    }
}
