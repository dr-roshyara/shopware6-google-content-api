## 2.4.14

* Sort the merged documents in `_action/pickware-merge-documents` by the order of the document deeplink codes in the request argument.


## 2.4.13

* Update dependencies.


## 2.4.12

* Update dependencies.


## 2.4.11

* Internal refactoring.


## 2.4.10

* Fix document creation for multiple languages with the same locale code. Documents are now created with a language id rather than a locale code.


## 2.4.9

* Update dependencies.


## 2.4.8

* Update dependencies.


## 2.4.7

* Fix tests for PHP 8.1.


## 2.4.6

* Update dependencies.


## 2.4.5

* Update dependencies.


## 2.4.4

* Fixes bundle registration in Shopware version 6.4.15.0 and above.


## 2.4.3

* Update dependencies.


## 2.4.2

* Update dependencies.


## 2.4.1

**Requirements:**

* The bundle now requires at least Shopware version 6.4.5.0.


## 2.4.0

* Remove unused document card for SW Administration.

**Requirements:**

* The bundle now requires at least Shopware version 6.4.4.0.


## 2.3.0

* Add document card for SW Administration.


## 2.2.10

* Update dependencies.


## 2.2.9

* When encountering multiple snippet sets for the same ISO during translation the oldest snippet set is selected.
* Change API response to adhere to the JsonApi standard.


## 2.2.8

* Update dependencies.


## 2.2.7

* Update code style.


## 2.2.6

* Update dependencies.


## 2.2.5

* Add support for Composer 2.2.


## 2.2.4

* Update dependencies.


## 2.2.3

* Run acceptance tests in dedicated database.


## 2.2.2

* Update dependencies.


## 2.2.1

* Update dependencies.


## 2.2.0

* Remove class `Pickware\DocumentBundle\ResponseFactory` as it was moved to the `shopware-extensions-bundle`. Should not be breaking as it was not used in any released plugin.


## 2.1.0

* Add `Pickware\DocumentBundle\Renderer` module with `Pickware\DocumentBundle\Renderer\DocumentTemplateRenderer` to render templates with a given locale.
* Add `Pickware\DocumentBundle\ResponseFactory`.


## 2.0.8

* Update `pickware/dal-bundle` to 3.6.0, `pickware/http-utils` to 2.3.0.


## 2.0.7

* Update `pickware/dal-bundle` to 3.5.0.


## 2.0.6

* Update `pickware/dal-bundle` to 3.4.0.
* Update `pickware/http-utils` to 2.1.0.


## 2.0.5

* Update `pickware/dal-bundle` to 3.3.0.


## 2.0.4

* Adjust composer version constraints to improve compatibility with Shopware 6.4.0.0 and above.


## 2.0.3

* Removed deprecations for version 2.0.0.


## 2.0.2

* Remove unnecessary dependency.


## 2.0.1

* Fix a problem that lead to duplicate migration execution.


## 2.0.0

* Update dependencies to be compatible to Shopware 6.4.0.0.
* Rename `Pickware\ShopwarePlugins\DocumentBundle` to `Pickware\DocumentBundle`


## 1.5.0

* Add new class `Pickware\ShopwarePlugins\DocumentBundle\Installation\DocumentUninstaller`
* Fix migration execution order.


## 1.4.0

* The path of the documents in the private file system is now stored explicitly in the entity.
* The file size of a document is now saved in the entity.
* A file name can now be saved for a document.
* The performance of downloading a document has been improved by streaming it directly from the file system.
* The method `DocumentContentsService::persistDocumentContents` has been deprecated. Use the method `DocumentContentsService::saveStringAsDocument` instead.
* The method `DocumentContentsService::readDocumentContents` has been deprecated. Use the private file system of the document bundle directly instead.
* When a document entity gets removed the corresponding file is now removed from the file system.


## 1.3.1

* Increase minimum required version of dependency `pickware/shopware-plugins-document-bundle`.


## 1.3.0

* Add support for Composer 2.
* Add method `DocumentContentsService::saveStringAsDocument`.


## 1.2.1

* Add support for Shopware 6.3.1.


## 1.2.0

* Drop support for Shopware 6.2.
* Add support for Shopware 6.3.


## 1.1.0

* Added support for Shopware 6.2.3.


## 1.0.0

* Initial release
