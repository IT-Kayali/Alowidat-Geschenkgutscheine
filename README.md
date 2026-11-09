# Alowidat Geschenkgutscheine

Eigenständiges WooCommerce-Plugin für **digitale Geschenkgutscheine** mit moderner Formularoberfläche, Empfänger-E-Mail, personalisierter Grußbotschaft, Restguthaben, Admin-Verwaltung und Steuer-neutraler Guthabenanrechnung.

**Version:** 1.7.0 · **Sprache:** Deutsch · **Währung:** EUR · **WordPress:** ab 6.4 · **PHP:** ab 7.4 mit OpenSSL · **WooCommerce:** ab 8.4

## Gutscheinrabatte im gemischten Warenkorb (seit v1.8.0)

- **Beim Gutscheinkauf ist der Nennwert immer vollständig zu bezahlen.** Geschenkgutscheine werden niemals rabattiert – weder mit prozentualen Produkt-Coupons noch mit festen Warenkorbrabatten.
- Enthält der Warenkorb auch normale rabattfähige Waren, können WooCommerce-Rabattcodes **nur auf diese anderen Waren** angewendet werden. Beispiel: 50 € Gutscheine + 25 € Parfüm + 10 % auf Parfüm = 72,50 € Warenwert vor weiteren Versandkosten/Steuern (bei Bruttopreisen). Die 50 € Gutscheinwert bleiben unverändert.
- Dafür werden Gutscheinpositionen mit `woocommerce_coupon_get_items_to_apply` aus der Rabattverteilung ausgeschlossen; zusätzliche Absicherung mit `woocommerce_coupon_get_discount_amount` und Prüfung der vollständigen Gutscheinposition bis zur Bestellerstellung.
- Ein Warenkorb, der nur Gutscheine und/oder Gratisartikel enthält, bietet keine rabattfähigen Produkte. Nicht produktbezogene negative Gebühren/Rabatte externer Plugins werden zur Sicherheit weiterhin gesperrt, solange Geschenkgutscheine enthalten sind. Solche Aktionen benötigen eine gesonderte Integration.
- Der bisherige Standard-Hinweistext wird automatisch in der Ausgabe auf die neue Regel aktualisiert; eigene Texte bleiben erhalten.
- Unter **Wichtige Hinweise** erscheint jetzt nur **eine** Hinweisbox, mit bearbeitbarem Zusatztext und dem gesondert erhaltenen gesetzlichen Widerrufshinweis innerhalb derselben Box. Text bleibt im Backend bearbeitbar.
- Bestehende Gutschein-Codes, Guthaben, Verfügbarkeit beider Einlösefelder und Kauf-/Checkout-Logik bleiben erhalten. Nur klassischer WooCommerce-Warenkorb/-Checkout unterstützt; WooCommerce Cart/Checkout Blocks weiterhin nicht unterstützt.

## Installation

1. Die ZIP `alowidat-geschenkgutscheine-v1.6.0.zip` unter **WordPress → Plugins → Plugin hochladen** installieren und aktivieren.
2. WooCommerce muss aktiviert sein; die Shopwährung muss auf **EUR** stehen.
3. Unter **WooCommerce → Geschenkgutscheine → Einstellungen & Shortcodes** Werte, Mindest-/Höchstbetrag und Farben konfigurieren. Wenn du den Höchstbetrag entfernst und speicherst, steht auf der Gutscheinseite nur „Ab 20 €“.
4. Eine Seite „Geschenkgutscheine“ erstellen und den Shortcode `[alowidat_gutscheine]` (Elementor-Shortcode-Widget oder WP-Shortcode-Block) einfügen.
5. **WooCommerce → Geschenkgutscheine → Einstellungen & Shortcodes:** Die Seite mit `[alowidat_gutscheine]` als **Gutscheinkauf-Seite** auswählen, damit der Kundenkonto-Link dorthin führt.
6. **Mein Konto → Mein Guthaben** wird automatisch hinzugefügt, nach „Zahlungsarten“ und vor „Kontodetails“. Ein weiterer Hinweis auf das Guthaben erscheint im Dashboard. Es ist **kein separater Shortcode** nötig.
7. Optional eine Seite zur Guthabenabfrage mit `[alowidat_guthaben]` erstellen.
8. **Warenkorb und Checkout müssen die klassischen WooCommerce-Shortcodes** `[woocommerce_cart]` und `[woocommerce_checkout]` verwenden. Cart-/Checkout-Blöcke sind in dieser Version **nicht** unterstützt. Unter **WooCommerce → Geschenkgutscheine → Einstellungen & Shortcodes** gibt es einen optionalen, bestätigungspflichtigen Umstellungsbutton für die WC-Blockseiten. Alte Seiteninhalte werden vorher gesichert; andere Seiten bleiben unverändert.
9. Mit einer echten Testbestellung (Testmodus des Zahlungsdienstleisters) E-Mail, Gutscheinkauf, Einlösung, Restguthaben, Rabatte und Steuern überprüfen.

## Funktionen

- Frontend mit dunkler Luxus-Geschenkkarte, warmen Goldakzenten und responsive Formularelementen.
- Bereits vorhandene Gutschein-WooCommerce-Produkte werden automatisch auf `virtuell = ja`, `Steuerstatus = none` und `Katalogsichtbarkeit = verborgen` migriert, um Versandkosten und Steuer beim Kauf zu vermeiden (Mehrzweckgutschein vorausgesetzt).
- Digitaler Versand wird im Warenkorb als **Digitale Zustellung** statt als physischer Versand gekennzeichnet.
- **Zusammen mit IT-Kayali Gratisprodukt ab Version 1.1.0**: Kein Gratisprodukt und kein Versand bei reinen Gutscheinbestellungen; normale Produktkäufe bleiben unverändert.
- **Neuer WooCommerce-Kundenkonto-Bereich „Mein Guthaben“** mit responsive Guthabenkarte, Gesamtwert, Einzelkarten, Gültigkeit, aufklappbaren Gutscheincodes und maximal 15 letzten Guthabenbewegungen.
- Automatische Aufnahme der Gutscheine, die an die Kontoe-Mail-Adresse des eingeloggten Kunden verschickt wurden.
- Fremd erhaltene Codes können per Nonce-geschütztem Formular manuell der Kundenübersicht hinzugefügt werden; gespeichert werden nur die Karten-IDs, nicht der Klartextcode. Rate-Limiting verhindert Massenversuche.
- Das Hinzufügen eines Gutscheins zum Konto macht aus dem Inhaber-Code kein exklusives Kundenkonto-Guthaben: Der Gutscheincode selbst bleibt ein einlösbares Inhaber-Instrument. Einlösen dürfen sowohl Gäste als auch angemeldete Benutzer anderer Konten; die Empfänger-E-Mail-Adresse dient dem Versand und der automatischen Anzeige im Kundenkonto, **nicht** einer verpflichtenden Konto-Bindung.
- Dashboard-Hinweis mit Gesamtguthaben und Link zum neuen Bereich; Position des Menüpunkts nach „Zahlungsarten“, falls verfügbar, ansonsten vor „Kontodetails“.
- Konfigurierbare Gutscheinkauf-Seite im Admin, sodass der Kundenkonto-Button auf die richtige Shop-Seite zeigt; Standardeinstellungen für neue Installationen an Screenshot angepasst (dunkel #212121, Gold #d4b45c). Bereits gespeicherte Farbeinstellungen bleiben unberührt.
- Feste Nennwerte **25 / 50 / 100 €**, frei wählbarer Betrag **ab 20 €** bis zu einem optional konfigurierbaren Maximum (Standard für bestehende Konfigurationen: 500 €). **Maximalfeld leer lassen**: Im Frontend erscheint ausschließlich **„Ab 20 €“**; die Eingabe besitzt keine individuell konfigurierte Obergrenze. Das bisherige technische Sicherheitslimit von **10.000 € je Gutschein** bleibt bestehen.
- Wahl zwischen Versand an eigene Rechnungs-E-Mail-Adresse oder andere Person mit E-Mail, Name und Nachricht.
- Gutscheine werden **erst nach bezahlter Bestellung** (processing/completed) erstellt und per HTML-E-Mail verschickt.
- **Gemeinsames Einlösefeld für Alowidat-Geschenkgutscheine und normale WooCommerce-Rabattcodes** im klassischen Warenkorb und Checkout, plus `[alowidat_gutschein_einloesen]`.
- Eingabe erkennt zuerst aktive bzw. vorhandene Alowidat-Geschenkgutscheine. Andere Codes werden mit WooCommerce `WC()->cart->apply_coupon()` geprüft; WooCommerce-Einstellungen und Einschränkungen für Rabattcodes bleiben wirksam.
- Auch bei bereits aktivem Geschenkguthaben bleibt das Eingabefeld sichtbar, damit Kunden weitere Rabattcodes hinzufügen können. Aktive Codes können unabhängig voneinander über eigene Entfernen-Buttons abgewählt werden.
- Rabattcodes werden zuerst auf die normalen Waren-/Steuerwerte angewandt; das Geschenkguthaben deckt anschließend den verbleibenden Zahlbetrag. Für Geschenk-Guthaben gelten keine Sale-Ausschlüsse. Bedingungen normaler WooCommerce-Rabattcodes (z. B. individuelles Verwenden, Mindestbestellwert oder Produktbindung) werden weiterhin eingehalten.
- Uneingeschränkt mit **normalen Rabattcodes, Sale-Artikeln und Aktionen kombinierbar**. Rabatte laufen vor der Guthabenanrechnung.
- Einlösung als **Guthaben/Bezahlung**; Guthaben wird erst nach Rabatt- und Steuerberechnung vom Brutto-Gesamtbetrag abgezogen.
- Der Kauf weiterer Gutscheine mit Gutscheinguthaben ist aus Missbrauchsschutz ausgeschlossen. Normale Rabattcodes dürfen den Verkauf eines neuen Geschenkgutscheins nicht verbilligen. Auch feste Warenkorb-Rabattcodes wirken nur auf rabattfähige normale Waren, niemals auf den Gutschein-Nennwert.
- Mehrfache Verwendung bis zur vollständigen Aufbrauchung, mit Transaktionsprotokoll.
- Gutschein erst bei bezahltem Auftrag aktiv, Code per 96-Bit-Zufall, HMAC-Suchindex, AES-256-GCM verschlüsselt in der Datenbank.
- Atomares Reservieren per InnoDB-Transaktion, Gutschrift bei Stornierung/Fehlschlag/Vollerstattung.
- Admin: Kennzahlen, Kartensuche, Einzelansicht mit Transaktionen, E-Mail erneut senden, aktivieren/sperren, manuelle Ausgabe, Farben/Preise konfigurieren.
- HPOS-kompatible WooCommerce-Bestell-API; WooCommerce-Standardfunktionen für Produkte/Bestellungen.
- Kaufgutscheine: Laufzeit standardmäßig bis zum Ende des dritten Folgekalenderjahrs (§ 195, § 199 BGB), ausgehend vom Ausgabejahr. Individuelle rechtliche Einordnung prüfen.

## Geschenkgutscheine zum vollen Nennwert verkaufen (seit v1.8.0)

- Gutscheine werden immer zum vollen Nennwert verkauft, **gleichzeitig dürfen Rabattcodes auf andere berechtigte Waren im selben Warenkorb angewendet werden**.
- Geschenkgutscheine werden vor der Berechnung von Prozent-, Produkt- und festen Warenkorbrabatten aus der Rabattberechnungsmenge ausgeschlossen. Zusätzlich wird vor Bestellerstellung geprüft, dass Gutscheinpreis und Nennwert übereinstimmen.
- Ausschließlich Gutscheine und kostenlose Gratisproben berechtigen nicht zum Einlösen normaler Rabattcodes. Nicht produktbezogene negative Gebühren aus Drittanbieter-Promotionen bleiben vorsorglich gesperrt, sobald Geschenkgutscheine im Warenkorb liegen. Dafür wäre eine separate Produktzuordnung nötig.
- **Wichtige Hinweise** lassen sich im Backend selbst bearbeiten. Auf Kaufseite und Kasse ist nur ein gemeinsames Hinweisfeld sichtbar; die gesetzliche Widerrufsinformation bleibt darin enthalten, selbst wenn der individuelle Zusatztext leer ist.
- Bereits ausgestellte Geschenkgutscheine bleiben weiterhin mit normalen Rabattcodes, Sale-Produkten und Aktionen kombinierbar; Einlösung ist keine neue Warenrabattierung.

## Beide Gutscheinfelder verwenden (seit v1.6.0)

- Das **normale WooCommerce-Gutscheinfeld** (im klassischen Warenkorb und Checkout) akzeptiert nun auch gültige Alowidat-Geschenkgutscheine. Dafür werden beide standardmäßigen WooCommerce-Verarbeitungswege unterstützt: Warenkorb-POST (`apply_coupon`) sowie Kassen-AJAX (`wc-ajax=apply_coupon`); bestehende WooCommerce-Rabattcodes bleiben unverändert WooCommerce-Coupons.
- Geschenkguthaben wird **nicht als gewöhnlicher Rabattcode** behandelt: Weiterhin nach Rabatt- und Steuerberechnung als Guthaben-/Zahlbetragsreduktion, mit unverändertem Bestellguthaben und Restsaldo.
- **Backend → WooCommerce → Geschenkgutscheine → Einstellungen:** Das zusätzliche Alowidat-Einlösefeld lässt sich ein-/ausblenden. Standardmäßig bleibt die bisherige Anzeige aktiviert. Auch bei ausgeschaltetem Zusatzfeld funktioniert das offizielle WooCommerce-Feld weiterhin für beide Codearten, sofern die WooCommerce-Couponfunktion in WooCommerce → Einstellungen → Allgemein aktiviert ist.
- Bei aktivem Guthaben erscheint in der Warenkorb-/Checkout-Summe zusätzlich ein **Entfernen-Link** mit Nonce, damit die Karte auch ohne Alowidat-Zusatzfeld wieder gelöst werden kann.
- `woocommerce`-Checkout-Blöcke und Cart-Blöcke sind **nicht** unterstützt. Die native Coupon-Einlösung gilt für klassische WooCommerce-Formulare, nicht für Store-API-Block-Coupons.
- Ungültige WooCommerce-Coupons werden weiter vom WooCommerce-Kern geprüft. Das bestehende Plugin-Feld nimmt weiterhin beide Codearten entgegen. Pro Einkauf bleibt eine aktive Alowidat-Geschenkkarte möglich; normale Coupons können soweit von WooCommerce erlaubt gleichzeitig aktiv sein.

## Rückgabe, Widerruf und Barauszahlung (seit v1.4.0)

- Auf der Gutscheinkauf-Seite und im **klassischen Checkout** bei Gutscheinbestellungen erscheint ein gut sichtbarer Hinweis: **„Beim Onlinekauf von Geschenkgutscheinen gilt grundsätzlich das gesetzliche 14-tägige Widerrufsrecht. Darüber hinaus bieten wir keine freiwillige Rücknahme oder einen Umtausch an. Eine Barauszahlung des Gutschein- oder Restguthabens ist, außer bei gesetzlichen Ansprüchen, ausgeschlossen.“**
- **Wichtig:** Es gibt **keinen pauschalen Ausschluss des gesetzlichen Widerrufsrechts**. Der digitale Gutschein-Code ist kein automatisch widerrufsfreier Download. Der Hinweis allein ersetzt auch **nicht** die rechtlich erforderlichen Widerrufsbelehrungen und AGB des Shops.
- **Keine zwingende Verzichts-Checkbox** und kein automatisches Erlöschen des gesetzlichen Widerrufsrechts bei E-Mail-Versand des Codes.
- Die Plugin-Einlöselogik, Bezahlung, die Guthaben- und Erstattungsfunktionen bleiben unverändert. Bei wirksamem Widerruf muss der Händler den Gutschein sperren und die tatsächlich noch vorhandenen bzw. bereits verwendeten Beträge für die Rückabwicklung prüfen. **Keine automatische Ablehnung** von Refund-/Widerrufsanfragen im Plugin.
- Seit **19. Juni 2026** ist im deutschen Onlinehandel für widerrufbare Online-Verträge eine elektronische Widerrufsfunktion („Widerrufsbutton“) gesetzlich vorgeschrieben; **das ist eine websiteweite Funktion** und wird **nicht** von diesem Gutschein-Plugin bereitgestellt. Shopweite rechtliche Umsetzung separat sicherstellen.
- Quelle zur Einordnung des Gutschein-Widerrufs: https://business.trustedshops.de/blog/legal/widerrufsrecht-wie-werden-eigentlich-gutscheine-behandelt; Gesetz: § 312g, §§ 355 ff. BGB. Rechtstexte sollten vor Veröffentlichung rechtlich geprüft werden.

## Steuerhinweis

**Wichtig:** Der Gutscheinverkauf legt ein WooCommerce-Produkt mit `tax_status = none` an. Das ist **ausschließlich** für die Behandlung als **Mehrzweckgutschein** gedacht, bei dem die Umsatzsteuer erst bei der Einlösung auf die zugrunde liegende Lieferung entsteht. Die rechtliche Einordnung ist abhängig von den tatsächlich angebotenen Produkten, Lieferländern und steuerlichen Verhältnissen. **Vor Live-Nutzung mit Steuerberatung bestätigen lassen.**

**Steuerbeispiel (Mehrzweckgutschein):** Ein Kunde kauft einen Gutschein für 50 € und bezahlt 50 € ohne Steuerberechnung beim Gutscheinkauf. Bei einer späteren Bestellung über 50 € **inklusive** deutscher Umsatzsteuer von 7,98 € kann er diese vollständig mit dem Gutschein begleichen. Für den Kunden entstehen dadurch keine zusätzlichen 19 %; die Steuer ist bereits in der Ware enthalten und fällt bei deren Verkauf an. Bei anderen EU-Lieferländern kann ein anderer Steuersatz gelten.

**Restbetrag ≠ Rabatt:** Das Plugin vermindert bei der Gutscheineinlösung den Zahlbetrag *nach* der normalen Waren-/Versandsteuer und nach Rabattcodes. Die Waren- und Umsatzsteuersummen werden nicht nachträglich um den Gutscheinbetrag reduziert. Es fügt der Bestellung zur Nachvollziehbarkeit eine nicht steuerpflichtige negative Guthabenposition hinzu; entsprechende Rechnungs-, ERP-, Buchhaltungs- und Zahlungsdienstleister-Plugins müssen darauf getestet werden.

## Wichtige Grenzen / Tests vor Live-Betrieb

- Nur **klassischer** Warenkorb/Checkout; **WooCommerce Blocks** aktuell nicht kompatibel.
- **Nicht** mit externen Store-Credit-, Gutschein- oder Warenkorbsummen-Modifikationsplugins ohne Test kombinieren.
- Gutschein-Codes in WooCommerce-PDF-Rechnungen müssen bei Bedarf separat geprüft werden.
- **Teilretouren/Teilerstattungen:** Eine automatisch anteilige Wiederaufladung wird bewusst nicht vorgenommen, da sonst falsche Erstattungen drohen. Es wird stattdessen eine Bestellnotiz angelegt und manuelle Prüfung verlangt.
- Bei **Erstattung eines verkauften Gutscheins** wird die Karte gesperrt. Bereits verbrauchtes Guthaben muss vor Auszahlung manuell berücksichtigt werden.
- Einlösen von Gutscheinwert in Gutschein-Geschenkkarten ist ausgeschlossen; Guthaben kann aber für alle normalen Shop-Produkte inkl. Sale und deren Versandkosten verwendet werden.
- Bei unvollendeten Bestellungen bleibt Guthaben bis zur Stornierung/Fehlerstatus reserviert; WooCommerce-Zeitlimits für unbezahlte Bestellungen sinnvoll konfigurieren und lange offene Zahlungsaufträge prüfen.
- Online-Checkout, Zahlungsgateways, externe Rechnungssoftware, steuerliche OSS/Mehrzweckgutschein-Verarbeitung, PHP-SMTP und WooCommerce-E-Mail-Zustellung benötigen reale Integrationstests.
- Der Gutschein-Code wird mit WordPress-Salts abgeleitet; diese **nicht ohne Migration ändern**, da Codevalidierung und Entschlüsselung sonst scheitern.
- Gutscheine und Transaktionsdaten **bleiben beim Deinstallieren erhalten**, um kein Kunden-Guthaben zu löschen.
- Die interne Gutschrift-Anrechnungszeile ist eine **nicht steuerbare Zahlungsanrechnung**, auch wenn sie in WooCommerce als negative nicht-steuerpflichtige Bestellgebühr erfasst ist; kaufmännische Darstellung mit Rechnungsplugin prüfen.

## Shortcodes

`[alowidat_gutscheine]` – Geschenkgutschein kaufen  
`[alowidat_guthaben]` – Guthaben prüfen  
`[alowidat_gutschein_einloesen]` – optionales Einlösefeld

**Mein Konto → Mein Guthaben:** Automatisch registrierter WooCommerce-Endpunkt `geschenkguthaben`; benötigt keinen Shortcode. Falls nach Plugin-Update ein 404 erscheint, einmal unter WordPress → Einstellungen → Permalinks auf „Änderungen speichern“ klicken.

**Wichtig:** Nur angemeldete Nutzer sehen ihren Kontobereich; Guthaben anderer Konten wird nicht angezeigt. Geschenkgutscheine sind übertragbare Inhabercodes und ihre Weitergabe erlaubt auch Dritten die Einlösung. Eine auf 200 Karten je Kunde begrenzte Zuordnung und die letzten 15 Bewegungen reduzieren die Datenmenge.

## Technische Architektur

`includes/class-alw-gift-db.php` – Tabellen, Ausstellen, Code-Sicherheit, Reservierungen & Transaktionen  
`includes/class-alw-gift-cards.php` – WooCommerce Integration, Shortcodes, E-Mails, Frontend  
`includes/class-alw-gift-admin.php` – WordPress Backend
`includes/class-alw-gift-account.php` – Kundenkonto-Endpunkt, Kartenübersicht, Code-Zuordnung, Historie  
`assets/` – Stile und dynamische Formularvorschau

## Lizenz

GPL-2.0-or-later. Für professionelle Nutzung vor Live-Betrieb Staging-Test und steuerliche Prüfung durchführen.

## GitHub: Quellcode und automatisch installierbare ZIP

- Dieses Repository enthält die **entpackten Plugin-Dateien**. Der Plugin-Einstiegspunkt `alowidat-geschenkgutscheine.php` liegt im Repository-Hauptverzeichnis.
- Bei einem Push auf `main` und bei manuellem Start unter **Actions → WordPress ZIP und GitHub Release → Run workflow** prüft GitHub Actions alle PHP-Dateien, die Versionsnummern und baut ein ZIP mit **genau einem Plugin-Hauptordner**.
- Wenn für die Version noch kein Release existiert, erstellt die Action automatisch das Release `vX.Y.Z` mit der **direkt in WordPress installierbaren** Datei `alowidat-geschenkgutscheine-vX.Y.Z.zip` unter **Releases → Assets**.
- **Wichtig:** Nicht das von GitHub automatisch angebotene **Source code (zip)** installieren. Dieses enthält auch Repository-Dateien und nicht das geprüfte WordPress-Paket.
- Für **jede weitere funktionierende Codeänderung** die Versionsnummer im Plugin-Header, in `ALW_GC_VERSION`, `README.md` und `CHANGELOG.md` anpassen. Existiert die Version schon als Release, bricht der Workflow absichtlich ab, statt zwei verschiedene ZIP-Inhalte unter derselben Versionsnummer zu veröffentlichen.
- Lokal prüfen und packen: `bash tools/build-release.sh` (benötigt `bash`, `php`, `zip`, `unzip`). Das ZIP liegt danach unter `dist/`.
- **Nicht ins Repository**: Kundendaten, Gutscheincodes, Datenbank-Dumps, Zugangsdaten oder WooCommerce-Konfigurationsdateien.
- Der Workflow baut und veröffentlicht Release-ZIPs. **Er installiert keine Plugins automatisch auf alowidat.de.** Ein WordPress-Update erfolgt weiterhin bewusst über das WordPress-Backend.

### Pflege neuer Versionen

1. Änderungen an den entpackten Plugin-Dateien durchführen und bestehende Funktionen erhalten.
2. PHP-Syntax, Checkout, Einlösung, Guthaben und Rückerstattungsfälle testen.
3. Versionsnummer und Changelog konsistent aktualisieren und nach `main` pushen.
4. Unter **Actions** den erfolgreichen Release-Lauf prüfen und unter **Releases → Assets** das WordPress-ZIP herunterladen.