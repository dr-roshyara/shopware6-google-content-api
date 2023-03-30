<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Supplier\ImportExportProfile;

use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\PickwareErpStarter\ImportExport\Exception\ImportExportException;

class ProductSupplierConfigurationException extends ImportExportException
{
    public const ERROR_CODE_NAMESPACE = 'PICKWARE_ERP__PRODUCT_SUPPLIER_CONFIGURATION_IMPORTER__';

    private const ERROR_CODE_PRODUCT_NOT_FOUND = self::ERROR_CODE_NAMESPACE . 'PRODUCT_NOT_FOUND';
    private const ERROR_CODE_SUPPLIER_NOT_FOUND_BY_NAME = self::ERROR_CODE_NAMESPACE . 'SUPPLIER_NOT_FOUND_BY_NAME';
    private const ERROR_CODE_SUPPLIER_NOT_FOUND_BY_NUMBER = self::ERROR_CODE_NAMESPACE . 'SUPPLIER_NOT_FOUND_BY_NUMBER';
    private const ERROR_CODE_MANUFACTURER_NOT_FOUND = self::ERROR_CODE_NAMESPACE . 'MANUFACTURER_NOT_FOUND';

    public static function createProductNotFoundError(string $productNumber): JsonApiError
    {
        return new JsonApiError([
            'code' => self::ERROR_CODE_PRODUCT_NOT_FOUND,
            'title' => 'Product not found',
            'detail' => sprintf('The product with the number "%s" could not be found.', $productNumber),
            'meta' => [
                'productNumber' => $productNumber,
            ],
        ]);
    }

    public static function createSupplierNotFoundByNameError(string $supplierName): JsonApiError
    {
        return new JsonApiError([
            'code' => self::ERROR_CODE_SUPPLIER_NOT_FOUND_BY_NAME,
            'title' => 'Supplier not found',
            'detail' => sprintf('The supplier with the name "%s" could not be found.', $supplierName),
            'meta' => [
                'supplierName' => $supplierName,
            ],
        ]);
    }

    public static function createSupplierNotFoundByNumberError(string $supplierNumber): JsonApiError
    {
        return new JsonApiError([
            'code' => self::ERROR_CODE_SUPPLIER_NOT_FOUND_BY_NUMBER,
            'title' => 'Supplier not found',
            'detail' => sprintf('The supplier with the number "%s" could not be found.', $supplierNumber),
            'meta' => [
                'supplierNumber' => $supplierNumber,
            ],
        ]);
    }

    public static function createManufacturerNotFoundError(string $manufacturerName): JsonApiError
    {
        return new JsonApiError([
            'code' => self::ERROR_CODE_MANUFACTURER_NOT_FOUND,
            'title' => 'Manufacturer not found',
            'detail' => sprintf('The manufacturer with the name "%s" could not be found.', $manufacturerName),
            'meta' => [
                'manufacturerName' => $manufacturerName,
            ],
        ]);
    }
}
