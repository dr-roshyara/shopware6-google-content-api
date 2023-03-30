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

class ReturnOrderStateMachine extends StateMachine
{
    public const TECHNICAL_NAME = 'pickware_erp_return_order.state';

    public const STATE_COMPLETED = 'completed';
    public const STATE_DRAFT = 'draft';

    public const TRANSITION_COMPLETE = 'complete';

    public function __construct()
    {
        $completed = new StateMachineState(self::STATE_COMPLETED, [
            'de-DE' => 'Abgeschlossen',
            'en-GB' => 'Completed',
        ]);
        $draft = new StateMachineState(self::STATE_DRAFT, [
            'de-DE' => 'Entwurf',
            'en-GB' => 'Draft',
        ]);

        parent::__construct(
            self::TECHNICAL_NAME,
            [
                'de-DE' => 'Rückgabe',
                'en-GB' => 'Return',
            ],
            [
                $completed,
                $draft,
            ],
            $completed,
        );

        $this->addTransitionsFromAllStatesToState($completed, self::TRANSITION_COMPLETE);
    }
}
