# HANDOVER – TaskDate

**Aktualisiert:** 2026-11-28  
**Repository:** https://github.com/lfflr/Taskdate  
**Main-Ausgangspunkt des Arbeitspakets:** `d4e9a2509e08752f02e92d21e647b9b0bedbc235` (0.2.2).  
**Entwicklungsbranch:** `release/appstore-readiness-2026-11-26`  
**PR:** https://github.com/lfflr/Taskdate/pull/1 (Entwurf; nicht mergen ohne NC35-Abnahme).  
**App-Version des Kandidaten:** 0.2.3.  
**Verifizierter Mehrbenutzer-Code-Stand:** `8c5b516516d8907e7ab80f01a45d2a52158b8783`, Nextcloud 35 einschließlich ACL/ETag/RRULE [Run #10](https://github.com/lfflr/Taskdate/actions/runs/46059313311) bestanden.
**Verifizierter Versionsmatrix-Code-Stand:** `081b7e71bed073b24c026694abc9e0acec7520c3`, direkte CalDAV-Tests für Nextcloud 33/34/35 [Run #14](https://github.com/lfflr/Taskdate/actions/runs/46059594089) vollständig bestanden.  
**Dokumentationsstand:** Aktualisiert nach grünem NC33–35-Matrixlauf; finalen GitHub-Branch-HEAD nach dieser HANDOVER-Aktualisierung gesondert prüfen.

## Übernommene Arbeit

1. Bisheriges post-save `CalendarObjectCreatedEvent`/`CalendarObjectUpdatedEvent`-Handling einschließlich direktem `OCA\\DAV\\CalDAV\\CalDavBackend`-Zugriff aus dem Branch entfernt.
2. DAV-Anbindung über `appinfo/info.xml` (`types/dav`, `sabre/plugins`), geladen durch den Nextcloud-35-DAV-PluginManager; `TaskDateSabrePlugin` verarbeitet passende `PUT`-Anfragen über `method:PUT` mit Priorität 60 vor dem regulären CorePlugin-Schreibhandler; Nextcloud lädt Drittanbieter-Plugins erst während `beforeMethod:*`. Der zunächst erprobte `OCP\\SabrePluginEvent`-Listener wurde entfernt, da er auf dem normalen DAV-Pfad nicht aufgerufen wird.
3. `TaskUpdatePlanner` kapselt Aufgabenumwandlung; manuelle DUE, geänderte oder gelöschte Fälligkeit, idempotente Updates, Titelbereinigung und automatische Marker berücksichtigt.
4. `TaskWritePolicy` schützt vor Änderungen an DTSTART- und Wiederholungsaufgaben. Dies ist bewusst eingeschränkt.
5. CalDAV-HTTP-Request-Tests mit echten SabreDAV- und VObject-Klassen einschließlich Stream-Body-Reparatur hinzugefügt.
6. LICENSE wurde nicht neu lizenziert; `appinfo/info.xml` zeigt nun `GPL-3.0-only` passend zu GPL-3.0 im Repository; `<types><dav/></types>` entspricht der Nextcloud-35-App-Deklaration.
7. Release-Staging, statisches API-/Lizenz-Gate, README und `docs/APPSTORE_RELEASE.md` aktualisiert.
8. Ausführlicher P1-P3-Plan in `docs/DEVELOPMENT_PLAN.md`.

## Verifizierte CI-Nachweise

- https://github.com/lfflr/Taskdate/actions/runs/46029191536 – realer SabreDAV-PUT-Request-Test und statisches API-/Lizenz-Gate bestanden.
- https://github.com/lfflr/Taskdate/actions/runs/46029284782 – Stream-Body-Regressionsfälle bestanden.
- https://github.com/lfflr/Taskdate/actions/runs/46029347331 – Regression nach manuell gelöschtem DUE bestanden.
- https://github.com/lfflr/Taskdate/actions/runs/46029894457 – deklarative DAV-Registrierung, echter SabreDAV-PUT-Test und Paketprüfung bestanden.
- https://github.com/lfflr/Taskdate/actions/runs/46030136176 – `method:PUT`-Prioritätsreihenfolge (vor Sabre CorePlugin), PHP-Syntax, Parser, Planner, Stream, Paket und statisches Release-Gate bestanden. Geprüfter Code-SHA: `bbf37072be9fe91ce2e12538150ddfa8069607d3`.
- https://github.com/lfflr/Taskdate/actions/runs/46033104910 – PHP-/SabreDAV-/Paketprüfung nach XML-Korrekturen bestanden.
- https://github.com/lfflr/Taskdate/actions/runs/46033274589 – **erfolgreicher echter End-to-End-Test** auf frischer Nextcloud **35.0.3.2**: Installation/Aktivierung von TaskDate, CalDAV-PUT/GET für automatisches DUE und Titel, Datumsänderung, manuellen Schutz, ganztägiges DUE, manuelle DUE-Entfernung, DTSTART und ungültige Authentifizierung.
- https://github.com/lfflr/Taskdate/actions/runs/46059313311 – zweiter Nicht-Admin-Nutzer, zwei eigene Kalender, Verbot fremder Schreibzugriffe, ETag-Preconditions und RRULE-Schutz bestanden.
- https://github.com/lfflr/Taskdate/actions/runs/46059594089 – **NC 33, NC 34, NC 35 jeweils bestanden** in eigener Docker-Instanz mit allen automatisierten CalDAV-Funktionstests.
- https://github.com/lfflr/Taskdate/actions/runs/46059594103 – PHP-/SabreDAV-Tests vor abschließender Workflow-Auslöserbereinigung bestanden.
- https://github.com/lfflr/Taskdate/actions/runs/46059735589 – **erneute vollständige NC33/34/35-Matrix nach CI-Trigger-Änderungen: 3/3 erfolgreich**.
- https://github.com/lfflr/Taskdate/actions/runs/46059735429 – PHP-/SabreDAV-/Paket-/Release-Gate nach CI-Trigger-Änderungen erfolgreich.

Die echte Nextcloud-33–35-Serverintegration ist in isolierten Docker-Tests bestanden; sie ersetzt **keine** UI-/Client-/Zeitzonen-/Freigabe-Rechte-Abnahme. Fehlgeschlagene E2E-Vorläufe #1 (falsche MKCALENDAR-Namespace) und #2 (Apache noch nicht HTTP-bereit) wurden im Testaufbau korrigiert; der abschließende erweitere Lauf #4 ist grün.

## Geänderte/neu angelegte Hauptdateien im größeren Block

- `appinfo/info.xml` – Sabre-Plugin deklarativ über `types/dav` und `sabre/plugins` registriert.
- `lib/AppInfo/Application.php` – kein konkurrierender Event-Listener mehr.
- `lib/Sabre/TaskDateSabrePlugin.php` – HTTP-PUT-Verarbeitung mit Stream-Recovery.
- `lib/TaskUpdatePlanner.php` – reine Logik.
- `lib/TaskWritePolicy.php` – konservative Beschränkungen.
- `lib/DateParser.php` – bisherige Erkennung inklusive kompakter HHMM.
- `tests/task_update_planner_test.php`, `tests/sabre_put_test.php`, Parser/Policy-Tests – Regressionen.
- `.github/workflows/php-checks.yml`, `composer.json` – automatisierte Tests (Composer nur dev).
- `scripts/prepare-release.sh`, `scripts/check-release-blockers.sh`, `tests/release_staging_test.sh` – Paket/Gate.
- `appinfo/info.xml`, `README.md`, `docs/APPSTORE_RELEASE.md`, `docs/DEVELOPMENT_PLAN.md`.
- **Entfernt:** `lib/Listener/TaskDateListener.php`, `lib/Listener/RegisterSabrePluginListener.php`, `lib/CalendarPayload.php`, `tests/calendar_payload_test.php`.

**Migrationen:** keine; keine Datenbankänderungen und keine produktiven Daten betroffen.

## Neues Integrationspaket (2026-11-27)

- Neu: `tests/nextcloud35_e2e.sh` und `.github/workflows/nextcloud35-e2e.yml` – GitHub Actions führt echte CalDAV-Requests in einer wegwerfbaren Nextcloud-35-Installation aus.
- `appinfo/info.xml` verwendet jetzt die gültige DAV-Type-Kennzeichnung `<dav/>` und die explizite SPDX-Lizenz `GPL-3.0-only`.
- `tests/release_staging_test.sh` validiert die korrigierte App-Registrierung.
- Die Installations-/HTTP-Readiness-Schritte wurden getestet; ein gesonderter Apache-Startcheck vermeidet Verbindungsabbrüche.
- **Keine Migrationen** und **keine Änderungen an produktiven Daten**.
- Verifizierter Code-SHA: `c5e164e16c05d20bbdd65793f77f10a6d5ae43d7`.

## Sicherheits- und Versionsmatrixpaket (2026-11-27)

- GitHub Actions testet jetzt getrennte Instanzen von Nextcloud 33, 34 und 35. Alle drei Jobs der [Matrix #14](https://github.com/lfflr/Taskdate/actions/runs/46059594089) abgeschlossen und erfolgreich.
- `tests/nextcloud35_e2e.sh` prüft jetzt auch normalen Nicht-Admin-Account, eigene zweite Kalenderinstanz, Cross-User-ACL, abgewiesene veraltete ETags und das Nicht-Umschreiben von RRULE-Aufgaben.
- `.github/workflows/nextcloud35-e2e.yml`: Versionenmatrix, zweiter Testnutzer; Prüfungen nur bei relevanten App-Dateiänderungen. `.github/workflows/php-checks.yml`: nur relevante App-/Test-/Skriptänderungen statt Dokumentationsänderungen.
- **Keine Migrationen**, keine produktiven Daten verändert, kein Release oder Merge.
- Weitere technische Tests (gemischte Zeitzonen, Sommerzeit, geteilte Kalender, externer Client) und tatsächliche Nextcloud-Tasks-Oberfläche sind **nicht** bestanden oder ausgeführt.

## Weiteres Entwicklungspaket – Zeitzonenschutz (2026-11-27)

- `lib/Sabre/TaskDateSabrePlugin.php` schützt nun explizite DUE-Zeitzonen (`TZID` oder UTC-`Z`), eingebettete `VTIMEZONE`, gemischte `VEVENT`/`VTODO`-Dateien und Kalender mit mehreren `VTODO`-Komponenten vor einer teilweisen oder falschen Umschreibung. Die Originaldaten werden unverändert an Nextcloud weitergegeben.
- `tests/sabre_put_test.php` enthält dazu fünf zusätzliche Tests für fremde Zeitzonen-/Mehrkomponenten-Dateien.
- [PHP-/SabreDAV-Lauf #109](https://github.com/lfflr/Taskdate/actions/runs/46060744527) erfolgreich.
- [NC33–35-Docker-CalDAV-Matrix #23](https://github.com/lfflr/Taskdate/actions/runs/46060744540) erfolgreich: alle drei Versionen mit echtem HTTP-Integrationslauf.
- `docs/UI_CLIENT_ACCEPTANCE.md` bündelt die noch nicht ausgeführte Abnahme in A (Nextcloud Tasks/Kalender), B (Client/Rechte/Teilen) und C (Zeitzonen/DST) mit Ergebnisprotokoll.
- In `docs/DEVELOPMENT_PLAN.md` und `docs/APPSTORE_RELEASE.md` sind Schutzgrenzen und verbleibende Freigabeschritte dokumentiert.
- **Keine Migrationen**, keine produktiven Daten verändert, kein Merge, Tag oder Release. Neues Code-Testcommit: `12c6bc520e7f290b1eab9946e8c90f95124fe1ed`.

**Wichtig:** Die Zeitzonen-Regressionen prüfen das **bewusste Nicht-Umschreiben** explizit zonierter oder gemischter Dateien. Sie bestätigen noch keine korrekte lokale Sommer-/Winterzeitumrechnung für alle Clients. Die aktuelle floating-time-Semantik und die Ermittlung der Benutzerzeitzone sind vor der Freigabe weiterhin fachlich zu prüfen.

## Aktuelles Integrationstestpaket (2026-11-28)

- Neu in `tests/nextcloud35_e2e.sh`: echte HTTP-`PUT`-/`GET`-Runden für fremde Aufgaben mit `DUE:...Z` (UTC), `DUE;TZID=Europe/Berlin` und Aufgabentexte in `DESCRIPTION`. UTC-/TZID-Termine müssen inhaltlich unverändert bleiben; bei einer regulären TaskDate-Transformation müssen zusätzliche `VTODO`-Felder erhalten bleiben.
- **Nachweis:** [Nextcloud-33–35-Matrix #31](https://github.com/lfflr/Taskdate/actions/runs/46104699628) vollständig erfolgreich (NC33, NC34, NC35). [PHP-/SabreDAV-Prüfung #117](https://github.com/lfflr/Taskdate/actions/runs/46104699641) erfolgreich.
- Geprüfter Code-Commit: `8856a39de72388ec016c760f64755f7551b4b17a`.
- `docs/DEVELOPMENT_PLAN.md` und `docs/APPSTORE_RELEASE.md` entsprechend aktualisiert.
- **Testgrenze:** Keine Browser-Abnahme der Nextcloud-Tasks-App, kein externer CalDAV-Client, keine geteilten Kalender oder bestätigte lokale DST-Konvertierung. Explizit zonierte Termine werden aus Sicherheitsgründen von TaskDate nicht verändert.
- Keine Migrationen, keine produktiven Nutzerdaten verändert, kein Merge, Tag, App-Store-Eintrag oder Release.

## Sommerzeit-Regressionen (2026-11-28)

- `lib/DateParser.php` validiert nun lokale Uhrzeiten auf nicht existierende Stunden (Sommerzeit-Sprung) sowie doppeldeutige Stunden (Winterzeit-Rückstellung). Statt PHPs stiller Anpassung wird in solchen Fällen die automatische Datumsermittlung abgebrochen.
- `lib/TaskUpdatePlanner.php` übernimmt eine bereits automatisch verwaltete Uhrzeit nur dann auf ein geändertes Datum, wenn diese Uhrzeit dort gültig und eindeutig ist.
- `tests/date_parser_test.php` prüft sieben DST-Grenzfälle (Europe/Berlin 28.03.2027 und 31.10.2027 plus UTC-Kontrolle), `tests/task_update_planner_test.php` vier zusätzliche Fälle für bestehende Zeitwerte.
- [PHP-/Parser-/Planner-CI #125](https://github.com/lfflr/Taskdate/actions/runs/46106043899) **erfolgreich**; [vollständige Docker-CalDAV-Matrix #39](https://github.com/lfflr/Taskdate/actions/runs/46106043915) **NC33, NC34 und NC35 je erfolgreich**.
- **Historischer Stand vor dem Benutzerzeitzonen-Block:** Die Prüfung nutzte damals die PHP-Serverzeitzone. **Inzwischen behoben und per NC33–35-Docker-Matrix #48 nachgewiesen:** Der DAV-Pfad nutzt die authentifizierte Benutzerzeitzone. Die UI-/Drittclient-DST-Abnahme bleibt offen.
- Implementierter Code-Stand: `8bc192de288cd44d8f1bc794636132f46ec6f3f7`.
- Keine Migrationen, kein Merge, kein Release, keine produktiven Daten berührt.

## Benutzerzeitzonen im DAV-Pfad – Abschlussblock (2026-11-28)

- `lib/Sabre/TaskDateSabrePlugin.php`: Verwendung der öffentlichen Nextcloud-Dienste `OCP\\IUserSession` und `OCP\\IDateTimeZone`. Für jeden authentifizierten CalDAV-Aufruf wird die Zeitzone des zugehörigen Benutzers statt der PHP-Serverzeitzone verwendet. Fehlt der Benutzer-/Dienstkontext, bleibt die ursprüngliche Anfrage unverändert; das Plugin überschreibt keine Fälligkeit auf Basis einer ungeprüften Zone.
- `tests/sabre_put_test.php`: In isolierten Sabre-Tests wird eine deterministische Zeitzone injiziert; reale Nextcloud-Dienste werden dort nicht vorgetäuscht.
- `.github/workflows/nextcloud35-e2e.yml`: Einrichtung zweier Testkonten mit `Europe/Berlin` und `UTC` über Nextcloud-`occ`.
- `tests/nextcloud35_e2e.sh`: echte CalDAV-`PUT`-/`GET`-Prüfung derselben lokalen Uhrzeit (`02:30`) am 28.03.2027 (Sommerzeit-Lücke) und 31.10.2027 (Winterzeit-Doppelung). Im Berliner Benutzerkonto bleibt sie unverändert; im UTC-Testkonto wird eine gültige Fälligkeit gebildet.
- **Bestanden:** [NC33–35-Matrix #48](https://github.com/lfflr/Taskdate/actions/runs/46109440198), **alle drei Serverversionen erfolgreich**. [PHP-/SabreDAV-CI #134](https://github.com/lfflr/Taskdate/actions/runs/46109440190) ebenfalls erfolgreich.
- **Grenzen:** Die erzeugte Fälligkeit bleibt eine iCalendar-Floating-Time ohne `TZID`; die Anzeige und Interpretation in Nextcloud Tasks, Kalender und Drittclients sind noch nicht per UI geprüft. Geteilte Kalender, zusätzliche Client-Zeitzonen, Multi-Client-Synchronisation und tatsächliche Nutzeroberfläche bleiben für die gebündelte Abnahme offen.
- Code-Stand mit der neuen Dienstanbindung: `f7a2a3839520e2cfc11b636a933b89a449947af8`; getesteter Workflow nach der `occ`-Syntaxkorrektur: `d91396c5cec0b7e73bb7e51e927dd67923bd5b5b`.
- Keine Migrationen, kein Merge und keine Änderung an produktiven Daten. Kein Tag, ZIP oder signiertes App-Store-Release erstellt.

## Relative Datumsangaben und Mehrfachspeicherung (28.11.2026)

- `lib/TaskUpdatePlanner.php`: Bei `heute`, `morgen`, `übermorgen` und Wochentagen wird eine bereits von TaskDate verwaltete Fälligkeit bei erneuten PUTs **nicht** erneut relativ zum aktuellen Speicherdatum ausgewertet. Das verhindert stille Terminverschiebungen bei Bearbeitung oder Synchronisierung an Folgetagen. Eine absichtliche Änderung auf ein anderes relatives Wort löst vorerst keine automatische Neuberechnung aus (konservative Einschränkung).
- `tests/task_update_planner_test.php`: fünf neue Randfälle für lokale Tagesgrenzen in `UTC`, `Europe/Berlin` und `America/New_York`; zusätzliche Fälle für spätere erneute Speicherung und Abgrenzung ähnlich klingender Wörter.
- `tests/nextcloud35_e2e.sh`: echtes erneutes PUT/GET einer Aufgabe mit `morgen` und Vergleich der gespeicherten DUE vor/nach der Wiederholung.
- Tatsächlich bestanden: [PHP-/SabreDAV-Testlauf #148](https://github.com/lfflr/Taskdate/actions/runs/46109844565) und [Nextcloud 33–35-Matrix #59](https://github.com/lfflr/Taskdate/actions/runs/46109799134) mit **3/3 erfolgreichen Versionen**.
- Abschließender zusätzlicher [Nextcloud 33–35-Matrixlauf #62](https://github.com/lfflr/Taskdate/actions/runs/46109844524) nach den später ergänzten Planner-Testfällen ebenfalls mit **3/3 erfolgreich**. Reine Dokumentationsänderungen danach lösen keinen erneuten Testlauf aus.
- Dokumentation in `README.md`, `docs/DEVELOPMENT_PLAN.md`, `docs/APPSTORE_RELEASE.md`, `docs/UI_CLIENT_ACCEPTANCE.md` aktualisiert.
- Geprüfter Integrationscode: `ebe43ed90f8812f4130f5557fce13f587b53f8f4`; danach wurde der separate Planner-Test um spätere Wiederholungen erweitert (Commit `63726c5c892235dd5ff3b98878b8ab6277fa0555`), PHP-CI ebenfalls erfolgreich.
- Keine Datenbankmigrationen, keine Berührung produktiver Daten, kein Merge und kein öffentliches Release.

## Noch offen / Abnahmekriterien

- **Höchste Priorität:** Ergänzende Nextcloud-35-UI-/Client-Abnahme mit Tasks-Anlage/Bearbeitung, Kalenderdarstellung, geteilten Kalendern und externer Client-Synchronisation. Der automatische CalDAV-Server-Test hat die deklarative Plugineinbindung und den tatsächlichen `method:PUT`-Pfad für direkte HTTP-Clients bestätigt. Jetzt insbesondere prüfen, ob Nextcloud Tasks und externe Clients dieselben fachlichen Ergebnisse liefern.
- Basis-ACL (Cross-User) und veraltete ETags im echten CalDAV-Test bestanden. Weitere Freigabe-/Teilen-Rechte, Locking und Client-Synchronisation müssen noch abgeprüft werden.
- Die Berechnung verwendet weiterhin iCalendar-Fließzeit; die **authentifizierte Benutzerzeitzone und DST-Lücke/-Überlappung** sind in NC33–35-Serverintegrationstests nachgewiesen. Die Anzeige in echten Client-Oberflächen sowie die Interpretation von Floating-Time bleibt offen. Explizit zonierte/UTC-Fälligkeiten und gemischte Kalenderdateien werden konservativ nicht verändert.
- Normale Tasks mit `DTSTART` werden übersprungen; nur nach nachgewiesenem Verhalten erweitern.
- Lizenzrechtsinhaber/Rechte, Screenshots, App-ID, Zertifikat/Signatur, offizieller App-Store-Eintrag.
- Vollständige Liste und Schritte: `docs/APPSTORE_RELEASE.md`.

## Nächste gebündelte Phase

Gemäß `docs/DEVELOPMENT_PLAN.md` ist **P2 mit direktem CalDAV-Serververkehr auf NC33, NC34 und NC35 getestet**. Die restliche UI-/Client-Abnahme liegt vollständig in `docs/UI_CLIENT_ACCEPTANCE.md`. Verbleibt: gemeinsame UI-/Client-/Zeitzonen-/Rechte-Abnahme sowie P3 (Zertifikat, Signatur und App-Store-Eintrag). Bis zu deren Abschluss bleibt PR #1 ein Entwurf und darf nicht als stabiles App-Store-Release veröffentlicht werden. Beim Abschließen dieses Pakets erneut Ausgangs-/End-SHA, geänderte Dateien, Migrationen, ausgeführte Tests, PR/Release/ZIP und nächsten Schritt dokumentieren.


**Verbindliche Testvorgabe vom 28.11.2026 (Korrektur):** **Ausschließlich Nextcloud 35 oder höher** testen; **keine neuen Tests auf Nextcloud 33 oder 34**. Der CI-Workflow testet derzeit ausschließlich NC35. Nextcloud 36+ erst nach gesonderter Kompatibilitätsentscheidung testen und freigeben. In `appinfo/info.xml` ist die Mindestversion deshalb 35; `max-version=35` bleibt als vorsichtige Release-Grenze bestehen, bis höhere Versionen ausdrücklich geprüft sind. Frühere NC33/NC34-Prüfungen dienen ausschließlich als historische Nachweise, nicht als aktuelles Testziel.
