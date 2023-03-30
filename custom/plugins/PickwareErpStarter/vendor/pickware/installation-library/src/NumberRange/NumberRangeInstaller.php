<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\InstallationLibrary\NumberRange;

use Doctrine\DBAL\Connection;
use InvalidArgumentException;
use Pickware\DalBundle\EntityManager;
use Pickware\InstallationLibrary\IdLookUpService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\NumberRange\Aggregate\NumberRangeType\NumberRangeTypeDefinition;
use Shopware\Core\System\NumberRange\Aggregate\NumberRangeType\NumberRangeTypeEntity;
use Shopware\Core\System\NumberRange\NumberRangeDefinition;
use Shopware\Core\System\NumberRange\NumberRangeEntity;

class NumberRangeInstaller
{
    private ?Connection $db = null;
    private ?EntityManager $entityManager = null;
    private ?IdLookUpService $idLookUpService = null;

    public function __construct($argument, ?IdLookUpService $idLookUpService = null)
    {
        /**
         * @deprecated tag:next-major First constructor argument will be changed to only accept an EntityManager as
         * argument. Second argument will be removed
         */
        if ($argument instanceof Connection) {
            $this->db = $argument;
            if (!$idLookUpService) {
                throw new InvalidArgumentException(
                    'When first argument is of type %s, the second argument must be provided and be of type %s.',
                    Connection::class,
                    IdLookUpService::class,
                );
            }
            $this->idLookUpService = $idLookUpService;
        } elseif ($argument instanceof EntityManager) {
            $this->entityManager = $argument;
        } else {
            throw new InvalidArgumentException(
                'First constructor argument must be of type %s or %s.',
                Connection::class,
                EntityManager::class,
            );
        }
    }

    /**
     * @param string $technicalName Matches number_range_type.technical_name
     * @param string $pattern Matches number_range.pattern (e.g. '{n}')
     * @param int $start Matches number_range.start (e.g. 1000)
     * @param array $translations Number range and number range type name for the locale codes de-DE and en-GB. E.g. [
     *   'de-DE' => 'Lieferanten',
     *   'en-GB' => 'Suppliers',
     * ]
     * @deprecated tag:3.0.0 Use self::ensureNumberRange instead
     */
    public function ensureNumberRangeExists(
        string $technicalName,
        string $pattern,
        int $start,
        array $translations
    ): void {
        $this->ensureNumberRange(new NumberRange($technicalName, $pattern, $start, $translations));
    }

    public function ensureNumberRange(NumberRange $numberRange): void
    {
        if ($this->entityManager) {
            $this->ensureNumberRangeWithEntityManager($numberRange);

            return;
        }

        if (!array_key_exists('de-DE', $numberRange->getTranslations())
            || !array_key_exists('en-GB', $numberRange->getTranslations())
        ) {
            throw new InvalidArgumentException(
                'Number range translations must support locale codes "de-DE" and "en-GB"',
            );
        }

        /** @var string $numberRangeTypeId */
        $numberRangeTypeId = $this->db->fetchOne(
            'SELECT `id` FROM `number_range_type` WHERE `technical_name` = :technicalName',
            ['technicalName' => $numberRange->getTechnicalName()],
        );

        if (!$numberRangeTypeId) {
            $numberRangeTypeId = Uuid::randomBytes();
            $this->db->executeStatement(
                'INSERT INTO `number_range_type` (
                `id`,
                `technical_name`,
                `global`,
                `created_at`
            ) VALUES (
                :id,
                :technicalName,
                1,
                NOW()
            ) ON DUPLICATE KEY UPDATE `id` = `id`',
                [
                    'id' => $numberRangeTypeId,
                    'technicalName' => $numberRange->getTechnicalName(),
                ],
            );
        }

        // Since number ranges are not unique per number range type, check for existing number ranges beforehand
        /** @var string $numberRangeId */
        $numberRangeId = $this->db->fetchOne(
            'SELECT `id` FROM `number_range` WHERE `type_id` = :numberRangeTypeId LIMIT 1',
            ['numberRangeTypeId' => $numberRangeTypeId],
        );
        if (!$numberRangeId) {
            $numberRangeId = Uuid::randomBytes();
            $this->db->executeStatement(
                'INSERT INTO `number_range` (
                    `id`,
                    `type_id`,
                    `global`,
                    `pattern`,
                    `start`,
                    `created_at`
                ) VALUES (
                    :id,
                    :numberRangeTypeId,
                    1,
                    :pattern,
                    :start,
                    NOW()
                )',
                [
                    'id' => $numberRangeId,
                    'numberRangeTypeId' => $numberRangeTypeId,
                    'pattern' => $numberRange->getPattern(),
                    'start' => $numberRange->getStart(),
                ],
            );
        }

        foreach ($numberRange->getTranslations() as $localeCode => $translatedName) {
            $languageId = $this->idLookUpService->lookUpLanguageIdForLocaleCode($localeCode);
            if (!$languageId) {
                continue;
            }

            $this->db->executeStatement(
                'INSERT INTO `number_range_type_translation` (
                    `number_range_type_id`,
                    `language_id`,
                    `type_name`,
                    `created_at`
                ) VALUES (
                    :numberRangeTypeId,
                    :languageId,
                    :translatedName,
                    NOW(3)
                ) ON DUPLICATE KEY UPDATE `number_range_type_id` = `number_range_type_id`',
                [
                    'numberRangeTypeId' => $numberRangeTypeId,
                    'languageId' => $languageId,
                    'translatedName' => $translatedName,
                ],
            );
            $this->db->executeStatement(
                'INSERT INTO `number_range_translation` (
                    `number_range_id`,
                    `language_id`,
                    `name`,
                    `created_at`
                ) VALUES (
                    :numberRangeId,
                    :languageId,
                    :translatedName,
                    NOW(3)
                ) ON DUPLICATE KEY UPDATE `number_range_id` = `number_range_id`',
                [
                    'numberRangeId' => $numberRangeId,
                    'languageId' => $languageId,
                    'translatedName' => $translatedName,
                ],
            );
        }
    }

    private function ensureNumberRangeWithEntityManager(NumberRange $numberRange): void
    {
        /** @var NumberRangeTypeEntity|null $existingNumberRange */
        $existingNumberRangeType = $this->entityManager->findOneBy(
            NumberRangeTypeDefinition::class,
            ['technicalName' => $numberRange->getTechnicalName()],
            Context::createDefaultContext(),
        );
        if (!$existingNumberRangeType) {
            $numberRangeTypeId = Uuid::randomHex();
            $this->entityManager->upsert(
                NumberRangeTypeDefinition::class,
                [
                    [
                        'id' => $numberRangeTypeId,
                        'technicalName' => $numberRange->getTechnicalName(),
                        'typeName' => $numberRange->getTypeNameTranslations(),
                        'global' => true,
                    ],
                ],
                Context::createDefaultContext(),
            );
        } else {
            $numberRangeTypeId = $existingNumberRangeType->getId();
        }

        /** @var NumberRangeEntity|null $existingNumberRange */
        $existingNumberRange = $this->entityManager->findBy(
            NumberRangeDefinition::class,
            ['typeId' => $numberRangeTypeId],
            Context::createDefaultContext(),
        )->first();
        if (!$existingNumberRange) {
            $this->entityManager->upsert(
                NumberRangeDefinition::class,
                [
                    [
                        'id' => Uuid::randomHex(),
                        'typeId' => $numberRangeTypeId,
                        'name' => $numberRange->getTypeNameTranslations(),
                        'pattern' => $numberRange->getPattern(),
                        'start' => $numberRange->getStart(),
                        'global' => true,
                    ],
                ],
                Context::createDefaultContext(),
            );
        }
    }
}
