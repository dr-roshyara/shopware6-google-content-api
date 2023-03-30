<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DalBundle;

use Doctrine\DBAL\Connection;
use InvalidArgumentException;
use LogicException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\CriteriaQueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Symfony\Component\DependencyInjection\ContainerInterface;

class EntityManager
{
    private ContainerInterface $container;
    private Connection $db;
    private ?CriteriaQueryBuilder $criteriaQueryBuilder;
    private DefaultTranslationProvider $defaultTranslationProvider;

    public function __construct(
        ContainerInterface $container,
        Connection $db,
        ?CriteriaQueryBuilder $criteriaQueryBuilder,
        ?DefaultTranslationProvider $defaultTranslationProvider = null
    ) {
        $this->container = $container;
        $this->db = $db;
        /**
         * @deprecated tag:next-major the optional argument ?CriteriaQueryBuilder $criteriaQueryBuilder will be placed
         * last. So the arguments will be as follows:
         *   ContainerInterface $container,
         *   Connection $db,
         *   DefaultTranslationProvider $defaultTranslationProvider,
         *   ?CriteriaQueryBuilder $criteriaQueryBuilder
         */
        $this->criteriaQueryBuilder = $criteriaQueryBuilder;

        /**
         * @deprecated tag:next-major DefaultTranslationProvider is optional to keep the constructor backwards
         * compatible. Will be non-optional with the next major release.
         */
        if (!$defaultTranslationProvider) {
            $this->defaultTranslationProvider = new DefaultTranslationProvider($this->container, $this->db);
        } else {
            $this->defaultTranslationProvider = $defaultTranslationProvider;
        }
    }

    /**
     * @param class-string<EntityDefinition> $entityDefinitionClassName
     * @param array|Criteria $criteria
     */
    public function findIdsBy(string $entityDefinitionClassName, $criteria, Context $context): array
    {
        if (is_array($criteria)) {
            $criteria = self::createCriteriaFromArray($criteria);
        } elseif (!($criteria instanceof Criteria)) {
            throw new InvalidArgumentException(sprintf(
                'Parameter $criteria must be instance of %s or array.',
                Criteria::class,
            ));
        }

        $repository = $this->getRepository($entityDefinitionClassName);

        return $repository->searchIds($criteria, $context)->getIds();
    }

    /**
     * @param class-string<EntityDefinition> $entityDefinitionClassName
     * @param array|string|int $primaryKey
     */
    public function findByPrimaryKey(
        string $entityDefinitionClassName,
        $primaryKey,
        Context $context,
        array $associations = []
    ): ?Entity {
        if (!is_array($primaryKey) && !is_string($primaryKey) && !is_int($primaryKey)) {
            throw new InvalidArgumentException(sprintf(
                'Type %s is not allowed for parameter $primaryKey.',
                gettype($primaryKey),
            ));
        }
        $repository = $this->getRepository($entityDefinitionClassName);
        $criteria = new Criteria([$primaryKey]);
        if (count($associations) !== 0) {
            $criteria->addAssociations($associations);
        }

        $result = $repository->search($criteria, $context);

        if ($result->count() > 1) {
            throw DataAbstractionLayerException::moreThanOneEntityInResultSet(__METHOD__);
        }

        return $result->first();
    }

    /**
     * @param class-string<EntityDefinition> $entityDefinitionClassName
     * @param array|string|int $primaryKey
     * @throws EntityManagerException when the entity was not found
     */
    public function getByPrimaryKey(
        string $entityDefinitionClassName,
        $primaryKey,
        Context $context,
        array $associations = []
    ): Entity {
        $entity = $this->findByPrimaryKey($entityDefinitionClassName, $primaryKey, $context, $associations);
        if (!$entity) {
            throw EntityManagerException::entityWithPrimaryKeyNotFound($entityDefinitionClassName, $primaryKey);
        }

        return $entity;
    }

    /**
     * @param class-string<EntityDefinition> $entityDefinitionClassName
     * @param array|Criteria $criteria
     */
    public function findBy(
        string $entityDefinitionClassName,
        $criteria,
        Context $context,
        array $associations = []
    ): EntityCollection {
        if (is_array($criteria)) {
            $criteria = self::createCriteriaFromArray($criteria);
        } elseif (!($criteria instanceof Criteria)) {
            throw new InvalidArgumentException(sprintf(
                'Parameter $criteria must be instance of %s or array.',
                Criteria::class,
            ));
        }

        if (count($associations) !== 0) {
            $criteria->addAssociations($associations);
        }

        $repository = $this->getRepository($entityDefinitionClassName);
        $searchResult = $repository->search($criteria, $context);

        $collectionClassName = $this->getEntityDefinition($entityDefinitionClassName)->getCollectionClass();

        return new $collectionClassName($searchResult->getElements());
    }

    /**
     * @param class-string<EntityDefinition> $entityDefinitionClassName
     * @param array|Criteria $criteria
     */
    public function findOneBy(
        string $entityDefinitionClassName,
        $criteria,
        Context $context,
        array $associations = []
    ): ?Entity {
        $result = $this->findBy($entityDefinitionClassName, $criteria, $context, $associations);

        if ($result->count() > 1) {
            throw DataAbstractionLayerException::moreThanOneEntityInResultSet(__METHOD__);
        }

        return $result->first();
    }

    /**
     * Same as findOneBy but throws an exception when no entity was returned.
     * @param class-string<EntityDefinition> $entityDefinitionClassName
     * @param array|Criteria $criteria
     * @throws EntityManagerException when the entity was not found
     */
    public function getOneBy(
        string $entityDefinitionClassName,
        $criteria,
        Context $context,
        array $associations = []
    ): Entity {
        $entity = $this->findOneBy($entityDefinitionClassName, $criteria, $context, $associations);
        if (!$entity) {
            throw EntityManagerException::entityWithCriteriaNotFound($entityDefinitionClassName, $criteria);
        }

        return $entity;
    }

    /**
     * Returns the first entity of the result. Throws an exception if no entity was found at all. Be sure to use a
     * sorting in the criteria to get deterministic results.
     *
     * @param class-string<EntityDefinition> $entityDefinitionClassName
     */
    public function getFirstBy(
        string $entityDefinitionClassName,
        Criteria $criteria,
        Context $context,
        array $associations = []
    ): Entity {
        $criteria->setLimit(1);

        return $this->getOneBy($entityDefinitionClassName, $criteria, $context, $associations);
    }

    /**
     * Returns the first entity of the result.
     *
     * @param FieldSorting[]|FieldSorting $sorting
     * @param array|Criteria|null $criteria
     * @param class-string<EntityDefinition> $entityDefinitionClassName
     */
    public function findFirstBy(
        string $entityDefinitionClassName,
        $sorting,
        Context $context,
        $criteria = null,
        array $associations = []
    ): ?Entity {
        if (is_array($criteria)) {
            $criteria = self::createCriteriaFromArray($criteria);
        } elseif ($criteria && !($criteria instanceof Criteria)) {
            throw new InvalidArgumentException(sprintf(
                'Parameter $criteria must be instance of %s or array.',
                Criteria::class,
            ));
        } elseif (!$criteria) {
            $criteria = new Criteria();
        }

        if ($sorting && is_array($sorting)) {
            $criteria->addSorting(...$sorting);
        } elseif ($sorting instanceof FieldSorting) {
            $criteria->addSorting($sorting);
        } else {
            throw new InvalidArgumentException(sprintf(
                'Parameter $sorting must be %s or array of %s.',
                FieldSorting::class,
                FieldSorting::class,
            ));
        }

        $criteria->setOffset(0);
        $criteria->setLimit(1);

        return $this->findBy($entityDefinitionClassName, $criteria, $context, $associations)->first();
    }

    /**
     * @param class-string<EntityDefinition> $entityDefinitionClassName
     */
    public function findAll(
        string $entityDefinitionClassName,
        Context $context,
        array $associations = []
    ): EntityCollection {
        return $this->findBy($entityDefinitionClassName, [], $context, $associations);
    }

    /**
     * @param class-string<EntityDefinition> $entityDefinitionClassName
     */
    public function create(
        string $entityDefinitionClassName,
        array $payload,
        Context $context
    ): EntityWrittenContainerEvent {
        if (count($payload) === 0) {
            return EntityWrittenContainerEvent::createWithWrittenEvents([], $context, []);
        }
        $this->defaultTranslationProvider->ensureSystemDefaultTranslationInEntityWritePayload(
            $entityDefinitionClassName,
            $payload,
        );

        return $this->getRepository($entityDefinitionClassName)->create($payload, $context);
    }

    /**
     * Creates all entities from $payload that do not exist yet.
     *
     * If the entities exist already, they stay untouched. The primary key is used to decide whether the entities already
     * exist.
     *
     * @param class-string<EntityDefinition> $entityDefinitionClassName
     */
    public function createIfNotExists(
        string $entityDefinitionClassName,
        array $payload,
        Context $context
    ): EntityWrittenContainerEvent {
        $primaryKeyFields = $this->getEntityDefinition($entityDefinitionClassName)->getPrimaryKeys();
        if ($primaryKeyFields->count() !== 1) {
            throw new LogicException('Entities with multiple primary key fields are not supported yet');
        }
        $primaryKeyName = $primaryKeyFields->first()->getPropertyName();

        $existingEntities = $this->findBy(
            $entityDefinitionClassName,
            [$primaryKeyName => array_map(fn(array $entity) => $entity[$primaryKeyName], $payload)],
            $context,
        );
        $existingEntityIds = EntityCollectionExtension::getField($existingEntities, $primaryKeyName);
        $entitiesToCreate = array_values(array_filter(
            $payload,
            fn(array $entity) => !in_array($entity[$primaryKeyName], $existingEntityIds, true),
        ));

        return $this->create($entityDefinitionClassName, $entitiesToCreate, $context);
    }

    /**
     * @param class-string<EntityDefinition> $entityDefinitionClassName
     */
    public function upsert(
        string $entityDefinitionClassName,
        array $payload,
        Context $context
    ): EntityWrittenContainerEvent {
        if (count($payload) === 0) {
            return EntityWrittenContainerEvent::createWithWrittenEvents([], $context, []);
        }
        $this->defaultTranslationProvider->ensureSystemDefaultTranslationInEntityWritePayload(
            $entityDefinitionClassName,
            $payload,
        );

        return $this->getRepository($entityDefinitionClassName)->upsert($payload, $context);
    }

    /**
     * @param class-string<EntityDefinition> $entityDefinitionClassName
     */
    public function update(
        string $entityDefinitionClassName,
        array $payload,
        Context $context
    ): EntityWrittenContainerEvent {
        if (count($payload) === 0) {
            return EntityWrittenContainerEvent::createWithWrittenEvents([], $context, []);
        }
        $this->defaultTranslationProvider->ensureSystemDefaultTranslationInEntityWritePayload(
            $entityDefinitionClassName,
            $payload,
        );

        return $this->getRepository($entityDefinitionClassName)->update($payload, $context);
    }

    /**
     * @param class-string<EntityDefinition> $entityDefinitionClassName
     */
    public function delete(string $entityDefinitionClassName, array $ids, Context $context): EntityWrittenContainerEvent
    {
        if (count($ids) === 0) {
            return EntityWrittenContainerEvent::createWithDeletedEvents([], $context, []);
        }

        $ids = array_values($ids);

        // Convert the $ids to an array of associative arrays if not passed as such
        if (!is_array($ids[0])) {
            $entityDefinition = $this->getEntityDefinition($entityDefinitionClassName);
            $primaryKeyFields = $entityDefinition->getPrimaryKeys()->filter(fn (Field $field) => !($field instanceof VersionField));
            $primaryKey = $primaryKeyFields->first();
            $ids = array_map(function ($id) use ($primaryKey) {
                return [
                    $primaryKey->getPropertyName() => $id,
                ];
            }, $ids);
        }

        return $this->getRepository($entityDefinitionClassName)->delete($ids, $context);
    }

    /**
     * @param class-string<EntityDefinition> $entityDefinitionClassName
     * @param array|Criteria $criteria
     */
    public function deleteByCriteria(
        string $entityDefinitionClassName,
        $criteria,
        Context $context
    ): EntityWrittenContainerEvent {
        $entitiesToDelete = $this->findBy($entityDefinitionClassName, $criteria, $context);
        $primaryKeyFields = $this->getEntityDefinition($entityDefinitionClassName)->getPrimaryKeys();
        $deletePayload = [];
        foreach ($entitiesToDelete as $entityToDelete) {
            $payload = [];
            foreach ($primaryKeyFields as $primaryKeyField) {
                $propertyName = $primaryKeyField->getPropertyName();
                $payload[$propertyName] = $entityToDelete->get($propertyName);
            }
            $deletePayload[] = $payload;
        }

        return $this->delete($entityDefinitionClassName, $deletePayload, $context);
    }

    /**
     * @param class-string<EntityDefinition> $entityDefinitionClassName
     * @return string the UUID of the newly created version
     */
    public function createVersion(string $entityDefinitionClassName, $primaryKey, Context $context): string
    {
        return $this->getRepository($entityDefinitionClassName)->createVersion($primaryKey, $context);
    }

    /**
     * @param class-string<EntityDefinition> $entityDefinitionClassName
     */
    public function getRepository(string $entityDefinitionClassName): EntityRepositoryInterface
    {
        $entityName = $this->getEntityDefinition($entityDefinitionClassName)->getEntityName();

        return $this->container->get(sprintf('%s.repository', $entityName));
    }

    /**
     * @template T of EntityDefinition
     * @param class-string<T> $entityDefinitionClassName
     * @return T
     */
    public function getEntityDefinition(string $entityDefinitionClassName): EntityDefinition
    {
        /** @var EntityDefinition $entityDefinition */
        $entityDefinition = $this->container->get($entityDefinitionClassName);

        return $entityDefinition;
    }

    /**
     * @param class-string<EntityDefinition> $entityDefinitionClassName
     * @param Criteria|array $criteria
     * @throws DataAbstractionLayerException when not in transaction
     */
    public function lockPessimistically(string $entityDefinitionClassName, $criteria, Context $context): void
    {
        if (!$this->criteriaQueryBuilder) {
            throw new LogicException(sprintf(
                'This instance of %s was created without an %s therefore this method is unavailable.',
                self::class,
                CriteriaQueryBuilder::class,
            ));
        }

        if (!$this->db->isTransactionActive()) {
            // Pessimistic locking can happen in transactions exclusively
            throw DataAbstractionLayerException::transactionNecessaryForPessimisticLocking();
        }

        // Convert criteria array to Criteria object
        if (is_array($criteria)) {
            $criteria = self::createCriteriaFromArray($criteria);
        } elseif (!($criteria instanceof Criteria)) {
            throw new InvalidArgumentException(sprintf(
                'Parameter $criteria must be instance of %s or array.',
                Criteria::class,
            ));
        }

        // Create queryBuilder for Criteria object
        $entityDefinition = $this->getEntityDefinition($entityDefinitionClassName);
        $queryBuilder = $this->criteriaQueryBuilder->build(
            new QueryBuilder($this->db),
            $entityDefinition,
            $criteria,
            $context,
        );
        $queryBuilder->addSelect($this->db->quoteIdentifier(sprintf('%s.id', $entityDefinition->getEntityName())));

        // Execute locking SQL
        $sql = $queryBuilder->getSQL() . ' ' . $this->db->getDatabasePlatform()->getWriteLockSQL();
        $this->db->executeStatement($sql, $queryBuilder->getParameters(), $queryBuilder->getParameterTypes());
    }

    public function runInTransactionWithRetry(callable $callback)
    {
        return RetryableTransaction::retryable($this->db, $callback);
    }

    /**
     * @deprecated Use runInTransactionWithRetry() instead. Will be removed with 4.0.0.
     */
    public function transactional(Context $context, callable $callback)
    {
        return $this->db->transactional(fn () => $callback($this, $context));
    }

    public static function createCriteriaFromArray(array $array): Criteria
    {
        $criteria = new Criteria();
        foreach ($array as $field => $criterion) {
            if (is_array($criterion)) {
                $criteria->addFilter(new EqualsAnyFilter($field, $criterion));
            } else {
                $criteria->addFilter(new EqualsFilter($field, $criterion));
            }
        }

        return $criteria;
    }

    /**
     * Creates a new Criteria object with filter, sorting, limit and offset of the given criteria (i.e. associations and
     * other settings are ignored).
     */
    public static function sanitizeCriteria(Criteria $criteria): Criteria
    {
        $sanitizedCriteria = new Criteria();
        $sanitizedCriteria->addFilter(...$criteria->getFilters());
        $sanitizedCriteria->addSorting(...$criteria->getSorting());
        $sanitizedCriteria->setLimit($criteria->getLimit());
        $sanitizedCriteria->setOffset($criteria->getOffset());

        return $sanitizedCriteria;
    }
}
