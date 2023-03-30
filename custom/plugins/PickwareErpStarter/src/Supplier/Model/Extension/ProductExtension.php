<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Supplier\Model\Extension;

use Pickware\PickwareErpStarter\Supplier\Model\ProductSupplierConfigurationDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class ProductExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        // Even though we intend to delete all product supplier configurations when the respective product is deleted,
        // we cannot use the CascadeDelete flag here. We restrict the deletion of product supplier configuration via the
        // DAL in general (see ProductSupplierConfigurationDeleteRestrictor.php) which unfortunately includes the DAL
        // CascadeDelete function.
        // We therefore rely on the CASCADE DELETE in the database to clear product supplier configurations of deleted
        // products.
        $collection->add(
            new OneToOneAssociationField(
                'pickwareErpProductSupplierConfiguration',
                'id',
                'product_id',
                ProductSupplierConfigurationDefinition::class,
                false,
            ),
        );
    }

    public function getDefinitionClass(): string
    {
        return ProductDefinition::class;
    }
}
