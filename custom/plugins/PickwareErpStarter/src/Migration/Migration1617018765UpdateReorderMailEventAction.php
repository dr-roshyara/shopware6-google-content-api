<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Migration;

use Doctrine\DBAL\Connection;
use Pickware\PickwareErpStarter\Reorder\ReorderMailEvent;
use Pickware\PickwareErpStarter\Reorder\ReorderMailTemplate;
use Shopware\Core\Content\MailTemplate\MailTemplateActions;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1617018765UpdateReorderMailEventAction extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1617018765;
    }

    public function update(Connection $connection): void
    {
        $mailTemplateTypeResult = $connection->fetchAssociative(
            'SELECT * FROM `mail_template_type`
            WHERE `technical_name` = :technicalName',
            [
                'technicalName' => ReorderMailTemplate::TECHNICAL_NAME,
            ],
        );
        if (!$mailTemplateTypeResult) {
            // If the mail template type does not exist (i.e. this is a new plugin installation)
            return;
        }
        $mailTemplateTypeId = $mailTemplateTypeResult['id'];

        $mailTemplateResult = $connection->fetchAssociative(
            'SELECT * FROM `mail_template`
            WHERE `mail_template_type_id` = :mailTemplateTypeId',
            [
                'mailTemplateTypeId' => $mailTemplateTypeId,
            ],
        );
        if (!$mailTemplateResult) {
            // If the mail template does not exist (i.e. this is a new plugin installation)
            return;
        }
        $mailTemplateId = $mailTemplateResult['id'];

        $eventActionResult = $connection->fetchAssociative(
            'SELECT id FROM `event_action`
            WHERE `event_name` = :eventName
            AND `action_name`= :actionName',
            [
                'eventName' => ReorderMailEvent::EVENT_NAME,
                'actionName' => MailTemplateActions::MAIL_TEMPLATE_MAIL_SEND_ACTION,
            ],
        );
        $eventActionId = $eventActionResult ? $eventActionResult['id'] : false;

        $connection->executeStatement(
            'INSERT INTO `event_action` (
                `id`,
                `event_name`,
                `action_name`,
                `config`,
                `created_at`
            ) VALUES(
                :id,
                :eventName,
                :actionName,
                :config,
                NOW()
            ) ON DUPLICATE KEY UPDATE
                  event_name=VALUES(event_name),
                  action_name=VALUES(action_name),
                  config=VALUES(config)',
            [
                'id' => $eventActionId ?: Uuid::randomBytes(),
                'eventName' => ReorderMailEvent::EVENT_NAME,
                'actionName' => MailTemplateActions::MAIL_TEMPLATE_MAIL_SEND_ACTION,
                'config' => json_encode([
                    'mail_template_type_id' => Uuid::fromBytesToHex($mailTemplateTypeId),
                    'mail_template_id' => Uuid::fromBytesToHex($mailTemplateId),
                ]),
            ],
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
