<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SwagMigrationAssistant\Test\Profile\Shopware63\Converter;

use SwagMigrationAssistant\Migration\Converter\ConverterInterface;
use SwagMigrationAssistant\Migration\DataSelection\DataSet\DataSet;
use SwagMigrationAssistant\Migration\Logging\LoggingServiceInterface;
use SwagMigrationAssistant\Migration\Media\MediaFileServiceInterface;
use SwagMigrationAssistant\Profile\Shopware6\DataSelection\DataSet\DocumentInheritanceDataSet;
use SwagMigrationAssistant\Profile\Shopware6\Mapping\Shopware6MappingServiceInterface;
use SwagMigrationAssistant\Profile\Shopware63\Converter\Shopware63DocumentInheritanceConverter;

class DocumentInheritanceConverterTest extends ShopwareConverterTest
{
    protected function createConverter(Shopware6MappingServiceInterface $mappingService, LoggingServiceInterface $loggingService, MediaFileServiceInterface $mediaFileService): ConverterInterface
    {
        return new Shopware63DocumentInheritanceConverter($mappingService, $loggingService);
    }

    protected function createDataSet(): DataSet
    {
        return new DocumentInheritanceDataSet();
    }

    protected function getFixtureBasePath(): string
    {
        return __DIR__ . '/../../../_fixtures/Shopware6/DocumentInheritance/';
    }
}
