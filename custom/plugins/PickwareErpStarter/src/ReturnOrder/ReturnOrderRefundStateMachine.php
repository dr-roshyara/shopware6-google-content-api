<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\ReturnOrder;

use Pickware\InstallationLibrary\StateMachine\StateMachine;
use Pickware\InstallationLibrary\StateMachine\StateMachineState;

class ReturnOrderRefundStateMachine extends StateMachine
{
    public const TECHNICAL_NAME = 'pickware_erp_return_order_refund.state';

    public const STATE_REFUNDED = 'refunded';

    public function __construct()
    {
        $refunded = new StateMachineState(self::STATE_REFUNDED, [
            'de-DE' => 'Erstattet',
            'en-GB' => 'Refunded',
        ]);

        parent::__construct(
            self::TECHNICAL_NAME,
            [
                'de-DE' => 'Erstattung',
                'en-GB' => 'Refund',
            ],
            [$refunded],
            $refunded,
        );
    }
}
