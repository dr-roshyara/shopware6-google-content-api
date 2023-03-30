## 2.42.3

### de

**Fehlerbehebungen:**

* Behebt einen Fehler, dass in manchen Fällen die Administration Erweiterungen des Plugins nicht geladen werden konnten.
* Behebt einen Fehler beim Picken und Versenden, falls eine Bestellung gelöschte Produkte enthält.

### en

**Bug fixes:**

* Fixes a bug that in some cases the Administration components of the plugin could not be loaded.
* Fixes picking issues for order which contain deleted products.

## 2.42.1

### de

**Fehlerbehebungen:**

* Lieferantenbestellungen können jetzt auch als PDF heruntergeladen oder als CSV-Datei exportiert werden, wenn sie ein gelöschtes Produkt beinhalten.

### en

**Bug fixes:**

* Supplier orders can now also be downloaded as a PDF or exported as a CSV file if they contain a deleted product.


## 2.42.0

### de

**Neue Funktionen und Verbesserungen:**

* Picklisten zeigen ab jetzt immer die zu versendenden Positionen zum Zeitpunkt der Picklistenerstellung an.
* Lagerplätze können in der Lagerplatzübersicht eines Lagers jetzt auch per Mehrfachauswahl gelöscht werden.
* Der Bestand aus laufenden Pickprozessen (mit Pickware WMS) wird jetzt korrekt bei der Auswertung der Kommissionierbarkeit weiterer Bestellungen berücksichtigt.

**Fehlerbehebungen:**

* Produkt-Lieferanten-Zuordnungen können nun auch importiert werden, sofern mehrere Hersteller mit dem selben Namen existieren.
* Reduziert sich die angezeigte Menge an Positionen in der Bedarfsplanung aufgrund einer Anpassung der Filter, wird die Liste jetzt automatisch aktualisiert.

### en

**New features and improvements:**

* Pick lists now always show the items to be shipped at the time of the pick list creation.
* Bin locations can now also be deleted via multiple selection in the bin location overview of a warehouse.
* Stock from ongoing pick processes (with Pickware WMS) is now correctly taken into account when evaluating the pickability of further orders.

**Bug fixes:**

* Product supplier assignments can now also be imported if multiple manufacturers exist with the same name.
* If the displayed quantity of items in the demand planning is reduced due to an adjustment of the filters, the list is now automatically updated correctly.


## 2.41.0

### de

**Neue Funktionen und Verbesserungen:**

* Verbessert die Performance beim Aktualisieren von Bestellungen (u.A. beim Picken mit Pickware WMS).

**Fehlerbehebungen:**

* Lieferantenbestellungen können jetzt auch wieder per E-Mail an den Lieferanten gesendet werden, ohne dass ein "BCC-Empfänger für Lieferantenbestellungen" gesetzt sein muss.
* Das Erstellen von Picklisten über die Mehrfachänderung von Bestellungen ist jetzt auch mit Shopware Version 6.4.14.0 kompatibel.

### en

**New features and improvements:**

* Improves the performance when updating orders (e.g. when picking orders with Pickware WMS).

**Bug fixes:**

* Creating picklists via the bulk edit of orders is now compatible with shopware version 6.4.14.0.
* Supplier orders can now also be sent to the supplier by email again without having to set a "BCC recipient for supplier orders".


## 2.40.0

### de

**Neue Funktionen und Verbesserungen:**

* Beim Ändern des Einkaufspreises eines Produkts in einer Lieferantenbestellung, erscheint jetzt eine Abfrage, ob der Preis dauerhaft für das Produkt als neuer Einkaufspreis übernommen werden soll.
* Die getroffenen Filtereinstellungen können nun per Klick in allen Ansichten in der Administration rückgängig gemacht bzw. auf den Standardwert zurückgesetzt werden.
* In der Konfiguration können E-Mail-Adressen hinterlegt werden, die bei jeder Lieferantenbestellung per E-Mail in BCC gesetzt werden.
* In der Konfiguration können nun Vorlagen für Kommentare für Warenbewegungen angelegt werden, die dann in der Administration und in der Pickware WMS App als Vorauswahl zur Verfügung stehen.
* Die Ladezeiten beim Kommissionieren von Bestellungen mit der Pickware WMS App sind jetzt deutlich kürzer.

**Fehlerbehebungen:**

* Die in der Storefront auswählbare Menge von Produkten wird bei aktiviertem Abverkauf jetzt wieder korrekt auf den verfügbaren Bestand beschränkt.
* Der Inventurexport kann jetzt auch wieder durchgeführt werden, wenn eines der in der Inventur enthaltenen Produkte zwischenzeitlich gelöscht wurde.

### en

**New features and improvements:**

* When changing the purchase price of a product in a supplier order, the user is now asked whether the price should be permanently adopted for the product as the new purchase price.
* The filter settings can now be undone or reset to the default value with one click in all views in the administration.
* BCC recipients for each supplier order mail can now be configured in the plugin configuration.
* Templates for comments for manual stock movements can now be created in the plugin configuration..
* Loading times when picking orders with the Pickware WMS App are now significantly shorter.

**Bug fixes:**

* The quantity of products that can be selected in the storefront is now correctly limited to the available stock again when clearance sale is activated.
* The stocktake export now works again if one of the products included in the stocktake has been deleted in the meantime.


## 2.39.0

### de

**Neue Funktionen und Verbesserungen:**

* Abgeschlossene Inventuren können nun in der Gezählt-Ansicht einer Inventur als CSV-Datei exportiert werden.
* Picklisten können jetzt über die Mehrfachänderung von Bestellungen erstellt und heruntergeladen werden (ab Shopware Version 6.4.14.0 und höher).
* Fügt einen "Reset" Button zu diversen Filtern in Listenansichten in der Administration hinzu.

**Fehlerbehebungen:**

* Die dynamischen Produktgruppen werden jetzt beim Buchen von Bestand immer neu ausgewertet.
* Das PDF-Dokument der Lieferantenbestellung wird wieder korrekt an die E-Mail angehängt.
* Die Inventurzählungen eines Produkts mit Lagerplatz "Unbekannt" werden jetzt auch korrrekt verbucht, wenn das Produkt zusätzlich auf weiteren Lagerplätzen im Lager gezählt wurde.

### en

**New features and improvements:**

* Completed stocktakes can now be exported as a CSV file in the counted view of a stocktake.
* Picklists can now be created and downloaded via the bulk edit of orders (Shopware version 6.4.14.0 and higher).
* Adds a "reset" button to multiple filter fields in list views in the Administration.

**Bug fixes:**

* The dynamic product groups are now re-evaluated when moving stock.
* The PDF document of the supplier order is correctly attached to the email again.
* The stocktake counting processes of a product on an unknown bin location are now considered correctly, even it the same product was counted on other locations in the warehouse as well.


## 2.38.0

### de

**Neue Funktionen und Verbesserungen:**

* Der Firmenname von Lieferanten kann nun gepflegt werden.
* Zeigt den Firmennamen von Lieferanten auf Lieferantenbestelldokumenten.

### en

**New features and improvements:**

* The supplier company name can now be set for suppliers.
* Shows the supplier company name on supplier order documents.


## 2.37.1

### de

**Fehlerbehebungen:**

* Die Detailseite einer Lieferantenbestellung kann wieder geöffnet werden.

### en

**Bug fixes:**

* The supplier order detail page can be opened again.


## 2.37.0

### de

**Neue Funktionen und Verbesserungen:**

* Unter _Lagerhaltung -> Inventur_ können ab sofort Inventuren für einzelne Lager gestartet und beendet sowie fortlaufend bearbeitet werden.

**Fehlerbehebungen:**

* Pickware Dokumente (z.B. Lieferantenbestelldokumente) können jetzt mit verschiedenen Sprachen, welche die gleiche Region besitzen, erstellt werden.

### en

**New features and improvements:**

* Stocktaking for a warehouse can now be started, finished and edited in _Warehousing -> Stocktake_.

**Bug fixes:**

* Pickware documents (i.e. supplier order documents) can now be created with different languages that have the same region.


## 2.36.1

### de

**Fehlerbehebungen:**

* Behebt einen Fehler beim Update des Plugins.

### en

**Bug fixes:**

* Fixes a problem while updating this plugin.


## 2.36.0

### de

**Neue Funktionen und Verbesserungen:**

* Das PDF-Bestelldokument für eine Lieferantenbestellung wird jetzt immer automatisch als Anhang in die E-Mail an den Lieferanten geladen.
* Die Berechnung der Kommissionierbarkeit von Bestellungen ist jetzt deutlich schneller.

### en

**New features and improvements:**

* By default, the PDF order document for suppliers is now loaded as an attachment in an e-mail to the supplier.
* The calculation of the order pickability is now much faster.


## 2.35.0

### de

**Neue Funktionen und Verbesserungen:**

* Die Benachrichtigung per E-Mail über Produkte, die ihren Meldebestand erreicht haben, wird jetzt über den Flow Builder abgebildet.
* Die Berechnung der Kommissionierbarkeit von Bestellungen ist jetzt deutlich schneller.

**Fehlerbehebungen:**

* Zusatzfelder können wieder für Lieferanten und Lager erstellt und zugewiesen werden.

### en

**New features and improvements:**

* The email notification of products that have reached their reorder point can now be configured in the flow builder.
* The calculation of the order pickability is now much faster.

**Bug fixes:**

* Custom fields can be created and assigned for suppliers and warehouses again.


## 2.34.1

### de

**Fehlerbehebungen:**

* Zusatzinformationen von Varianten werden jetzt korrekt auf dem PDF zu Lieferantenbestellungen angezeigt.
* In der Dokumentenauswahl für die Mehrfachänderung von Bestellungen, steht der Dokumententyp "Lieferantenbestellung" nicht länger zur Auswahl.

### en

**Bug fixes:**

* Additional product variant information are now displayed correctly on the supplier order pdf.
* Removes "Supplier order" from document selection for order bulk edit.


## 2.34.0

### de

**Neue Funktionen und Verbesserungen:**

* Lieferantenbestellungen können ab jetzt auch als PDF-Datei heruntergeladen werden.
* Lieferanten können jetzt per CSV-Datei exportiert und importiert werden.
* Die Kommissionierbarkeit von Bestellungen wird nun pro Lager berechnet.
* Ein Lager kann per Aktionsmenü in der Listenansicht zum Standardlager gemacht werden.

**Fehlerbehebungen:**

* Lieferanten können nun gelöscht werden, selbst wenn noch Zuordnungen zu Produkten oder Lieferantenbestellungen existieren.

### en

**New features and improvements:**

* Supplier orders can now be downloaded as PDF files.
* Suppliers can now be exported and imported via CSV file.
* The order pickability is now calculated per warehouse.
* The default warehouse can be selected via the action menu in the list view.

**Bug fixes:**

* Suppliers can now be deleted, even if mappings to products or supplier orders exist.


## 2.33.2

### de

**Fehlerbehebungen:**

* In der Bestandsplanung kann jetzt der vorraussichtliche Bedarf für 0 Tage berechnet werden, um Bestellvorschläge auch ohne Berücksichtigung voraussichtlicher Produktverkäufe zu ermitteln.
* Der Produktimport per Shopware Import/Export funktioniert jetzt auch wieder wenn zuvor ein Lager gelöscht wurde.
* Im Flow Builder wird das gespeicherte Lager beim Öffnen des Modals zur Bearbeitung einer Aktion zur Dokumentengenerierung wieder korrekt angezeigt.


### en

**Bug fixes:**

* The estimated demand can now be calculated for 0 days in demand planning to determine order proposals without taking expected product sales into account.
* Product import via Shopware Import/Export now works again even if a warehouse was previously deleted.
* In the Flow Builder, the saved warehouse is now preselected correctly when opening the modal for editing an action for document generation.


## 2.33.1

### de

**Fehlerbehebungen:**

* Behebt einen Fehler bei der Installation in Shopware Version 6.4.12.0 und neuer.

### en

**Bug fixes:**

* Fixes an error during the installation in Shopware version 6.4.12.0 and above.


## 2.33.0

### de

**Neue Funktionen und Verbesserungen:**

* Picklistenpositionen werden nun sekundär nach der Produktnummer sortiert.
* Lieferantenbestellungen können nun als PDF exportiert werden.

**Fehlerbehebungen:**

* Lieferanten können nach Kontaktperson sortiert werden.
* Beim Setzen des Bestellstatus auf "Abgeschlossen" wird jetzt wieder das Feld "sales" von Produkten korrekt berechnet.
* Nachdem der Bestellstatus auf "Abgeschlossen" beim Versenden einer Bestellung gesetzt wurde, wird die Bestellung neu geladen.

### en

**New features and improvements:**

* Picklist items are now secondarily sorted by their product number.
* Supplier orders can now be exported to PDF.

**Bug fixes:**

* Suppliers can be sorted by contact persons.
* When setting the order status to "Completed", the "sales" field of products is now calculated correctly again.
* After setting the order status to "Completed" when delivering an order the order will be reloaded.


## 2.32.0

### de

**Neue Funktionen und Verbesserungen:**

* Es wird nun eine Warnung angezeigt, wenn seit der letzten Berechnung der Bedarfsplanung neue Lieferantenbestellungen erstellt wurden.
* Im bewerteten Warenbestand werden Produkte ohne Bestand nun standardmäßig ausgeblendet.
* Fügt mehrere neue Filer in der Bestandsübersicht hinzu.
* Fügt neue Filter in der Bestandsübersicht hinzu.
* Es sind weitere optionale Spalten in der Lieferantenübersicht verfügbar.
* Felder in der Lieferantenübersicht sind nun editierbar.

### en

**New features and improvements:**

* A warning is now displayed if new supplier orders have been created since the last demand planning calculation.
* Products without stock are now hidden by default in the stock valuation report.
* Adds several new filters in the stock overview.
* Adds new filters in the stock overview.
* There are more optional columns available in the supplier overview.
* Fields in the supplier overview are now editable.


## 2.31.0

### de

**Neue Funktionen und Verbesserungen:**

* Hauptprodukte von Varianten werden nun standardmäßig in der Bedarfsplanung ausgeblendet.

**Fehlerbehebungen:**

* Behebt einen Fehler beim Plugin-Update, der auftreten konnte, wenn mehrere Einträge für Business-Events mit Pickware-E-Mail-Templates angelegt wurden.

### en

**New features and improvements:**

* Main products of variants are now hidden by default in demand planning.

**Bug fixes:**

* Fixes an issue during plugin updates, that could occur when multiple entries for business events relating to Pickware e-mail templates had been created.


## 2.30.1

### de

**Fehlerbehebungen:**

* Allgemeine Verbesserungen im Umgang mit mehreren Versionen einer Bestellung.

### en

**Bug fixes:**

* General improvements in handling multiple versions of an order.


## 2.30.0

### de

**Neue Funktionen und Verbesserungen:**

* Wird der Bestellstatus einer Lieferantenbestellung auf "Geliefert" gesetzt, erscheint nun ein Hinweis, wenn diese zuvor noch nicht vollständig eingelagert wurde.
* Lieferantenbestellungen sind nun in der Detailansicht bearbeitbar.

### en

**New features and improvements:**

* A warning is now displayed if the order status of a supplier order is set to "Delivered" that has not yet been stocked completely.
* Supplier orders are now editable in the detail view.


## 2.29.1

### de

**Fehlerbehebungen:**

* Bestellungen können jetzt wieder bearbeitet werden.

### en

**Bug fixes:**

* Orders can now be edited again.


## 2.29.0

### de

**Neue Funktionen und Verbesserungen:**

* Es wird jetzt eine Warnung ausgegeben, wenn sie den Status einer Lieferantenbestellung auf "Geliefert" stellen, obwohl die Liefermenge unter der Bestellmenge liegt.
* Lieferantenbestellungen können jetzt auch in der Detailansicht direkt gesendet oder eingelagert werden.

**Fehlerbehebungen:**

* Behebt einen Fehler beim Updaten einer Datenbanktabelle aus dem letzten Release.

### en

**New features and improvements:**

* A warning is now shown, when you set a supplier order to "Delivered", even though the delivered quantitity is below the ordered quantity.
* You can now send and stock supplier orders directly in their detail view.

**Bug fixes:**

* Fixes an error with the updating of an existing database table in the last release.


## 2.28.0

### de

**Neue Funktionen und Verbesserungen:**

* Lieferantenbestellungen können jetzt auch in der Detailansicht direkt als CSV-Datei exportiert, gesendet, eingelagert oder gelöscht werden.
* In der Detailansicht von Lieferantenbestellungen ist neben der "Bestellmenge" jetzt auch die "Liefermenge" in einer weiteren Spalte zu sehen.

**Fehlerbehebungen:**

* Der Datepicker zur Auswahl von Zeiträumen (z.B. für Promotions) funktioniert in der Administration wieder an allen Stellen.
* Änderungen von anderen Plugins in der Administration in der Bestelldetailseite im Bereich der Lieferungen werden nun nicht mehr überschrieben.

### en

**New features and improvements:**

* You can now export, send, stock and delete supplier orders directly in their detail view.
* The supplier order detail view now shows the "Delivered Quantity" next to the "Ordered Quantity".

**Bug fixes:**

* The date picker for selecting time periods (e.g. for promotions) works again in all places in the administration.
* UI changes of other plugins in the administration in the order detail page around the shipments are not overwritten anymore.


## 2.27.3

### de

**Neue Funktionen und Verbesserungen:**

* Bei vollständiger Deinstallation werden nun auch E-Mail-Templates entfernt.
* Verbessert die Berechnung der Komminissionierbarkeit von Bestellungen und damit die Ladezeit der Bestellübersicht.

**Fehlerbehebungen:**

* Behebt ein Problem bei der Verwendung des Extension-Frameworks von Shopware unter Composer 2.2.x.

### en

**New features and improvements:**

* Mail templates will be removed when uninstalling the plugin completely.
* Improves the pickability calculation of orders. Reduces the loading time of the order list in the Administration.

**Bug fixes:**

* Fixes an issue when using Shopware's extension framework with Composer 2.2.x.


## 2.27.2

### de

**Fehlerbehebungen:**

* Das Erzeugen von Picklisten Dokumenten funktioniert nun wieder über den Flow Builder.

**Anforderungen:**

* Das Plugin benötigt nun mindestens Shopware Version 6.4.10.0.

### en

**Bug fixes:**

* The picklist document creation works again via the flow builder.

**Requirements:**

* The plugin now requires at least Shopware version 6.4.10.0.


## 2.27.1

### de

**Fehlerbehebungen:**

* In Shopware Installationen mit MariaDB kann Pickware ERP Starter jetzt wieder auf Versionen > 2.26.0 aktualisiert werden.

### en

**Bug fixes:**

* In Shopware installations with MariaDB, Pickware ERP Starter can now be updated again to versions > 2.26.0.


## 2.27.0

### de

**Neue Funktionen und Verbesserungen:**

* Offene Lieferantenbestellungen werden nun in den Zustand "An Lieferanten gesendet" überführt, wenn sie durch das
  Aktionenmenü in der Listenansicht an den Lieferanten gesendet werden.
* Fehlende Datenbank-Views werden nun bei einem Update oder eine Neuinstallation automatisch erkannt und neu erstellt.
* Die Summen über dem bewerteten Warenbestand wurden in die Tabelle als fixierte Summenzeile verschoben.

**Fehlerbehebungen:**

* Produktbilder werden auf der Einkaufsliste wieder korrekt angezeigt.
* Korrigiert kleine Anzeigefehler im Bestandstab von Produkten in der Administration in Shopware Version 6.4.10.0.
* Der Meldebestand kann im Bestandstab von Produkten auch nach mehrfacher Änderung wieder gespeichert werden.

### en

**New features and improvements:**

* The state of open supplier orders will now be changed to "Sent to supplier", when they are sent to the supplier via
  the actions menu in the list view.
* Missing database views are now automatically detected and recreated during an update or a re-installation.
* The sums above the stock valuation were moved into the table as a fixed summary row.

**Bug fixes:**

* Product images are shown in the purchase list correctly.
* Fixes small styling errors in the stock tab of products in the Administration in Shopware version 6.4.10.0.
* The reorder point of products can be edited and saved multiple times in the stock tab of products.


## 2.26.0

### de

**Neue Funktionen und Verbesserungen:**

* Lieferantenbestellungen können nun über das Aktionenmenü in der Listenansicht eingelagert werden.
* Die Verlinkung von Brutto- und Nettoeinkaufspreisen von Produkten kann in der Administration nicht mehr aufgehoben werden.
* Verbessert die Omnisuche in der Administration für die Pickware Module "Lager" und "Lieferantenbestellungen".
* Ermöglicht das Erstellen von Picklisten über den Flow Builder.

**Fehlerbehebungen:**

* Die Bedarfsplanung wird auch mit Shopware Version 6.4.9.0 und höher wieder korrekt geladen.

### en

**New features and improvements:**

* Supplier orders can now be stocked using the actions menu in the list view.
* Gross and net purchase prices of products cannot be unlinked in the administration anymore.
* Improves the search bar in the administration for the Pickware modules "Warehouses" and "Supplier orders".
* Supports creating picklist documents via the flow builder.

**Bug fixes:**

* Demand planning is now working for Shopware version 6.4.9.0 and higher again.


## 2.25.0

### de

**Neue Funktionen und Verbesserungen:**

* Im Reiter "Bestand" eines Produkts wird ab jetzt bei Bestandsbewegungen zusätzlich der Benutzer angezeigt.
* Lieferanten können nun mit einem Klick direkt aus der Übersicht der Lieferantenbestellungen geöffnet werden.
* In der Einkaufsliste werden Produkte jetzt mit einem Warnhinweis versehen, sofern deren Bestellmenge nicht zur hinterlegten Mindestabnahme oder dem Abnahmeintervall des Produkts passt.
* Werden Änderungen an der Produkt-Lieferanten-Zuordnung eines Variantenprodukts vorgenommen, welche für alle Varianten übernommen werden sollen, wird jetzt eine Fortschrittsleiste angezeigt.
* Wird der Bestellstatus einer Lieferantenbestellung auf "Bestätigt" geändert, wird nun automatisch das voraussichtliche Lieferdatum auf Basis der Standardlieferzeit des jeweiligen Lieferanten gesetzt.

### en

**New features and improvements:**

* The user is now additionally displayed for stock movements in the "Stock" tab of a product.
* Suppliers can now openend via link directly from the list of supplier orders.
* Products are now displayed with a warning in the purchase list if their order quantity does not match the minimum purchase or the purchase steps of the product.
* If changes are made to the product supplier mapping of a variant product, that should be applied to all variants, a progress bar is now displayed.
* If the order status of a supplier order is changed to "Confirmed", the expected delivery date is now automatically set based on the standard delivery time of the respective supplier.


## 2.24.0

### de

**Neue Funktionen und Verbesserungen:**

* Bestelldokumente können in der Detailansicht einer Bestellung nun direkt durch Klick auf den Dokumententyp geöffnet werden.
* Das Plugin ist nun kompatibel mit dem Plugin "Mail Archive".

**Fehlerbehebungen:**

* CSV-Dateien zu Exporten beinhalten wieder alle relevanten Datensätze.

**Anforderungen:**

* Das Plugin benötigt nun mindestens Shopware Version 6.4.6.0.

### en

**New features and improvements:**

* Order documents can now be opened directly by clicking the document type in the order detail view.
* The plugin is now compatible with the plugin "Mail Archive".

**Bug fixes:**

* CSV files for exports again contain all relevant records.

**Requirements:**

* The plugin now requires at least Shopware version 6.4.6.0.


## 2.23.1

### de

**Fehlerbehebungen:**

* Auf Picklisten werden die zugehörige Bestellnummer und das ausgewählte Lager wieder korrekt angezeigt.
* Das Layout von Picklisten unterstützt jetzt auch Adressen mit sehr langen Namen.
* Die Performance und Stabilität beim Import und Export von großen CSV-Dateien wurde verbessert.

### en

**Bug fixes:**

* The order number and the selected warehouse are displayed correctly on picklists again.
* The picklist layout now supports addresses with very long names.
* The performance and stability when importing and exporting large CSV files has been improved.


## 2.23.0

### de

**Neue Funktionen und Verbesserungen:**

* Das Ziellager für Lieferantenbestellungen kann ab jetzt geändert werden.
* Bestellpositionen in Lieferantenbestellungen können ab jetzt bearbeitet und gelöscht werden.
* Es können nun Picklisten über den Flow Builder generiert werden.
* Unterstützt jetzt die Shopware Mehrfachänderung für den Lieferstatus "Retour", sodass unter Auswahl eines Lagers automatisch der korrekte Bestand eingelagert wird.

**Fehlerbehebungen:**

* Picklisten können für Shopware Version 6.4.7.0 und höher wieder erstellt werden.
* Im Reiter "Bestellungen" eines Produkts werden die Statusfilter wieder angezeigt.

### en

**New features and improvements:**

* The target warehouse for supplier orders can be changed from now on.
* Order items in supplier orders can edited or removed from now on.
* Picklists can now be generated via Flow Builder.
* Now supports the shopware bulk edit for the delivery status "Return", so that the stock is automatically restored in the selected warehouse.

**Bug fixes:**

* Picklists can be created again for Shopware version 6.4.7.0 and higher.
* Filters can now be set again in the "Orders" tab of products.


## 2.22.0

### de

**Neue Funktionen und Verbesserungen:**

* Unterstützt jetzt die Shopware Mehrfachänderung für den Lieferstatus "Versandt", sodass unter Auswahl eines Lagers automatisch der korrekte Bestand ausgebucht wird.
* In den Produkt-Lieferanten-Zuordnungen kann jetzt nach Produkten, Lieferanten und Herstellern gefiltert werden.

**Fehlerbehebungen:**

* In der Bedarfsplanung werden alte Bestellungen, zu denen mehrere Versionen vorliegen, nun nicht länger mehrfach berücksichtigt.
* Der Import für Produkt-Lieferanten-Zuordnungen funktioniert nun auch korrekt für Produktvarianten.
* In den Produkt-Lieferanten-Zuordnungen können zugewiesene Lieferanten nun auch wieder über die Administration entfernt werden.

**Anforderungen:**

* Das Plugin benötigt nun mindestens Shopware Version 6.4.2.0.

### en

**New features and improvements:**

* Now supports the shopware bulk edit for the delivery status "Shipped", so that the stock is automatically removed in the selected warehouse.
* Product supplier mappings can now be filtered by products, suppliers and manufacturers.

**Bug fixes:**

* Multiple versions of old orders are no longer considered multiple times for demand planning.
* The import for product supplier mappings now works for product variants as well.
* In the administration, assigned suppliers can now be removed again for product supplier configurations.

**Requirements:**

* The plugin now requires at least Shopware version 6.4.2.0.


## 2.21.0

### de

**Neue Funktionen und Verbesserungen:**

* Lieferantenbestellungen können jetzt per E-Mail an den Lieferanten gesendet werden.
* In der Bedarfsplanung werden inaktive Produkte jetzt standardmäßig ausgeblendet.
* In der Bedarfsplanung werden Produkte, für die der Abverkauf aktiviert ist, jetzt standardmäßig ausgeblendet.
* In der Bedarfsplanung können Produkte jetzt nach Hersteller gefiltert werden.
* In der Bedarfsplanung können Produkte jetzt nach Kategorie gefiltert werden.
* Vererbte Produktnamen von Varianten werden nun korrekt in der Detailansicht der Lieferantenbestellungen angezeigt.
* Lagerplatzimporte werden jetzt auch in der Übersicht aller Importe und Exporte gelistet.

**Fehlerbehebungen:**

* Der Bestand von Produkten kann nun wieder per absolutem Bestandsimport auf 0 gesetzt werden.

### en

**New features and improvements:**

* Supplier orders can now be sent to suppliers by email.
* In demand planning, inactive products are now hidden by default.
* In demand planning, products in clearance sale are now hidden by default.
* In demand planning, products can now be filtered by manufacturer.
* In demand planning, products can now be filtered by category.
* Inherited product names of variants are now correctly displayed in the detail view of supplier orders.
* Bin location imports are now also listed in the overview of all imports and exports.

**Bug fixes:**

* The product stock can now be set to 0 again using the absolute stock import.


## 2.20.1

### de

**Fehlerbehebungen:**

* Der Import für die Produkt-Lieferanten-Zuordnung geht nun korrekt mit leeren Einträgen für Einkaufspreise um.

### en

**Bug fixes:**

* The import for product supplier mappings now handles empty entries for purchase prices correctly.


## 2.20.0

### de

**Neue Funktionen und Verbesserungen:**

* Das Plugin unterstützt nun Shopware Version 6.4.3.0.
* Standardlagerplätze werden nun in der Bestandsübersicht markiert und können per Import geändert werden.
* Der bewertete Warenbestand kann nun als CSV-Datei exportiert werden.
* Die zu exportiertenden Spalten der Lieferantenbestellungen können nun in den allgemeinen Plugin-Einstellungen angepasst werden.
* Die Bedarfsplanung kann jetzt nach Lieferant gefiltert werden.
* Die Einkaufsliste kann jetzt nach Lieferant gefiltert werden.
* Der Meldebestand eines Produktes kann nun per Bestandsimport geändert werden.
* Die Bedarfsplanung berücksichtigt jetzt eingehende Lieferantenbestellungen für den Bestellvorschlag.

**Fehlerbehebungen:**

* Meldebestands-Emails werden nun wieder zur konfigurierten Zeit versandt.

### en

**New features and improvements:**

* The plugin now supports Shopware version 6.4.3.0.
* Standard bin locations are now highlighted in the stock overview and can be changed by CSV-import.
* The stock valuation can now be exported as a CSV file.
* The columns of the supplier export can now be adjusted in the general plugin settings.
* The demand planning can now be filtered by supplier.
* The purchase list can now be filtered by supplier.
* Reorder point of a product can now be changed via stock import.
* Incoming supplier orders are now taken into account in demand planning.

**Bug fixes:**

* Reorder point emails are now sent at the configured time again.


## 2.19.0

### de

**Neue Funktionen und Verbesserungen:**

* Unter "Einkauf" -> "Lieferantenzuordnung" gibt es ab sofort eine weitere Spalte "Lieferantennummer", um die Produkt-Lieferanten-Zuordnung anhand dieser durchführen zu können und nicht mehr den Namen des Lieferanten in der CSV-Datei angeben zu müssen.
* Unter "Einkauf" -> "Lieferantenzuordnung" kann jetzt nach Produkten gefiltert werden, die noch keinem Lieferanten zugeordnet sind.

**Fehlerbehebungen:**

* Picklisten können für Shopware Version 6.4.2.0 und höher wieder heruntergeladen werden.
* Das Plugin lässt sich für Shopware Version 6.4.2.0 und höher wieder ohne Fehler installieren.

### en

**New features and improvements:**

* There is now an additional column "Supplier number" that can be used instead of the name of the supplier to perform the product supplier mapping via CSV file in "Purchasing" -> "Supplier mapping".
* It is now possible to filter for products that have not yet been mapped to a supplier in "Purchasing" -> "Supplier mapping".

**Bug fixes:**

* Picklists can be downloaded again for Shopware version 6.4.2.0 and higher.
* The plugin can now be installed again without errors for Shopware version 6.4.2.0 and higher.


## 2.18.0

### de

**Neue Funktionen und Verbesserungen:**

* Ermöglicht das Erstellen von Lieferantenbestellungen aus der Einkaufsliste heraus.
* Unter "Einkauf" -> "Lieferantenbestellungen" werden ab sofort alle Lieferantenbestellungen gelistet, die zuvor erstellt wurden.
* Unter "Einkauf" -> "Lieferantenbestellungen" können Lieferantenbestellungen ab sofort als CSV-Datei heruntergeladen werden.
* Produkt-Lieferanten-Zuordnungen können nun unter "Einkauf" -> "Lieferantenzuordnung" als CSV-Datei exportiert und importiert werden.
* Bei Lieferantenproduktnummern wird nun eine Länge von bis zu 64 Zeichen unterstützt.

### en

**New features and improvements:**

* Enables you to create supplier orders from the purchase list.
* All supplier orders that have been previously created are now listed in "Purchasing" -> "Supplier orders".
* Supplier orders can now be exported as a CSV file in "Purchasing" -> "Supplier orders".
* Product supplier mappings can now be exported and imported as CSV file in "Purchasing" -> "Supplier mapping".
* Supplier product numbers can now have a length of up to 64 characters.


## 2.17.0

### de

**Neue Funktionen und Verbesserungen:**

* Unter "Lagerhaltung" -> "Bestandsübersicht" kann ab sofort zwischen einem Export mit Bestand, als Vorlage für absolute Bestandsänderungen und einem Export ohne Bestand, als Vorlage für relative Bestandsänderungen gewählt werden.
* Unter "Lagerhaltung" -> "Bestandsübersicht" kann ab sofort zwischen einem absoluten Bestandsimport, z.B. bei einer Inventur des Lagers und einem relativen Bestandsimport, z.B. beim Wareneingang einer Lieferantenbestellung, gewählt werden.
* Unter "Einkauf" -> "Lieferantenzuordnung" vorgenommene Änderungen an der Produkt-Lieferanten-Zuordnung von Varianten, können jetzt optional für alle Varianten übernommen werden.


### en

**New features and improvements:**

* It is now possible to choose between an export with the current stock, as a template for absolute stock changes, and an export without the current stock, as a template for relative stock changes, in "Warehousing" -> "Stock overview".
* It is now possible to choose between an absolute stock import, e.g. following a stocktaking of a warehouse, and a relative stock import, e.g. when stocking a supplier order, in "Warehousing" -> "Stock overview".
* Changes made to the product supplier mapping of variants in "Purchasing" -> "Supplier mapping" can now optionally be applied to all variants.


## 2.16.0

### de

**Neue Funktionen und Verbesserungen:**

* Unter "Einkauf" -> "Lieferantenzuordnung" können ab sofort Produkt-Lieferanten-Zuordnungen festgelegt und bearbeitet werden.
* Unter "Einkauf" -> "Bedarfsplanung" können ab sofort, auf Basis von vergangenen Verkäufen, Bestellvorschläge für einen gewünschten Prognosezeitraum berechnet und die entsprechenden Produkte anschließend auf die Einkaufsliste übernommen werden.
* Unter "Einkauf" -> "Einkaufsliste" werden ab sofort alle Produkte gelistet, die zuvor auf die Einkaufsliste gesetzt wurden.
* Exporte der Bestandsübersicht werden ab sofort geloggt und können nun nachträglich unter "Einstellungen" -> "Import/Export (Pickware)" eingesehen und abgerufen werden.

**Fehlerbehebungen:**

* Bestellungen, die bereits vor der Installation von Pickware ERP Starter im Lieferstatus "Versandt" waren, können nun retourniert werden.
* Bestellungen mit sehr vielen Positionen führen nun nicht mehr zu ungewollt leeren Seiten zu Beginn der Pickliste.
* Zusatzinformationen von Custom Products werden nun auch auf der Pickliste angezeigt.


### en

**New features and improvements:**

* Product supplier assignments can now be defined and edited in "Purchasing" -> "Supplier mapping".
* It is now possible to calculate purchase suggestions for a desired forecast period based on past sales in "Purchasing" -> "Demand planning". The products can then be transferred to the purchase list.
* All products that have previously been placed on the purchase list are now listed in "Purchasing" -> "Purchase list".
* Exports of the stock overview are logged from now on and can be viewed and downloaded afterwards in "Settings" -> "Import/Export (Pickware)".

**Bug fixes:**

* Order that have been shipped before Pickware ERP Starter was installed, can now be returned.
* Orders with a large number of items no longer result in unintentionally blank pages at the beginning of the picklist.
* Additional information of custom products are now also displayed on the picklist.


## 2.15.1

### de

**Fehlerbehebungen:**

* Das Plugin kann ab Shopware Version 6.4.0 wieder erfolgreich aktualisiert werden.

### en

**Bug fixes:**

* The plugin can be successfully updated again with Shopware version 6.4.0 or higher.


## 2.15.0

### de

**Neue Funktionen und Verbesserungen:**

* Das Plugin unterstützt nun Shopware Version 6.4.0.
* Eine Bestellung, die bereits im Lieferstatus "Versandt" war, kann jetzt auch erneut als "Versandt" markiert werden, wenn sich der Lieferstatus zwischenzeitlich geändert hatte.

**Anforderungen:**

* Das Plugin erfordert nun mindestens Shopware Version 6.4.0.


### en

**New features and improvements:**

* The plugin now supports Shopware version 6.4.0.
* An order that was already in the delivery status "Shipped" can now be marked as "Shipped" again if the delivery status had changed in the meantime.

**Requirements:**

* The plugin now requires at least Shopware version 6.4.0.


## 2.14.0

### de

**Neue Funktionen und Verbesserungen:**

* "Leere Positionen" einer Bestellung werden jetzt auch auf der Pickliste angezeigt.
* Es wird nun eine Warnung in der Administration angezeigt, wenn die Message Queue nicht ordnungsgemäß läuft.

**Fehlerbehebungen:**

* Bestellungen, die Custom Products enthalten, können nun wieder gelöscht werden.
* Die E-Mails zur täglichen Benachrichtigung über alle Produkte, die ihren Meldebestand erreicht haben, werden wieder korrekt versandt.

### en

**New features and improvements:**

* Custom items are now also displayed on picklists.
* A warning is now displayed in the administration if the message queue is not running properly.

**Bug fixes:**

* Orders that contain Custom Products can now be deleted again.
* The daily reorder notification emails are being sent out properly again.


## 2.13.1

### de

**Fehlerbehebungen:**

* Vererbte Einkaufspreise und Zusatzbezeichnungen von Varianten werden im bewerteten Warenbestand jetzt korrekt angezeigt.
* CSV-Dateien für den relativen Bestandsimport können nun auch unter Windows hochgeladen und importiert werden.
* Die Konfiguration der Pickliste unter *Einstellungen -> Dokumente* wird nun auch für Shopware Version 6.3.5.0 oder höher wieder korrekt geladen.
* Für Bestellungen, die Produkte beinhalten, welche zwischenzeitlich gelöscht wurden, können nun weiterhin Picklisten erzeugt werden.

### en

**Bug fixes:**

* Inherited purchase prices and additional information of variants are now correctly displayed in the stock valuation.
* CSV files for the relative stock import can now also be uploaded and imported using Windows.
* The picklist configuration in *Settings -> Documents* is now loaded correctly again for Shopware version 6.3.5.0 or higher.
* Picklists can now still be created for orders that contain products that have been deleted in the meantime.


## 2.13.0

### de

**Neue Funktionen und Verbesserungen:**

* Verbessert die Auswahl von Standardlagerplätzen am Produkt, indem Lagerplätze mit Bestand bevorzugt angezeigt werden.
* Bei der Migration von Shopware 5 auf Shopware 6 wird der Lagerbestand von Produkten ab jetzt korrekt synchronisiert.

**Fehlerbehebungen:**

* Produkte denen ein Standardlagerplatz zugeordnet ist, können wieder korrekt dupliziert werden.
* Die Produktsuche in der Produkt-Lieferanten-Zuordnung funktioniert jetzt auch wieder für Shopware Version 6.3.5.0 oder höher.
* Das Datumsfeld für den "Wunschtag" bei der Versandetikettenerstellung mit dem Plugin _DHL Versand_ funktioniert nun wieder wie gewohnt.
* Das Plugin lässt sich nun wieder auf Systemen aktualisieren, die in MySQL keine SUPER-Privileges haben.

### en

**New features and improvements:**

* Improves the default bin location selection of a product by preferentially displaying bin locations with stock.
* The product stock is now synchronized correctly when migrating from shopware 5 to shopware 6.

**Bug fixes:**

* Products with default bin locations can be cloned again.
* The product search in the product supplier assignment now works again for Shopware version 6.3.5.0 or higher.
* The date field for the "preferred day" when creating shipping labels with the plugin _DHL Shipping_ now works as usual again.
* The plugin can now be updated again on systems that do not have SUPER privileges in MySQL.


## 2.12.0

### de

**Neue Funktionen und Verbesserungen:**

* Die Migration der Bestände von Produkten von Shopware 5 nach Shopware 6 funktioniert nun korrekt.
* In der Bestandsübersicht können ab sofort relative Bestandsänderungen, unter Angabe des gewünschten Lagerorts, per CSV-Datei importiert werden.
* Unter *Einstellungen -> Shop -> Import/Export (Pickware)* gibt es ab sofort eine Übersicht aller laufenden und vergangenen Importe/Exporte, inklusive Fortschrittsanzeige, Fehlerausgabe und Dateidownload.

**Fehlerbehebungen:**

* Die Pick-Anweisung wird jetzt auch beim Einsatz von Shopware Version 6.3.5.0 oder höher nur noch einfach auf der Pickliste angezeigt.

### en

**New features and improvements:**

* The migration of the stocks of products from Shopware 5 to Shopware 6 now works correctly.
* Relative stock changes can now be imported for specific stock locations via CSV file in the stock overview.
* All current and past imports/exports, including progress, error output and file download are now displayed in an overwiew in *Settings -> Shop -> Import/Export (Pickware)*.

**Bug fixes:**

* The picking instructions are now only displayed once on the pick list when using Shopware version 6.3.5.0 or higher.


## 2.11.0

### de

**Neue Funktionen und Verbesserungen:**

* Fügt eine Auswertung zur Bewertung des Warenbestandes hinzu.
* Änderungen an einer Produkt-Lieferanten-Zuordnung können nun für alle Varianten eines Produkts übernommen werden.
* Fügt der Produkt-Lieferanten-Zuordnung das Feld "Mindestabnahme", "Abnahmeintervall" und "Lieferantenproduktnummer" hinzu.

**Fehlerbehebungen:**

* Änderungen an Bestellpositionen wirken sich nun korrekt auf das Versenden der Lieferung der entsprechenden Bestellung aus.

### en

**New features and improvements:**

* Adds a report for evaluating the inventory value.
* Changes of a product supplier assignment can now be applied to all variants of a product.
* Adds the fields "Minimum purchase", "Purchase steps" and "Supplier product number" to the product supplier assignment.

**Bug fixes:**

* Changes in order positions now correctly affect the shipping of the order's delivery.


## 2.10.1

### de

**Fehlerbehebungen:**

* Picklisten lassen sich nun auch für Bestellungen erstellen, für die ein automatischer Versandkostenrabatt vorliegt.
* Die Pickliste wird für Shopware Versionen ab Shopware 6.3.4.0 jetzt korrekt dargestellt.

### en

**Bug fixes:**

* Picklists can now also be created for orders with an automatic shipping cost discount.
* The picklist is now displayed correctly for Shopware version 6.3.4.0 and higher.


## 2.10.0

### de

**Neue Funktionen und Verbesserungen:**

* Eine "Auftragsampel" zeigt jetzt in der Liste aller Bestellungen an, ob ausreichend Bestand vorliegt, um diese zu kommissionieren (Grün = Vollständig kommissionierbar, Orange = Teilweise kommissionierbar, Rot = Nicht kommissionierbar).
* Die Liste aller Bestellungen kann jetzt nach Kommissionierbarkeit gefiltert werden.
* Die Liste aller Bestellungen kann jetzt nach Bestell-, Zahlungs- und Lieferstatus gefiltert werden.
* Die Bestandsübersicht kann in der Ansicht *Je Produkt* jetzt zusätzlich nach Lieferanten gefiltert werden.

### en

**New features and improvements:**

* The order list now contains a "traffic light" that shows whether there is enough stock to pick an order (green = completely pickable, orange = partially pickable, red = not pickable).
* The order list can now be filtered by pickability.
* The order list can now be filtered by order, payment and delivery status.
* The stock overview can now be additionally filtered by supplier in the view *Per product*.


## 2.9.0

### de

**Neue Funktionen und Verbesserungen:**

* Unter "Einkauf" -> "Lieferanten" können ab sofort Lieferanten erstellt und Stammdaten zu diesen gepflegt werden.
* Im Reiter "Produkte" eines Lieferanten können diesem ab sofort Produkte zugeordnet werden.
* Im Reiter "Lieferant" eines Produkts kann diesem ab sofort ein Lieferant zugeordnet werden.
* Sofern ein Kunde im Bestellabschluss einen Bestellkommentar angibt, wird dieser jetzt auch auf der Pickliste angezeigt.
* Verbessert die Suche und Darstellung von Lagerplätzen an diversen Stellen in der Administration. Diese Verbesserung greift ab Shopware Version 6.3.2.0.

**Fehlerbehebungen:**

* Lieferadressen werden wieder korrekt auf Picklisten angezeigt.

### en

**New features and improvements:**

* Suppliers can now be added and edited in "Purchasing" -> "Suppliers".
* Products can now be assigned to a supplier in the "Products" tab of the supplier.
* A supplier can now be assigned to a product in the "Supplier" tab of the product.
* If a customer enters an order comment in the checkout, this comment is now also displayed on the picking list.
* Improves the search and display of bin locations at various places in the administration. This improvement applies to Shopware version 6.3.2.0 and higher.

**Bug fixes:**

* Shipping addresses are now shown correctly on picklist documents again.


## 2.8.1

### de

**Fehlerbehebungen:**

* Unter Shopware 6.3.2.0 kann der Bestellstatus einer Bestellung jetzt wieder automatisch auf "Abgeschlossen" gesetzt werden, wenn der Lieferstatus auf "Versandt" gesetzt wird und die E-Mail für den Lieferstatus "Versandt" wird wieder korrekt verschickt.
* Die manuelle Zuweisung eines Produktes zu einer Kategorie funktioniert nun wieder.

### en

**Bug fixes:**

* In Shopware 6.3.2.0 the order status of an order can now automatically be set to "Done" again and the delivery mail is sent correctly again when the delivery status is set to "Shipped".
* The manual assignment of a product to a category now works again.


## 2.8.0

### de

**Neue Funktionen und Verbesserungen:**

* Das Plugin unterstützt nun Shopware Version 6.3.2.0.

### en

**New features and improvements:**

* The plugin now supports shopware version 6.3.2.0.


## 2.7.0

### de

**Neue Funktionen und Verbesserungen:**

* In der Bestandsübersicht wird in der Ansicht *Je Produkt* ab sofort der "Meldebestand" eines Produkts angezeigt.
* In der Bestandsübersicht kann in der Ansicht *Je Produkt* ab sofort nach Produkten gefiltert werden, die ihren Meldebestand erreicht bzw. unterschritten haben.

### en

**New features and improvements:**

* The view *Per product* in the stock overview, now shows the "reorder point" of a product.
* In the stock overview, it is now possible to filter for products that have reached their reorder point.


## 2.6.0

### de

**Neue Funktionen und Verbesserungen:**

* In der Bestandsübersicht stehen ab sofort drei unterschiedliche Ansichten zur Auswahl, um den Bestand *Je Produkt* / *Je Produkt und Lager* / *Je Produkt und Lagerplatz* anzeigen und exportieren zu können.
* Das Plugin unterstützt nun Shopware Version 6.3.1.

**Anforderungen:**

* Das Plugin benötigt nun mindestens Shopware Version 6.3.1.0.

### en

**New features and improvements:**

* The stock overview now supports three different views to display and export the stock *Per product* / *Per product and warehouse* / *Per product and bin location*.
* The plugin now supports shopware version 6.3.1.

**Requirements:**

* The plugin now requires at least Shopware version 6.3.1.0.


## 2.5.1

### de

**Fehlerbehebungen:**

* Die Dokumenteneinstellungen unter *Einstellungen -> Dokumente* werden wieder vollständig geladen.

### en

**Bug fixes:**

* The document detail pages in the document settings are loaded properly again.


## 2.5.0

### de

**Neue Funktionen und Verbesserungen:**

* Im Reiter "Bestand" eines Produkts kann jetzt ein produktspezifischer Meldebestand gepflegt werden.
* In der Konfiguration des Plugins kann jetzt definiert werden, zu welchem Zeitpunkt und an wen täglich eine Liste aller Produkte, deren Lagerbestand den Meldebestand erreicht oder unterschritten haben, per E-Mail gesendet werden soll.
* Ein Klick auf einen beliebigen Lagerplatz in der Bestandsübersicht filtert die Bestandsübersicht ab sofort automatisch nach diesem.

### en

**New features and improvements:**

* A product specific reorder point can now be set in the "stock" tab of a product.
* In the configuration of the plugin, it is now possible to define at what time and to whom a list of all products that have reached their reorder point should be sent by email every day.
* Clicking on any bin location in the stock overview now filters the stock overview automatically after this.


## 2.4.0

### de

**Neue Funktionen und Verbesserungen:**

* Unter *Lagerhaltung -> Lager und Lagerplätze* können im Reiter *Lagerplätze* eines Lagers ab sofort Lagerplätze per CSV-Datei importiert werden.
* Sämtliche Varianteninformationen zu einem Produkt werden nun zusätzlich zum Produktnamen auf der Pickliste abgedruckt.
* Bestandsbewegungen, die einen negativen Lagerbestand in einem Lager oder auf einem Lagerplatz zur Folge hätten, sind ab jetzt nicht mehr möglich und führen zu einer entsprechenden Fehlermeldung.
  * **Wichtig:** Falls ein Produkt zum Zeitpunkt des Updates einen negativen Lagerbestand auf einem Lagerplatz hat, wird der Lagerbestand dort automatisch auf 0 gesetzt.
* Das Plugin kann jetzt optional unter Beibehaltung aller Daten deinstalliert werden.
* Das Plugin unterstützt nun Shopware Version 6.3.0 (ab Version 6.3.0.2).

**Fehlerbehebungen:**

* Beim Erstellen oder Bearbeiten eines Lagers wird nicht mehr automatisch "Deutschland" in der Adresse des Lagers vorausgewählt, wenn keine Lageradresse gewünscht ist.

**Anforderungen:**

* Das Plugin benötigt nun mindestens Shopware Version 6.3.0.2.

### en

**New features and improvements:**

* You can now import bin locations via CSV file in the *Bin locations* tab of a warehouse in *Warehousing -> Warehouses and bin locations*.
* All variant information for a product is now printed on the picklist in addition to the product name.
* Stock movements that would result in a negative stock in a warehouse or in a bin location are no longer possible from now on and lead to a corresponding error message.
  * **Important:** If a product has a negative stock in a bin location at the time of the update, the stock there is automatically set to 0.
* The plugin can now optionally be uninstalled while keeping all data.
* The plugin now supports Shopware version 6.3.0 (from version 6.3.0.2).

**Bug fixes:**

* Fixes a bug that caused the country "Germany" to be preselected (and therefore unknowingly saved) in the address of a warehouse.

**Requirements:**

* The plugin now requires at least Shopware version 6.3.0.2.


## 2.3.0

### de

**Neue Funktionen und Verbesserungen:**

* Im Reiter "Bestand" eines Produkts kann jetzt ein Standardlagerplatz je Lager festgelegt werden. Die Zuordnung eines Produkts zu seinem Standardlagerplatz bleibt auch dann erhalten, wenn der Bestand dort auf 0 fällt.
* Wird der Bestand eines Produkts per Shopware API bzw. Shopware Produktimport erhöht, wird der Bestand jetzt immer auf den Standardlagerplatz des Standardlagers eines Produkts gebucht.
* Wird der Bestand eines Produkts per Shopware API bzw. Shopware Produktimport verringert, wird jetzt die selbe Ausbuchungsstrategie angewandt wie beim Versenden einer Bestellung.
* Die Berechnung des reservierten Bestands eines Produkts erfolgt jetzt unter Berücksichtigung der bereits versendeten Positionen sowie der Bestell- und Lieferstatus der Bestellungen.
* Lagerplätze und Produktvarianten werden in der Bestandsübersicht jetzt deutlicher dargestellt.

**Voraussetzungen:**

* Das Plugin erfordert mindestens Shopware 6.2.3.

### en

**New features and improvements:**

* You can now define a default bin location per product and warehouse in the "Stock" tab of a product. The assignment of a product to its default bin location is retained even if the stock there falls to 0.
* If the stock of a product is increased via Shopware API or Shopware Import, the stock is now always posted to the default bin location of the default warehouse for a product.
* If the stock of a product is decreased via the Shopware API or Shopware Import, the same clearing strategy is now used as when shipping an order delivery.
* The calculation of the reserved stock of a product is now based on the items that are already shipped and the order and delivery status of the orders.
* Improved display of bin locations and product variants in the stock overview.

**Requirements:**

* The plugin requires at least Shopware 6.2.3.


## 2.2.0

### de

**Neue Funktionen und Verbesserungen:**

* Die Bestandsübersicht kann jetzt unter Berücksichtigung der gesetzten Filterparameter als CSV-Datei exportiert werden.
* Bestand wird jetzt ins ausgewählte Lager eingebucht, wenn der Status der Lieferung einer Bestellung auf "Retour" geändert wird.

**Fehlerbehebungen:**

* Produktnamen von Varianten werden jetzt korrekt auf der Pickliste dargestellt.

### en

**New features and improvements:**

* The (filtered) stock overview can now be exported as CSV file.
* Stock is now stored in the selected warehouse when the status of the order delivery state is changed to "Returned".

**Bug fixes:**

* Inherited product names of product variants are now displayed correctly on the picklist document.


## 2.1.0

### de

**Neue Funktionen und Verbesserungen:**

* __Highlight:__ Es ist nun möglich **Picklisten** für Bestellungen zu erstellen. Alle zu kommissionierenden Produkte werden dabei nach Lagerplatz sortiert aufgelistet.
* Bestand wird jetzt gemäß der Ausbuchungsstrategie von den Lagerplätzen ausgebucht, wenn der Status der Lieferung einer Bestellung auf "Versandt" geändert wird.
* In der Bestandsübersicht und weiteren filterbaren Ansichten lassen sich vorherige Filtereinstellungen nun per Browsernavigation wiederherstellen. Durch Kopieren der URL können die Filtereinstellungen jetzt auch mit anderen Personen geteilt werden.

**Fehlerbehebungen:**

* Produkte, für die bereits Bestandsbewegungen vorliegen, lassen sich nun wieder löschen und duplizieren.
* Bestellungen lassen sich nun wieder löschen, auch wenn sie bereits versendet wurden.
* Sofern einer Bestellung "Leere Positionen" oder "Gutschriften" hinzugefügt wurden, funktioniert die Statusänderung jetzt wieder ohne Fehlermeldung.
* Das Plugin kann jetzt auch aktualisiert werden während es deaktiviert ist.

### en

**New features and improvements:**

* __Highlight:__ It is now possible to create **picklists** for orders. All products to be picked are displayed sorted by bin location.
* Stock is now cleared from the bin locations according to the clearing strategy when the status of the order delivery is changed to "Shipped".
* In the stock overview and other filterable views, previous filter settings can now be restored via browser navigation. In addition, you can now share filter settings with others by copying the URL.

**Bug fixes:**

* Products that already have stock movements can now be deleted and duplicated.
* Orders can now be deleted even if they have already been shipped.
* If "custom items" or "credits" have been added to an order, the status change now works again without an error message.
* The plugin can now also be updated while it is deactivated.


## 2.0.0

### de

**Neue Funktionen und Verbesserungen:**

* Im Reiter "Bestellungen" eines Produkts kann die Liste der Kundenbestellungen jetzt nach Zahlungs-, Liefer- und Bestellstatus gefiltert werden.
* Ein Klick auf _Reservierter Bestand_ im Reiter "Bestand" eines Produkts öffnet nun die Liste der zu versendenden Kundenbestellungen zu diesem Produkt.
* Im Reiter "Bestand" werden bei der Auslagerung und Umlagerung von Produkten jetzt nur noch Lagerplätze zur Auswahl angeboten, auf denen das Produkt aktuell einen Bestand > 0 hat.
* Die unter _Einstellungen -> System -> Zusatzfelder_ angelegten Zusatzfeld-Sets können ab sofort auch für Lager verwendet werden.
* Das Plugin unterstützt nun Shopware Version 6.2.0.

**Fehlerbehebungen:**

* Der verfügbare Bestand eines Produkts wird jetzt sofort reduziert, wenn zu einer bestehenden Bestellung eine neue Bestellposition hinzugefügt wurde.

**Anforderungen:**

* Das Plugin erfordert nun mindestens Shopware Version 6.2.0.

### en

**New features and enhancements:**

* The list of all customer orders including a specific product can now be filtered by payment, delivery and order status in the "Orders" tab of that product.
* A click on _Reserved stock_ in the "Stock" tab of a product now opens the list of customers orders that have to be shipped for this product.
* Only bin locations with stock > 0 can now be selected when removing or transferring products in the "Stock" tab of a product.
* The additional field sets created in _Settings -> System -> Custom fields_ can now be used for warehouses as well.
* The plugin now supports Shopware version 6.2.0.

**Bug fixes:**

* The available stock of a product is now adjusted as soon as a new product has been added to an existing order.

**Requirements:**

* The plugin now requires at least Shopware version 6.2.0.


## 1.2.0

### de

**Neue Funktionen und Verbesserungen:**

* Im Reiter "Bestellungen" eines Produkts findet sich ab sofort eine Liste aller Kundenbestellungen, in denen das Produkt enthalten ist.
* Im Reiter "Bestand" eines Produkts findet sich ab sofort eine Übersicht des gesamten Lagerbestands, reservierten Bestands und verfügbaren Bestands des Produkts.
* Beim Ändern des Lieferstatus auf "Versandt" erfolgt nun eine Abfrage, um den Bestellstatus der Kundenbestellung gleichzeitig auf "Abgeschlossen" zu setzen.
* Unter "Lagerhaltung" —> "Lager und Lagerplätze" kann das Standardlager ab jetzt konfiguriert werden.

### en

**New features and enhancements:**

* A list of all customer orders including a specific product is now shown in the "Orders" tab of that product.
* An overview of the total stock, reserved stock and available stock of the product has been added to the "Stock" tab of a product.
* When changing the delivery status to "Shipped", a pop-up is now displayed to set the order status of the customer order to "Done" at the same time.
* The default warehouse can now be configured in "Warehousing" —> "Warehouses and bin locations".


## 1.1.0

### de

**Neue Funktionen und Verbesserungen:**

* Bestand wird jetzt automatisch aus dem gewünschten Lager ausgebucht, wenn der Status der Lieferung einer Bestellung auf "Versandt" geändert wird.
* Unter "Lagerhaltung" —> "Bestandsübersicht" findet sich ab sofort eine filterbare Übersicht des Bestands aller Produkte je Lagerplatz.
* Via CSV-Datei Import kann jetzt der Produktbestand im Standardlager angepasst werden (Kompatibilität mit Shopware Version 6.1.5).
* Via Shopware API kann jetzt der Produktbestand im Standardlager angepasst werden.

### en

**New features and enhancements:**

* Stock is now automatically cleared out of the desired warehouse when the status of an order delivery is changed to "Shipped".
* A filterable overview of all product stock per bin location has been added in "Warehousing" -> "Stock overview".
* Product stock can now be adjusted in the standard warehouse via CSV file import (compatibility with Shopware version 6.1.5).
* Product stock can now be adjusted in the standard warehouse via the shopware API.


## 1.0.0

### de

**Initiales Release mit folgenden Features:**

* Mehrere Lager verwalten
* Lagerplätze verwalten
* Chaotische Lagerhaltung (Mehrere Lagerplätze pro Artikel)
* Protokoll aller Warenbewegungen

### en

**Initial release with these features:**

* Manage multiple warehouses
* Manage bin locations
* Multiple bin locations per product (dynamic warehousing)
* Complete log of all stock movements
