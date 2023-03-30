## 2.13.2

* Fix `NumberRangeInstaller` to not update the number range but noop if the number range already exists.


## 2.13.1

* Fix foreign key constraint error when uninstalling a state machine.


## 2.13.0

* Add method `Pickware\InstallationLibrary\StateMachine\StateMachineInstaller::removeStateMachine` to remove state machine during a plugin uninstallation.


## 2.12.1

* Update dependencies.


## 2.12.0

* Add method `Pickware\InstallationLibrary\StateMachine\StateMachine::getStatesThatAllowTransitionWithName()`.


## 2.11.1

* Update dependencies.


## 2.11.0

* The `StateMachineInstaller` now removes state transitions that do not exist anymore.


## 2.10.7

* Update dependencies.


## 2.10.6

* Fix mail template installer.


## 2.10.5

* Deprecate methods `Pickware\InstallationLibrary\MailTemplate\MailTemplate::getActionEvents()` and `Pickware\InstallationLibrary\MailTemplate\MailTemplateInstaller::ensureMailActionEventsWithEntityManager()`.
* Deprecate parameter `$actionEvents` in `Pickware\InstallationLibrary\MailTemplate\MailTemplate`.
* Fix tests for PHP 8.1.


## 2.10.4

* Fix document type installer.


## 2.10.3

* Update dependencies.


## 2.10.2

* Update dependencies.


## 2.10.1

* Update dependencies.


## 2.10.0

* Improved handling of unusual existing data in
  `Pickware\InstallationLibrary\MailTemplate\MailTemplateInstaller::installMailTemplate`:
  * Added optional method parameter `$logger`.
  * No longer crashes when multiple `EventAction`s exist for the same event name.
  * Existing duplicated `EventAction`s for the same event name are now either updated as well, or conditionally removed.
    The origin of these duplicated `EventAction`s is unknown as of yet (see
    <https://github.com/pickware/shopware-plugins/issues/2765> and
    <https://github.com/pickware/pickware-cloud/issues/5426>). `EventActions` are only deduplicated if they are
    inactive, if reference a non-existent `MailTemplateType`, and if they are not referenced by any `EventActionRule`s.
    In other cases, duplicates remain, however, no such cases have yet been observed.


## 2.9.0

**Requirements:**

* The package now requires at least Shopware version 6.4.5.0.


## 2.8.0

* Rework several installers to work with `Pickware\DalBundle\EntityManager`:
  * `Pickware\InstallationLibrary\DocumentType\DocumentTypeInstaller`
  * `Pickware\InstallationLibrary\MailTemplate\MailTemplateInstaller`
  * `Pickware\InstallationLibrary\NumberRange\NumberRangeInstaller`
  * `Pickware\InstallationLibrary\StateMachine\StateMachineInstaller`
* Add `MailTemplateUninstaller` for uninstalling mail templates.

**Requirements:**

* The package now requires at least Shopware version 6.4.4.0.


## 2.7.0

* Refactors the `Pickware\InstallationLibrary\DocumentType\DocumentTypeInstaller` to support installing `DocumentType` classes.
* Deprecates methods `Pickware\InstallationLibrary\DocumentType\DocumentTypeInstaller::ensureDocumentTypeExists()` and `Pickware\InstallationLibrary\DocumentType\DocumentTypeInstaller::copyDocumentConfigIfNotExists()`.
* Make method in `BundleSupportingAssetService` chainable.


## 2.6.1

* Internal refactoring and code style.


## 2.6.0

* Add new method `Pickware\InstallationLibrary\SqlView\SqlViewInstaller::ensureSqlView` to create SQL views on the
  database. Also added class `Pickware\InstallationLibrary\SqlView\SqlView` that will represent an installable
  SQL view.


## 2.5.3

* Update code style.


## 2.5.2

* Add support for Composer 2.2.


## 2.5.1

* Run acceptance tests in dedicated database.


## 2.5.0

* Add new method `Pickware\InstallationLibrary\NumberRange\NumberRangeInstaller::ensureNumberRange` to install a number
  range. Also added class `Pickware\InstallationLibrary\NumberRange\NumberRange` that will represent an installable
  number range.
* Deprecated method `Pickware\InstallationLibrary\NumberRange\NumberRangeInstaller::ensureNumberRangeExists`.
* Add new method `Pickware\InstallationLibrary\StateMachine\StateMachineInstaller::ensureStateMachine` to install a
  complete state machine. Also added class `Pickware\InstallationLibrary\StateMachine\StateMachine` that will represent
  an installable number range
* Deprecated all other methods in `Pickware\InstallationLibrary\StateMachine\StateMachineInstaller`.


## 2.4.0

* Add new method `Pickware\InstallationLibrary\MailTemplate\MailTemplateInstaller::installMailTemplate` that will
  handle all the installation for a mail template (including mail template type, template and translations). Therefore
  the following methods of this service are now deprecated: `ensureMailTemplateType()`, `ensureMailTemplate()`,
  `ensureMailTemplateTranslation()`, `ensureMailActionEvent()`.


## 2.3.0

* Add class`Pickware\InstallationLibrary\DocumentType\DocumentTypeInstaller`.


## 2.2.0

* Add class `Pickware\InstallationLibrary\MailTemplate\MailTemplateUpdater`.


## 2.1.1

* Adjust composer version constraints to improve compatibility with Shopware 6.4.0.0 and above.


## 2.1.0

* Adds `Pickware\InstallationLibrary\StateMachine\NumberRangeInstaller` to install number ranges.
* Adds `Pickware\InstallationLibrary\StateMachine\StateMachineInstaller` to install complete state machines (states and tansitions).


## 2.0.2

* Update composer.json to remove lock file via config.
* Fix deprecations.


## 2.0.1

* Remove unnecessary dependency.


## 2.0.0

* Rename `Pickware\ShopwarePlugins\ShopwareInstallationLibrary` to `Pickware\InstallationLibrary`


## 1.1.0

* Add class `\Pickware\ShopwarePlugins\ShopwareInstallationLibrary\BundleSupportingAssetService`.
* Fix installation of mail action events.


## 1.0.1

* Fixed Composer 2 support.


## 1.0.0

* Initial release
