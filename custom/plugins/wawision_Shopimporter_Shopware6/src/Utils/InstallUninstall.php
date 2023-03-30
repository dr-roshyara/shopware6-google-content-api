<?php declare(strict_types=1);
/**
 * Shopware
 * Copyright © 2020
 *
 * @category   Shopware
 * @package    Shopimporter_Shopware6
 * @subpackage InstallUninstall.php
 *
 * @copyright  2020 Iguana-Labs GmbH
 * @author     Module Factory <info at module-factory.com>
 * @license    https://www.module-factory.com/eula
 */

namespace wawision\Shopimporter_Shopware6\Utils;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\ContainerInterface;

class InstallUninstall
{
    const SYSTEM_CONFIG_DOMAIN = 'WawisionShopimporter.config.';

    /** @var ContainerInterface */
    private $container;

    /** @var Connection */
    private $connection;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->connection = $this->container->get(Connection::class);
    }

    public function install(Context $context): void
    {
        $this->createXentralUser($context);
    }

    public function uninstall(Context $context): void
    {
        $this->removeConfiguration($context);
        $this->removeDatabaseTables();;
    }

    private function createXentralUser(Context $context): void
    {
        $this->connection->insert('user', [
            'id' => Uuid::randomBytes(),
            'locale_id' => $this->getLocaleOfSystemLanguage(),
            'username' => 'xentral-admin',
            'first_name'=> '',
            'last_name' => 'xentral-admin',
            'password' => password_hash(Uuid::randomHex(), PASSWORD_BCRYPT),
            'email' => 'info@email.com',
            'active' => true,
            'admin' => true,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_FORMAT),
        ]);
    }

    private function getLocaleOfSystemLanguage(): string
    {
        $builder = $this->connection->createQueryBuilder();

        return (string) $builder->select('locale.id')
            ->from('language', 'language')
            ->innerJoin('language', 'locale', 'locale', 'language.locale_id = locale.id')
            ->where('language.id = :id')
            ->setParameter('id', Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM))
            ->execute()
            ->fetchOne();
    }

    /**
     * @param Context $context
     * @throws \Exception
     */
    private function removeConfiguration(Context $context): void
    {
        /** @var EntityRepositoryInterface $systemConfigRepository */
        $systemConfigRepository = $this->container->get('system_config.repository');

        $criteria = (new Criteria())
            ->addFilter(new ContainsFilter('configurationKey', self::SYSTEM_CONFIG_DOMAIN));
        $idSearchResult = $systemConfigRepository->searchIds($criteria, $context);

        if (!$idSearchResult->getTotal()) {
            return;
        }

        $ids = array_map(static function ($id) {
            return ['id' => $id];
        }, $idSearchResult->getIds());

        $systemConfigRepository->delete($ids, $context);
    }

    private function removeDatabaseTables(): void
    {
        try {
            $this->connection->executeQuery("DELETE FROM `user` WHERE `username` = 'xentral-admin'");
        } catch (\Exception $e) { /* nothing */ }
    }

}
