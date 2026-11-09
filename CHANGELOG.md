# Changelog

## 1.8.0 — 2026-11-09
- **Gemischter Warenkorb:** WooCommerce-Rabattcodes für andere berechtigte Waren weiterhin zulässig, Geschenkgutscheine sind grundsätzlich aus der Rabattberechnung ausgeschlossen. Das löst die bislang pauschale Sperre aus v1.7.0 ab.
- Bei Prozent-, Festbetrags- und festen Warenkorbrabatten filtert `woocommerce_coupon_get_items_to_apply` Gutscheinpositionen vor der Rabattberechnung heraus; `woocommerce_coupon_get_discount_amount` dient als zusätzliche Sicherung und Fallback für ältere WooCommerce-Versionen.
- Sicherungen beim Hinzufügen, an der Kasse und bei der Bestellerstellung überprüfen weiter den vollständigen Gutschein-Nennwert. In reinen Gutschein-/Gratisprodukt-Warenkörben gibt es keinen rabattfähigen Artikel.
- Der bisherige Standard-Hinweistext wird passend zur neuen Warenkorbregel aktualisiert, ohne individuell bearbeitete Hinweise zu überschreiben.
- **Ein Hinweisfeld:** Der frei bearbeitbare Text „Wichtige Hinweise“ und die unveränderbare gesetzliche Widerrufsinformation erscheinen gemeinsam in einer Box, auf der Kaufseite und bei Gutscheinkäufen an der Kasse. Bestehende Texteinstellungen bleiben erhalten.
- Nicht zuordenbare negative Rabattgebühren von Drittplugins werden weiterhin vorsorglich blockiert, solange Gutscheine im Warenkorb sind; sie benötigen eine produktbezogene Integration.
- Keine Änderungen an Guthaben, Datenbankschema, Gratisprodukt-Plugin oder bestehenden Karten; Regressionstests für gemischte Warenkörbe ergänzt.

## 1.7.0 — 2026-11-09
- Kauf neuer digitaler Geschenkgutscheine mit WooCommerce-Rabattcodes (alle Coupontypen) oder negativen Rabattgebühren konsequent gesperrt, auch bei gemischten Warenkörben. Bereits aktive Coupons verhindern das Hinzufügen eines neuen Gutscheins und umgekehrt.
- Mehrfache Sicherheitsprüfung bis unmittelbar vor der Bestellerstellung stellt sicher, dass Gutscheinpositionen ausschließlich zum vollen Nennwert verkauft werden.
- Neue frei bearbeitbare Einstellung **„Wichtige Hinweise“** unter WooCommerce → Geschenkgutscheine → Einstellungen & Shortcodes. Sicher escaped und auf Gutschein-Kaufseite sowie im klassischen Checkout sichtbar; leerer Text blendet nur den Hinweis aus.
- Die Rabattsperre ist eine feste Funktion und kann nicht durch eine Textänderung deaktiviert werden; gesetzlicher Widerrufshinweis bleibt separat erhalten.
- Die Einlösung bereits ausgestellten Geschenkguthabens bei normalen Waren bleibt weiterhin mit Rabattcodes, Sonderangeboten und Sale-Artikeln kombinierbar. Bestehende Karten, Guthaben und das Gratisprodukt-Plugin sind unverändert.
- Neue Regressionstests für alle Coupontypen, Checkoutsperre, geänderte Gutscheinpreise, automatische negative Gebühren, Anzeigesicherheit und Ausnahmen für normale Produkte.

## 1.6.0 — 2026-11-08
- Bidirektionale Gutscheineinlösung: Alowidat-Geschenkguthaben nun auch über das reguläre WooCommerce-Rabattcodefeld im klassischen Warenkorb und in der klassischen Kasse. Bestehende WooCommerce-Coupons funktionieren weiterhin im Alowidat-Einlösefeld.
- Sichere Prüfung der nativen WooCommerce-Form- und AJAX-Nonces; Gutscheinguthaben wird nicht als Warenrabatt verarbeitet, sondern wie bisher nach Steuern und regulären Rabatten angerechnet.
- Neue Admin-Option zum Ausblenden des zusätzlichen Alowidat-Codefeldes ohne Verlust der Einlösefunktion. Standardanzeige bleibt aus Gründen der Rückwärtskompatibilität unverändert.
- Entfernen-Link in der Guthaben-Zeile auch bei ausgeblendetem Feld; Checkout-/Warenkorb-Aktualisierung nach dem Entfernen.
- Erweitertes Regressionstest-Set für native Formular- und AJAX-Eingaben, Standardcoupon-Fallback, optionale Anzeige, Session und das Entfernen.
- Gutscheindaten, andere Plugins, PHP-Anforderungen, Steuerbehandlung und WooCommerce-Block-Inkompatibilität unverändert.

## 1.5.0 — 2026-11-08
- Gemeinsames Feld im klassischen WooCommerce-Checkout, Warenkorb und Einlöse-Shortcode für **Geschenkgutscheine und normale WooCommerce-Rabattcodes**.
- Erkennt vorhandene Alowidat-Geschenkgutscheine und übergibt gewöhnliche Rabattcodes an die WooCommerce-Validierung mit allen vorhandenen Einschränkungen und Fehlermeldungen.
- Auch bei aktivem Geschenkgutschein bleibt die Eingabe sichtbar; mehrere normale Rabattcodes (soweit WooCommerce dies zulässt) sowie das Guthaben können gemeinsam verwendet werden.
- Einzelnes Entfernen von Geschenkguthaben und Rabattcodes direkt im Einlösebereich. Kein Verlust bereits angewendeter anderer Codes.
- Feste WooCommerce-Warenkorb-Rabatte werden beim Kauf eines neuen Geschenkgutscheins blockiert, da die normale Produktausschlussregel solche Rabatte nicht immer verhindert. Die Kombination von Rabattcodes mit bereits vorhandenem Geschenkguthaben bleibt möglich.
- Responsive Darstellung im bestehenden Schwarz-Gold-Stil; Gutschein-Datenbank, Steuern, bestehende Guthaben und das separate Gratisprodukt-Plugin unverändert.

## 1.4.0 — 2026-11-08
- Klarer Hinweis auf der Geschenkgutschein-Kaufseite und bei Geschenkgutscheinen im klassischen WooCommerce-Checkout: keine freiwillige Rücknahme, kein freiwilliger Umtausch und keine Barauszahlung von Guthaben außerhalb gesetzlicher Ansprüche.
- Das gesetzliche 14-tägige Widerrufsrecht für online gekaufte Wertgutscheine bleibt ausdrücklich erhalten. Kein unzulässiger pauschaler Ausschluss und keine irreführende "Digitalcode = kein Widerruf"-Klausel.
- Alowidat-konformes dezentes Gold-/Creme-Hinweisfeld, mobil optimiert.
- README mit Hinweisen zu geltender Widerrufsbelehrung, gesetzlichem Widerrufsbutton (seit Juni 2026) und manuell zu prüfender Rückabwicklung ergänzt.
- Gutscheinwerte, Steuern, Restguthaben, Kundenkonten, Adminfunktionen, vorhandene Karten und das separate Gratisprodukt-Plugin unverändert.


## 1.3.0 — 2026-11-08
- Das Feld „Wunschbetrag höchstens“ kann jetzt leer gespeichert werden; bisherige Eingaben wurden irrtümlich auf 500 € zurückgesetzt.
- Im Frontend bei leerem Maximum nur „Ab 20 €“ (bzw. konfigurierter Mindestbetrag), ohne „Zwischen … und …“ oder Maximal-HTML-Attribut.
- Serverseitige Validierung unterstützt das leere Maximum weiterhin mit dem bestehenden technischen Schutzlimit von 10.000 € pro Gutschein.
- Auf der Kaufseite wird klargestellt: Der Code ist übertragbar und kann von Gästen oder anderen WooCommerce-Kundenkonten verwendet werden. Die Einlöse- und Konto-Logik war bereits nicht an den Käufer gebunden.
- Vorhandene Gutscheinwerte, Karten, Guthaben, Rabattkombinationen und das separate Gratisprodukt-Plugin bleiben unverändert.

## 1.2.0 — 2026-11-08
- Bestehendes Gutscheinprodukt beim Update automatisch als virtuelles, nicht steuerpflichtiges und verborgenes Produkt korrigieren; keine Änderung der Produkt-ID oder vorhandener Gutscheine.
- Versandbedarf für das Alowidat-Gutscheinprodukt zusätzlich per WooCommerce-Filter ausschließen.
- Im Warenkorb „Digitale Zustellung“ statt irreführendem physischem „Versand“ anzeigen.
- Im Admin optionaler, explizit bestätigter Ein-Klick-Wechsel von WooCommerce Cart/Checkout Blocks zu den unterstützten klassischen Shortcodes; sichert vorherigen Seiteninhalt, verändert keine fremden Layouts.
- Zusammenspiel mit IT-Kayali Gratisprodukt v1.1.0 dokumentiert.

## 1.1.0 — 2026-11-08
- Neuer WooCommerce-Menüpunkt „Mein Guthaben“ im Kundenkonto zwischen Zahlungsarten und Kontodetails.
- Eigene hochwertige und responsive Guthabenübersicht im Alowidat-Stil mit Gesamtguthaben, Kartenstatus, Gültigkeit und ausklappbaren Codes.
- Guthabenschnellzugriff auf der Konto-Startseite sowie letzte 15 Gutscheinbewegungen.
- Empfangene Gutscheine werden über die E-Mail-Adresse des Kunden automatisch angezeigt; andere gültige Gutscheincodes können nach Anmeldung gespeichert werden.
- Sichere Codezuordnung: Nonce-Schutz, begrenzte Versuche, nur Karten-IDs im Benutzerprofil, keine Guthabenübertragung durch Speichern.
- Backend-Einstellung für die Gutscheinkauf-Seite und Farbstandard für Neuinstallationen auf #212121 / #d4b45c abgestimmt.
- Bestehende Gutscheine, Bestellungen, Rabatte und die Behandlung des Gutscheinverkaufs als Mehrzweckgutschein bleiben unverändert.
- Keine zusätzlichen 19 % auf bereits brutto ausgezeichnete, per Gutschein bezahlte Bestellungen.

## 1.0.0 — 2026-11-07
- Neue digitale Geschenkgutscheine mit 25/50/100 € und freiem Betrag ab 20 €.
- Moderner Frontend-Shortcode in Alowidat-Farbwelt mit dynamischer Kartenansicht.
- Empfänger-, Absender- und Grußnachricht im Gutscheinformular.
- WooCommerce-Gutscheinkauf als verstecktes virtuelles Produkt.
- Automatische, sichere Ausstellung nach bestätigter Zahlung und HTML-E-Mail.
- Teilguthaben, Bestandsabfrage, Gutschein-Einlösung und Kombination mit Rabattcodes/Sale.
- Separate, nachsteuerliche Anrechnung des Gutschein-Guthabens im klassischen Checkout.
- WooCommerce-Backend: Übersicht, Details, Transaktionshistorie, Sperrung, Wiederaktivierung, manuelle Ausgabe, erneuter E-Mail-Versand und Einstellungen.
- HPOS-Deklaration und Hinweise zu Checkout-Blocks, Steuern und Teilerstattungen.