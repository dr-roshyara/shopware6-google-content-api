## 2.2.0s

* Also log stack traces of exceptions thrown in a request from the WMS App.


## 2.1.10

* Fixes Bundle initialization during container reboot.
* Avoid redeclaration of the global PickwareDebugging method.
* Fix tests for PHP 8.1.


## 2.1.8

* Fixes bundle registration in Shopware version 6.4.15.0 and above.


## 2.1.7

**Requirements:**

* The bundle now requires at least Shopware version 6.4.5.0.


## 2.1.6

**Requirements:**

* The bundle now requires at least Shopware version 6.4.4.0.


## 2.1.5

* Update composer configuration


## 2.1.4

* Fix user agent header recognition when the user agent header is missing.


## 2.1.3

* Update code style.


## 2.1.2

* Add support for Composer 2.2.


## 2.1.1

* Run acceptance tests in dedicated database.


## 2.1.0

* Add debug subscriber


## 2.0.6

* Correct documentation after class rename and release to facilitate release script.


## 2.0.5

* Adjust composer version constraints to improve compatibility with Shopware 6.4.0.0 and above.


## 2.0.4

* Removed deprecations for version 2.0.0.


## 2.0.3

* Remove unnecessary dependency.


## 2.0.2

* Rename global method `Pickware` to `PickwareDebugging` to avoid naming collisions with old versions of the package.


## 2.0.1

* Fix problem with duplicated definition of `Pickware` global method.


## 2.0.0

* Update dependencies to be compatible to Shopware 6.4.0.0.
* Rename `Pickware\ShopwarePlugins\DebugBundle` to `Pickware\DebugBundle`


## 1.2.1

* Fix migration execution order.


## 1.2.0

* Adds `DebugBundle\SqlLockLogger`.


## 1.1.0

* Add support for Composer 2.


## 1.0.1

* Support for Shopware has been extended to versions 6.3.*.


## 1.0.0

* Initial release
