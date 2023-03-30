<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SwagMigrationAssistant\Profile\Shopware6\Converter;

use SwagMigrationAssistant\Migration\Converter\ConvertStruct;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;

abstract class MediaConverter extends ShopwareMediaConverter
{
    public function getMediaUuids(array $converted): ?array
    {
        return \array_column($converted, 'id');
    }

    protected function convertData(array $data): ConvertStruct
    {
        $converted = $data;

        $this->mainMapping = $this->getOrCreateMappingMainCompleteFacade(
            DefaultEntities::MEDIA,
            $data['id'],
            $converted['id']
        );

        $this->updateMediaAssociation($converted);

        return new ConvertStruct($converted, null, $this->mainMapping['id'] ?? null);
    }
}
