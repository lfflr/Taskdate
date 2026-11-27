# HANDOVER – TaskDate

**Aktualisiert:** 2026-11-27  
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

## Noch offen / Abnahmekriterien

- **Höchste Priorität:** Ergänzende Nextcloud-35-UI-/Client-Abnahme mit Tasks-Anlage/Bearbeitung, Kalenderdarstellung, geteilten Kalendern und externer Client-Synchronisation. Der automatische CalDAV-Server-Test hat die deklarative Plugineinbindung und den tatsächlichen `method:PUT`-Pfad für direkte HTTP-Clients bestätigt. Jetzt insbesondere prüfen, ob Nextcloud Tasks und externe Clients dieselben fachlichen Ergebnisse liefern.
- Basis-ACL (Cross-User) und veraltete ETags im echten CalDAV-Test bestanden. Weitere Freigabe-/Teilen-Rechte, Locking und Client-Synchronisation müssen noch abgeprüft werden.
- Die Berechnung verwendet weiterhin iCalendar-Fließzeit; Benutzerzeitzonen und Sommer-/Winterzeit noch prüfen. Explizit zonierte/UTC-Fälligkeiten und gemischte Kalenderdateien werden jetzt konservativ nicht verändert.
- Normale Tasks mit `DTSTART` werden übersprungen; nur nach nachgewiesenem Verhalten erweitern.
- Lizenzrechtsinhaber/Rechte, Screenshots, App-ID, Zertifikat/Signatur, offizieller App-Store-Eintrag.
- Vollständige Liste und Schritte: `docs/APPSTORE_RELEASE.md`.

## Nächste gebündelte Phase

Gemäß `docs/DEVELOPMENT_PLAN.md` ist **P2 mit direktem CalDAV-Serververkehr auf NC33, NC34 und NC35 getestet**. Die restliche UI-/Client-Abnahme liegt vollständig in `docs/UI_CLIENT_ACCEPTANCE.md`. Verbleibt: gemeinsame UI-/Client-/Zeitzonen-/Rechte-Abnahme sowie P3 (Zertifikat, Signatur und App-Store-Eintrag). Bis zu deren Abschluss bleibt PR #1 ein Entwurf und darf nicht als stabiles App-Store-Release veröffentlicht werden. Beim Abschließen dieses Pakets erneut Ausgangs-/End-SHA, geänderte Dateien, Migrationen, ausgeführte Tests, PR/Release/ZIP und nächsten Schritt dokumentieren.
