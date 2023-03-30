## 1.12.1

* Class `PickwareValidationEvent` has been re-added and deprecated.

## 1.12.0

* Class `PickwareValidationEvent` has been removed.
* Class `PickwareValidationViolation` has been deprecated.

## 1.11.1

* Update dependencies.


## 1.11.0

* Add class `Pickware\ShopwareExtensionsBundle\Context\ContextExtension`.


## 1.10.1

* Update dependencies.


## 1.10.0

* The `PickwareValidationViolation` now extends `Exception`.
* New extension `OrderTransactionCollectionExtension`.
* New extension `OrderDeliveryCollectionExtension`.
* `PickwareOrderTransactionCollection` has been deprecated.
* `PickwareOrderDeliveryCollection` has been deprecated.


## 1.8.2

* The `OrderConfigurationUpdater.php` event subscriber now runs with a higher priority, so it is executed before other subscribers.


## 1.8.1

* Update dependencies.


## 1.8.0

* Add class `Pickware\ShopwareExtensionsBundle\Event\PickwareValidationViolation.php`.
* Deprecated method `Pickware\ShopwareExtensionsBundle\Event\PickwareValidationEvent::addError()`, use new method `Pickware\ShopwareExtensionsBundle\Event\PickwareValidationEvent::addViolation()` instead.
* Fix tests for PHP 8.1.


## 1.7.2

* Change `Pickware\ShopwareExtensionsBundle\Event\PickwareValidationEvent::addError()` argument to `JsonApiErrorSerializable`.


## 1.7.1

* Scope `Pickware\ShopwareExtensionsBundle\Mail\MailSendSuppressionService.php` per request.


## 1.7.0

* Add `PickwareValidationEvent` for easier cross-plugin validation
* Fixes bundle registration in Shopware version 6.4.15.0 and above.


## 1.6.9

* Fix backwards compatibility.


## 1.6.8

* Update dependencies.


## 1.6.7

* Removes migrations from the migrations table when the bundle is uninstalled.


## 1.6.6

* Fix (add) order versioning for OrderConfiguration entities.


## 1.6.5

* Revert deprecations of 1.6.1.


## 1.6.4

* Start `OrderConfigurationIndexer` when the bundle is activated.


## 1.6.3

* Fix bug in SQL query in `OrderConfigurationUpdater::updatePrimaryOrderDeliveries()` that different types where used for comparison.


## 1.6.2

* Fix tests for ci and shopware 6.4.14.0.


## 1.6.1

* Deprecate usage of classes
    * `Pickware\ShopwareExtensionsBundle\OrderDelivery\PickwareOrderDeliveryCollection.php`
    * `Pickware\ShopwareExtensionsBundle\OrderTransaction\PickwareOrderTransactionCollection.php`


## 1.6.0

* Add order extension model `Pickware\ShopwareExtensionsBundle\OrderConfiguration\Model\OrderConfigurationDefinition.php`.
* Add `Pickware\ShopwareExtensionsBundle\OrderConfiguration\OrderConfigurationIndexer.php` and `Pickware\ShopwareExtensionsBundle\OrderConfiguration\OrderConfigurationUpdater.php` to keep track of primary order delivery and primary order transaction.


## 1.5.0

* Add class `Pickware\ShopwareExtensionsBundle\Mail\MailSendSuppressionService.php`.

**Requirements:**

* The bundle now requires at least Shopware version 6.4.5.0.


## 1.4.0

* Add class `Pickware\ShopwareExtensionsBundle\OrderDocument\OrderDocumentService`.
* Add `StateTransitioning` module with classes
  * `Pickware\ShopwareExtensionsBundle\StateTransitioning\StateTransitionService`
  * `Pickware\ShopwareExtensionsBundle\StateTransitioning\ShortestPathCalculation\Dijkstra`

**Requirements:**

* The bundle now requires at least Shopware version 6.4.4.0.


## 1.3.3

* Update composer configuration


## 1.3.2

* Update dependencies.


## 1.3.1

* Internal refactoring and code style.


## 1.3.0

* Add classes
  * `Pickware\ShopwareExtensionsBundle\Price\CalculatedPriceExtension`
  * `Pickware\ShopwareExtensionsBundle\Price\CalculatedTaxCollectionExtension`
  * `Pickware\ShopwareExtensionsBundle\Price\TaxRuleCollectionExtension`
  * `Pickware\ShopwareExtensionsBundle\Struct\CollectionExtension`


## 1.2.3

* Update dependencies.


## 1.2.2

* Update code style.


## 1.2.1

* Update dependencies.


## 1.2.0

* Add new `OrderTransactionCollection`.


## 1.1.4

* Add support for Composer 2.2.


## 1.1.3

* Update dependencies.


## 1.1.2

* Update dependencies.


## 1.1.1

* Update dependencies.


## 1.1.0

* Add new `OrderDelivery` module with `OrderDeliveryController` and `OrderDeliveryService`.


## 1.0.0

* Initial release
