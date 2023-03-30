## 3.3.0

* Restructure the dependency exclude list and add conditional exclude/include by plugin name.


## 3.2.6

* Update the dependency exclude list.


## 3.2.5

* Update composer configuration


## 3.2.4

* Internal refactoring and code style.


## 3.2.3

* Update code style.


## 3.2.2

* Add support for Composer 2.2.


## 3.2.1

* Update `composer.json` `extra` parameter.


## 3.2.0

* Add `aws/aws-crt-php` to `dependency-exclude-list.yaml`


## 3.1.0

* Do not ship dependencies of type `shopware-platform-plugin` with packages.


## 3.0.4

* Update dependencies and require php8 polyfill dependency before generating `Packages.php`.


## 3.0.3

* Adjust composer version constraints to improve compatibility with Shopware 6.4.0.0 and above.


## 3.0.2

* Update composer.json to remove lock file via config.


## 3.0.1

* Remove unnecessary dependency.


## 3.0.0

* Rename `Pickware\ShopwarePlugins\DependencyLoader` to `Pickware\DependencyLoader`


## 2.3.1

* Update phpunit.unit.xml


## 2.3.0

* Add support for Composer 2.


## 2.2.0

* Add support for Shopware 6.3.*.


## 2.1.2

* Fixed a possible problem with loading plugins in Shopware because the source files of composer plugins were stripped.


## 2.1.1

* Add support for Shopware 6.3.1.
* Drop support for Shopware 6.3.0.
* Marked the package `laminas/laminas-zendframework-bridge` as incompatible.


## 2.1.0

* Drop support for Shopware 6.2
* Add support for Shopware 6.3.0


## 2.0.1

* Renamed blacklist to exclude-list.


## 2.0.0

* Drop support for Shopware 6.1
* Add support for Shopware 6.2


## 1.0.1

* Fixes possible crash of Shop after Plugin was deleted via Plugin-Manager
