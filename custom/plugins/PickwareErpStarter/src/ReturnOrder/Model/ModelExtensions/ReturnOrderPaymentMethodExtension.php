<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\ReturnOrder\Model\ModelExtensions;

use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderRefundDefinition;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class ReturnOrderPaymentMethodExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new OneToManyAssociationField(
                'pickwareErpReturnOrderRefunds',
                ReturnOrderRefundDefinition::class,
                'payment_method_id',
                'id',
            ))->addFlags(new RestrictDelete()),
        );
    }

    public function getDefinitionClass(): string
    {
        return PaymentMethodDefinition::class;
    }
}
