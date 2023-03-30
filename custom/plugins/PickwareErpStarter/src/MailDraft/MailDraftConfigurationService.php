<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\MailDraft;

use Shopware\Core\System\SystemConfig\SystemConfigService;

class MailDraftConfigurationService
{
    private SystemConfigService $systemConfigService;

    public function __construct(SystemConfigService $systemConfigService)
    {
        $this->systemConfigService = $systemConfigService;
    }

    public function getDefaultSenderEmailAddress(): string
    {
        // See also Shopware\Core\Content\Mail\Service\MailService::getSender()
        $senderEmail = $this->systemConfigService->get('core.basicInformation.email');
        if ($senderEmail === null || trim($senderEmail) === '') {
            $senderEmail = $this->systemConfigService->get('core.mailerSettings.senderAddress');
        }

        return $senderEmail;
    }
}
