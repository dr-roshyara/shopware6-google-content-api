<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\BarcodeLabel;

use Pickware\PickwareErpStarter\BarcodeLabel\DataProvider\BarcodeLabelDataProviderRegistry;
use Shopware\Core\Checkout\Document\GeneratedDocument;
use Shopware\Core\Framework\Context;

class BarcodeLabelService
{
    private BarcodeLabelDataProviderRegistry $barcodeLabelDataProviderRegistry;
    private BarcodeLabelRenderer $barcodeLabelRenderer;

    public function __construct(
        BarcodeLabelDataProviderRegistry $barcodeLabelDataProviderRegistry,
        BarcodeLabelRenderer $barcodeLabelRenderer
    ) {
        $this->barcodeLabelDataProviderRegistry = $barcodeLabelDataProviderRegistry;
        $this->barcodeLabelRenderer = $barcodeLabelRenderer;
    }

    public function createBarcodeLabels(
        BarcodeLabelConfiguration $labelConfiguration,
        Context $context
    ): GeneratedDocument {
        $dataProvider = $this->barcodeLabelDataProviderRegistry->getDataProviderByBarcodeLabelType(
            $labelConfiguration->getBarcodeLabelType(),
        );

        return $this->barcodeLabelRenderer->render(
            $labelConfiguration,
            $dataProvider->getData($labelConfiguration, $context),
        );
    }
}
