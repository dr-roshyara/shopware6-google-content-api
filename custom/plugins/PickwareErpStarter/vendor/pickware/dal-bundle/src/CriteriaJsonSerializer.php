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

use InvalidArgumentException;
use Pickware\DalBundle\ExceptionHandling\InvalidOffsetQueryException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\AssociationNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidFilterQueryException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidLimitQueryException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidPageQueryException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSortQueryException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\SearchRequestException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Grouping\FieldGrouping;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\AggregationParser;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\QueryStringParser;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

class CriteriaJsonSerializer
{
    private RequestCriteriaBuilder $requestCriteriaBuilder;
    private AggregationParser $aggregationParser;
    private EntityManager $entityManager;

    /**
     * @deprecated Third argument $entityManager will be required and typed with EntityManager
     */
    public function __construct(
        RequestCriteriaBuilder $requestCriteriaBuilder,
        AggregationParser $aggregationParser,
        $entityManager
    ) {
        $this->requestCriteriaBuilder = $requestCriteriaBuilder;
        $this->aggregationParser = $aggregationParser;
        if ($entityManager instanceof EntityManager) {
            $this->entityManager = $entityManager;
        }
    }

    public function serializeToArray(Criteria $criteria): array
    {
        return $this->requestCriteriaBuilder->toArray($criteria);
    }

    /**
     * This is a copy of Shopware\Core\Framework\DataAbstractionLayer\Search::RequestCriteriaBuilder with different
     * arguments (context, apiVersion is missing)
     *
     * @param class-string<EntityDefinition> $entityDefinitionClassName
     */
    public function deserializeFromArray(array $payload, $entityDefinitionClassName): Criteria
    {
        /**
         * @deprecated tag:next-major. Only the listed arguments will be allowed
         */
        $arguments = func_get_args();
        if (count($arguments) < 2) {
            throw new InvalidArgumentException(
                sprintf('At least 2 parameters have to be passed to method %s', __METHOD__),
            );
        }
        array_shift($arguments); // First argument is already saved in $payload
        if ($arguments[0] instanceof Criteria) {
            trigger_error(
                sprintf('Passing a %s to method %s is deprecated', Criteria::class, __METHOD__),
                E_USER_DEPRECATED,
            );
            $criteria = array_shift($arguments);
        } else {
            $criteria = new Criteria();
        }
        $entityDefinition = array_shift($arguments);
        if (is_string($entityDefinition)) {
            $entityDefinition = $this->entityManager->getEntityDefinition($entityDefinition);
        } elseif (!($entityDefinition instanceof EntityDefinition)) {
            throw new InvalidArgumentException(sprintf(
                'Second parameter $entityDefinitionClassName of method %s has to be of type string.',
                __METHOD__,
            ));
        }
        if (count($arguments) !== 0) {
            trigger_error(
                sprintf('Passing a %s to method %s is deprecated.', Context::class, __METHOD__),
                E_USER_DEPRECATED,
            );
        }

        $searchException = new SearchRequestException();

        if (isset($payload['ids'])) {
            if (\is_string($payload['ids'])) {
                $ids = array_filter(explode('|', $payload['ids']));
            } else {
                $ids = $payload['ids'];
            }

            $criteria->setIds($ids);
            $criteria->setLimit(null);
        } else {
            if (isset($payload['total-count-mode'])) {
                $criteria->setTotalCountMode((int) $payload['total-count-mode']);
            }

            if (isset($payload['limit'])) {
                $this->addLimit($payload, $criteria, $searchException);
            }

            if (isset($payload['offset'])) {
                $this->addOffset($payload, $criteria, $searchException);
            }

            if (isset($payload['page'])) {
                $this->setPage($payload, $criteria, $searchException);
            }
        }

        if (isset($payload['includes'])) {
            $criteria->setIncludes($payload['includes']);
        }

        if (isset($payload['filter'])) {
            $this->addFilter($entityDefinition, $payload, $criteria, $searchException);
        }

        if (isset($payload['grouping'])) {
            foreach ($payload['grouping'] as $groupField) {
                $criteria->addGroupField(new FieldGrouping($groupField));
            }
        }

        if (isset($payload['post-filter'])) {
            $this->addPostFilter($entityDefinition, $payload, $criteria, $searchException);
        }

        if (isset($payload['query']) && \is_array($payload['query'])) {
            foreach ($payload['query'] as $query) {
                $parsedQuery = QueryStringParser::fromArray($entityDefinition, $query['query'], $searchException);
                $score = $query['score'] ?? 1;
                $scoreField = $query['scoreField'] ?? null;

                $criteria->addQuery(new ScoreQuery($parsedQuery, $score, $scoreField));
            }
        }

        if (isset($payload['term'])) {
            $term = trim((string) $payload['term']);
            $criteria->setTerm($term);
        }

        if (isset($payload['sort'])) {
            $this->addSorting($payload, $criteria, $entityDefinition, $searchException);
        }

        if (isset($payload['aggregations'])) {
            $this->aggregationParser->buildAggregations($entityDefinition, $payload, $criteria, $searchException);
        }

        if (isset($payload['associations'])) {
            foreach ($payload['associations'] as $propertyName => $association) {
                $field = $entityDefinition->getFields()->get($propertyName);

                if (!$field instanceof AssociationField) {
                    throw new AssociationNotFoundException($propertyName);
                }

                $ref = $field->getReferenceDefinition();
                if ($field instanceof ManyToManyAssociationField) {
                    $ref = $field->getToManyReferenceDefinition();
                }

                $nested = $criteria->getAssociation($propertyName);

                $this->deserializeFromArray($association, $nested, $ref);
            }
        }

        $searchException->tryToThrow();

        return $criteria;
    }

    private function setPage(array $payload, Criteria $criteria, SearchRequestException $searchRequestException): void
    {
        if ($payload['page'] === '') {
            $searchRequestException->add(new InvalidPageQueryException('(empty)'), '/page');

            return;
        }

        if (!is_numeric($payload['page'])) {
            $searchRequestException->add(new InvalidPageQueryException($payload['page']), '/page');

            return;
        }

        $page = (int) $payload['page'];
        $limit = (int) ($payload['limit'] ?? 0);

        if ($page <= 0) {
            $searchRequestException->add(new InvalidPageQueryException($page), '/page');

            return;
        }

        $offset = $limit * ($page - 1);
        $criteria->setOffset($offset);
    }

    private function addLimit(array $payload, Criteria $criteria, SearchRequestException $searchRequestException): void
    {
        if ($payload['limit'] === '') {
            $searchRequestException->add(new InvalidLimitQueryException('(empty)'), '/limit');

            return;
        }

        if (!is_numeric($payload['limit'])) {
            $searchRequestException->add(new InvalidLimitQueryException($payload['limit']), '/limit');

            return;
        }

        $limit = (int) $payload['limit'];
        if ($limit <= 0) {
            $searchRequestException->add(new InvalidLimitQueryException($limit), '/limit');

            return;
        }

        $criteria->setLimit($limit);
    }

    private function addOffset(array $payload, Criteria $criteria, SearchRequestException $searchRequestException): void
    {
        if ($payload['offset'] === '') {
            $searchRequestException->add(new InvalidOffsetQueryException('(empty)'), '/query');

            return;
        }

        if (!is_numeric($payload['offset'])) {
            $searchRequestException->add(new InvalidOffsetQueryException($payload['offset']), '/query');

            return;
        }

        $offset = (int) $payload['offset'];
        if ($offset <= 0) {
            $searchRequestException->add(new InvalidOffsetQueryException($offset), '/query');

            return;
        }

        $criteria->setOffset($offset);
    }

    private function addFilter(EntityDefinition $definition, array $payload, Criteria $criteria, SearchRequestException $searchException): void
    {
        if (!\is_array($payload['filter'])) {
            $searchException->add(new InvalidFilterQueryException('The filter parameter has to be a list of filters.'), '/filter');

            return;
        }

        if ($this->hasNumericIndex($payload['filter'])) {
            foreach ($payload['filter'] as $index => $query) {
                try {
                    $filter = QueryStringParser::fromArray($definition, $query, $searchException, '/filter/' . $index);
                    $criteria->addFilter($filter);
                } catch (InvalidFilterQueryException $ex) {
                    $searchException->add($ex, $ex->getPath());
                }
            }

            return;
        }

        $criteria->addFilter($this->parseSimpleFilter($definition, $payload['filter'], $searchException));
    }

    private function addPostFilter(EntityDefinition $definition, array $payload, Criteria $criteria, SearchRequestException $searchException): void
    {
        if (!\is_array($payload['post-filter'])) {
            $searchException->add(new InvalidFilterQueryException('The filter parameter has to be a list of filters.'), '/post-filter');

            return;
        }

        if ($this->hasNumericIndex($payload['post-filter'])) {
            foreach ($payload['post-filter'] as $index => $query) {
                try {
                    $filter = QueryStringParser::fromArray($definition, $query, $searchException, '/post-filter/' . $index);
                    $criteria->addPostFilter($filter);
                } catch (InvalidFilterQueryException $ex) {
                    $searchException->add($ex, $ex->getPath());
                }
            }

            return;
        }

        $criteria->addPostFilter(
            $this->parseSimpleFilter(
                $definition,
                $payload['post-filter'],
                $searchException,
            ),
        );
    }

    private function hasNumericIndex(array $data): bool
    {
        return array_keys($data) === range(0, \count($data) - 1);
    }

    private function addSorting(array $payload, Criteria $criteria, EntityDefinition $definition, SearchRequestException $searchException): void
    {
        if (\is_array($payload['sort'])) {
            $sorting = $this->parseSorting($definition, $payload['sort']);
            $criteria->addSorting(...$sorting);

            return;
        }

        try {
            $sorting = $this->parseSimpleSorting($definition, $payload['sort']);
            $criteria->addSorting(...$sorting);
        } catch (InvalidSortQueryException $ex) {
            $searchException->add($ex, '/sort');
        }
    }

    private function parseSorting(EntityDefinition $definition, array $sorting): array
    {
        $sortings = [];
        foreach ($sorting as $sort) {
            $order = $sort['order'] ?? 'asc';
            $naturalSorting = $sort['naturalSorting'] ?? false;

            if (strcasecmp($order, 'desc') === 0) {
                $order = FieldSorting::DESCENDING;
            } else {
                $order = FieldSorting::ASCENDING;
            }

            $sortings[] = new FieldSorting(
                $this->buildFieldName($definition, $sort['field']),
                $order,
                (bool) $naturalSorting,
            );
        }

        return $sortings;
    }

    private function parseSimpleSorting(EntityDefinition $definition, string $query): array
    {
        $parts = array_filter(explode(',', $query));

        if (empty($parts)) {
            throw new InvalidSortQueryException();
        }

        $sorting = [];
        foreach ($parts as $part) {
            $first = mb_substr($part, 0, 1);

            $direction = $first === '-' ? FieldSorting::DESCENDING : FieldSorting::ASCENDING;

            if ($direction === FieldSorting::DESCENDING) {
                $part = mb_substr($part, 1);
            }

            $sorting[] = new FieldSorting($this->buildFieldName($definition, $part), $direction);
        }

        return $sorting;
    }

    private function parseSimpleFilter(EntityDefinition $definition, array $filters, SearchRequestException $searchRequestException): MultiFilter
    {
        $queries = [];

        $index = -1;
        foreach ($filters as $field => $value) {
            ++$index;

            if ($field === '') {
                $searchRequestException->add(new InvalidFilterQueryException(sprintf('The key for filter at position "%s" must not be blank.', $index)), '/filter/' . $index);

                continue;
            }

            if ($value === '') {
                $searchRequestException->add(new InvalidFilterQueryException(sprintf('The value for filter "%s" must not be blank.', $field)), '/filter/' . $field);

                continue;
            }

            $queries[] = new EqualsFilter($this->buildFieldName($definition, $field), $value);
        }

        return new MultiFilter(MultiFilter::CONNECTION_AND, $queries);
    }

    private function buildFieldName(EntityDefinition $definition, string $fieldName): string
    {
        if ($fieldName === '_score') {
            // Do not prefix _score fields because they are not actual entity properties but a calculated field in the
            // SQL selection.
            return $fieldName;
        }

        $prefix = $definition->getEntityName() . '.';

        if (mb_strpos($fieldName, $prefix) === false) {
            return $prefix . $fieldName;
        }

        return $fieldName;
    }
}
