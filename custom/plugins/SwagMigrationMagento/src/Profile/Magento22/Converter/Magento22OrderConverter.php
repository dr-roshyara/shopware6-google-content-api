<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento22\Converter;

use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\OrderDataSet;
use Swag\MigrationMagento\Profile\Magento2\Converter\Magento2OrderConverter;
use Swag\MigrationMagento\Profile\Magento22\Magento22Profile;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

class Magento22OrderConverter extends Magento2OrderConverter
{
    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof Magento22Profile
             && $this->getDataSetEntity($migrationContext) === OrderDataSet::getEntity();
    }
}
