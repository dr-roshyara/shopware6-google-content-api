## 3.20.0

* Simplify API of `CriteriaJsonSerializer::deserializeFromArray`
  * Add constructor argument `EntityManager $entityManager` to `Pickware\DalBundle\CriteriaJsonSerializer`.


## 3.19.0

* Add method `Pickware\DalBundle\EntityIdResolver::resolveIdForStateMachine`.


## 3.18.1

* Update dependencies.


## 3.18.0

* Update function `Pickware\DalBundle\ExceptionHandling\UniqueIndexExceptionHandler::matchExpection()`, remove usage of second parameter (see feature flag `FEATURE_NEXT_16640`)
* Add function `Pickware\DalBundle\ContextFactory::createWithSource()` to create or copy contexts in a specific context source.


## 3.17.0

* Adds new service `Pickware\DalBundle\DatabaseBulkInsertService`.


## 3.16.1

* Update dependencies.


## 3.16.0

* Adds function `Pickware\DalBundle\EntityResponseService::makeEntityListingResponse` to create entity listing responses.
* Fix document creation for multiple languages with the same local code. Documents are now created with a language id rather than a locale code.
  * `Pickware\DalBundle\ContextFactory::createLocalizedContext()` now accepts a language id as parameter. The function still works with the old locale code parameter.


## 3.15.0

* Adds method `Pickware\DalBundle\EntityManager::findFirstBy()`.


## 3.13.3

* Remove usage of `maxLimit` from `Pickware\DalBundle\CriteriaJsonSerializer`.
* Fix tests for PHP 8.1.


## 3.13.1

* Update dependencies


## 3.13.0

* Adds new service `Pickware\DalBundle\EntityResponseService` that can be used to create JSON HTTP responses for entities.
* Fixes bundle registration in Shopware version 6.4.15.0 and above.


## 3.12.2

* Fixes compatibility to Shopware version 6.4.15.0.


## 3.12.1

**Requirements:**

* The bundle now requires at least Shopware version 6.4.5.0.


## 3.12.0

* Add new class `\Pickware\DalBundle\DefaultTranslationProvider`.
* Add new Argument `\Pickware\DalBundle\DefaultTranslationProvider` to `\Pickware\DalBundle\EntityManager`. Argument is
  optional now to be backwards compatible. Will be non-optional with the next major release.
* Add function `\Pickware\DalBundle\CriteriaFactory::makeCriteriaForEntitiesIdentifiedByIdWithAssociations`.

**Requirements:**

* The bundle now requires at least Shopware version 6.4.4.0.


## 3.11.1

* Add parameter type check to `findByPrimaryKey` in `Pickware\DalBundle\EntityManager`.


## 3.11.0

* Add new method `Pickware\ShopwarePlugins\DalBundle\EntityManager::getFirstBy()`.


## 3.10.4

* Internal refactoring and code style.


## 3.10.3

* Update dependencies


## 3.10.2

* Use correct class for default entity ids.


## 3.10.1

* Update code style.


## 3.10.0

* Add class `\Pickware\DalBundle\Field\FixedReferenceVersionField`.


## 3.9.2

* Add support for Composer 2.2.


## 3.9.1

* Run acceptance tests in dedicated database.


## 3.9.0

* Add method `findIdsBy()` to `Pickware\DalBundle\EntityManager`.


## 3.8.0

* Add new class `Pickware\DalBundle\EntityWrittenContainerEventExtension`.
* Add new class `Pickware\DalBundle\CriteriaFactory`.


## 3.7.1

* Fix creation of `languageIdChain` in method `Pickware\DalBundle\ContextFactory::createLocalizedContext`.


## 3.7.0

* Add method `getOneBy()` to `Pickware\DalBundle\EntityManager`.
* Add service `\Pickware\DalBundle\TechnicalNameToIdConverter`.
* Add service `\Pickware\DalBundle\EntityIdResolver`.
* Add function `runInTransactionWithRetry()` to `Pickware\DalBundle\EntityManager`. Deprecate function `transactional()`.
* Deprecates parameter `context` in `Pickware\DalBundle\CriteriaJsonSerializer::deserializeFromArray()`


## 3.6.0

* Deprecate classes `Pickware\DalBundle\AbstractEntityDeleteRestrictor` and `Pickware\DalBundle\AbstractEntityUpdateRestrictor`.
* Add new classes  `Pickware\DalBundle\EntityDeleteRestrictor`, `Pickware\DalBundle\EntityUpdateRestrictor` and `Pickware\DalBundle\EntityInsertRestrictor`.
* Add new class `Pickware\DalBundle\DalPayloadSerializer`.


## 3.5.0

* Add `getByPrimaryKey()` to `Pickware\DalBundle\EntityManager`.
* Add `Pickware\DalBundle\EntityManagerException`.


## 3.4.0

* Add `sanitizeCriteria()` to `Pickware\DalBundle\EntityManager`.
* Add new classes `Pickware\DalBundle\AbstractEntityDeleteRestrictor` and `Pickware\DalBundle\AbstractEntityUpdateRestrictor`.
* Fix `EnumFieldSerializer` compatibility with Shopware 6.4.3.0.


## 3.3.0

* Add `createLocalizedContext()` to `Pickware\DalBundle\ContextFactory`.


## 3.2.1

* Adjust composer version constraints to improve compatibility with Shopware 6.4.0.0 and above.


## 3.2.0

* Removed deprecations for version 3.0.0.
* Add `Pickware\DalBundle\RetryableTransaction` to use retryable transactions.


## 3.1.0

* Add new classes `Pickware\DalBundleCriteriaJsonSerializer` and `Pickware\DalBundle\ExceptionHandling\InvalidOffsetQueryException`.


## 3.0.0

* Removed module `ShopwarePlugins\DalBundle\Caching` to be compatible to Shopware 6.4.0.0 and above.
* Removed `QueryBuilderFactory`. `Shopware\Core\Framework\DataAbstractionLayer\Dbal\CriteriaQueryBuilder` is used instead.
* Update dependencies to be compatible to Shopware 6.4.0.0.
* Renamed `ShopwarePlugins\DalBundle` to `Pickware\DalBundle`.


## 2.7.0

* Add new class `\Pickware\ShopwarePlugins\DalBundle\EntityCollectionExtension`.


## 2.6.0

* Add mew module `ShopwarePlugins\DalBundle\Caching` with classes `NonCachingAssociationValidator`, `NonCachingEntityReaderDecorator` and `NonCachingEntitySearcherDecorator`.


## 2.5.0

* Add new class `Pickware\ShopwarePlugins\DalBundle\ContextFactory`.


## 2.4.0

* Add new module `ShopwarePlugins\DalBundle\ExceptionHandling` with classes `UniqueIndexExceptionHandler`, `UniqueIndexExceptionMapping` and `UniqueIndexHttpException`.


## 2.3.0

* Add support for Composer 2.2.


## 2.2.0

* Add new class `ShopwarePlugins\DalBundle\Sql\SqlUuid`


## 2.1.1

* Add support for Shopware 6.3.1.


## 2.1.0

* Add new method `Pickware\ShopwarePlugins\DalBundle\EntityManager::transactional()`
* Add new method `Pickware\ShopwarePlugins\DalBundle\EntityManager::lockPessimistically()`
* Add new method `Pickware\ShopwarePlugins\DalBundle\EntityManager::createCriteriaFromArray()`
* Drop support for Shopware 6.2.
* Add support for Shopware 6.3.


## 2.0.1

* Fix `JsonSerializableObjectFieldSerializer` not working with API.


## 2.0.0

* Technical: Change signature of Pickware\ShopwarePlugins\DalBundle\DalBundle::registerMigrations in a backwards-incompatible manner.
* Drop support for Shopware 6.1
* Add support for Shopware 6.2


## 1.1.0

* Method `Pickware\ShopwarePlugins\DalBundle\EntityManager::getEntityDefinition` is now public.
