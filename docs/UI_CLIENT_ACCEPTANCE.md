# TaskDate – gebündelte UI- und Client-Abnahme

**Stand:** 2026-11-27. **Kein Produktivtest.** Repository: `lfflr/Taskdate`, PR #1, Kandidat 0.2.3. Vor der Abnahme eine **separate Testinstanz** oder ausdrücklich isolierte Testnutzer und Testkalender einsetzen; die automatisierten Docker-Tests sind kein Nachweis für die Nextcloud-Tasks-Oberfläche.

## Ausgangslage protokollieren

- Nextcloud-Serverversion, Tasks-App-Version, Kalender-App-Version und PHP-Zeitzone dokumentieren.
- Browser- und Betriebssystemversion sowie tatsächliche Nutzerzeitzone (z. B. Europe/Berlin) erfassen.
- Aktiven Testbranch/Commit `release/appstore-readiness-2026-11-26` vor Installation notieren.
- Testkalender und Testnutzer ohne bestehende Produktivdaten verwenden.

## Gruppe A – Nextcloud Tasks und Kalender

| ID | Aktion in der **Tasks-Oberfläche** | Erwartung |
|---|---|---|
| A1 | `Zahnarzt 261112 1530` erstellen | Titel `Zahnarzt 261112`, Fälligkeit 12.11.2026 15:30 |
| A2 | `Termin 20261112 1405` erstellen | Uhrzeit 14:05, Titel `Termin 20261112` |
| A3 | `Ganztag 261112` erstellen | Datum ohne Uhrzeit, keine ungewollte Zeitzonenverschiebung |
| A4 | Im bereits bereinigten Titel `261112` durch `261113` ersetzen | Fälligkeit 13.11.2026, ursprüngliche Uhrzeit bleibt |
| A5 | Fälligkeit manuell setzen oder ändern und danach Titel verändern | Manuelle Fälligkeit wird nicht überschrieben |
| A6 | Automatisch gesetzte Fälligkeit manuell löschen | Die Fälligkeit wird nicht automatisch erneut gesetzt |
| A7 | Aufgaben im Kalender einblenden und Seite neu laden | Erwartete Fälligkeit erscheint, kein zusätzliches VEVENT |
| A8 | Aufgabe mit Startdatum oder Wiederholung anlegen | Wird vorerst durch TaskDate nicht verändert; Produktentscheidung dokumentieren |

**Besonders wichtig:** Ermitteln, ob Nextcloud Tasks bei *normalen* Aufgaben automatisch `DTSTART` mitsendet. Falls ja, würde der derzeitige Sicherheitsfilter auch diese Aufgaben ausnehmen; nicht stillschweigend als „bestanden“ werten.

## Gruppe B – externe CalDAV-Clients, Teilen und Rechte

- B1: Mit einem externen Client eine einfache VTODO anlegen, auf einem zweiten Client und in Nextcloud aktualisieren und die Werte vergleichen.
- B2: Eine VTODO mit `DUE;TZID=Europe/Berlin` und eine mit UTC-`DUE` aktualisieren. TaskDate muss diese bewusst **unverändert** lassen, bis die fachliche Zeitzonenabbildung freigegeben ist.
- B3: Privaten Kalender zwischen zwei Nutzern abgrenzen; fremde Schreibzugriffe verweigern. Dieser Basisfall ist bereits in Docker geprüft, UI-Darstellung aber noch offen.
- B4: Geteilte Kalender mit Schreibrecht und nur Leserecht getrennt testen. Bestehende Daten nicht verwenden.
- B5: Gleichzeitige Änderungen aus zwei Clients mit ETag-/Synchronisationskonflikt prüfen. Serverseitiger Basisfall bereits automatisiert bestanden.

## Gruppe C – Sommerzeit und Zeitzonen

- C1: `Termin 270328 0130` (Europe/Berlin, Beginn der Sommerzeit 2027) und `Termin 271031 0130` (Ende der Sommerzeit 2027) anlegen und sowohl im Browser als auch im externen Client vergleichen.
- C2: Lokale nicht existierende Stunde beim Wechsel in die Sommerzeit und doppelte Stunde beim Wechsel zurück **als offenen Sonderfall dokumentieren**, nicht als garantiert korrekt darstellen.
- C3: `heute`/`morgen` kurz vor und nach Mitternacht in der Benutzerzeitzone gegen Serverzeitzone prüfen.
- C4: Beim Wechsel zwischen Browser- und Client-Zeitzone darf eine explizit zonierte bestehende Fälligkeit nicht stillschweigend ersetzt werden.

## Ergänzung – relative Angaben

- D1: `heute`/`morgen` nahe der lokalen Mitternacht erstellen und sicherstellen, dass das Datum aus der Benutzerzeitzone stammt.
- D2: Eine automatisch verwaltete Aufgabe mit `morgen` am Folgetag erneut öffnen und speichern; die ursprünglich gesetzte Fälligkeit darf dadurch nicht unbemerkt um einen weiteren Tag wandern.
- D3: Wenn statt `morgen` ausdrücklich ein anderes relatives Wort eingegeben wird, dokumentieren, dass TaskDate bei bereits verwalteten relativen Titeln vorerst nicht neu berechnet; die Fälligkeit kann manuell angepasst werden.

## Ergebnisprotokoll

Für jede fehlgeschlagene Prüfung notieren: Test-ID, Nextcloud-/Tasks-Version, Originaltitel, erwartete und tatsächliche DUE/SUMMARY, Zeitpunkt, Zeitzone, Browser/Client, Logauszug **ohne personenbezogene Daten oder Zugangsdaten** und gegebenenfalls Screenshot.

Nur die **gebündelten Ergebnisse** zurückmelden. Vorher sind keine einzelnen manuellen Freigaben nötig. Ein erfolgreicher Durchlauf dieser Liste erlaubt noch kein automatisches App-Store-Release: App-ID, Rechte, Signaturzertifikat und signiertes Paket sind eigenständige Schritte.
