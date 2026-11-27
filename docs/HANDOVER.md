# HANDOVER – TaskDate

**Aktualisiert:** 2026-11-27  
**Repository:** https://github.com/lfflr/Taskdate  
**Main-Ausgangspunkt des Arbeitspakets:** `d4e9a2509e08752f02e92d21e647b9b0bedbc235` (0.2.2).  
**Entwicklungsbranch:** `release/appstore-readiness-2026-11-26`  
**PR:** https://github.com/lfflr/Taskdate/pull/1 (Entwurf; nicht mergen ohne NC35-Abnahme).  
**App-Version des Kandidaten:** 0.2.3.  
**Wichtiger Code-Stand:** Commit `dd688d3d12a2c3e9bc67f8ed3949cc58d2727b3b` umfasst die deklarative DAV-Registrierung und deren Pakettest.  
**End-SHA:** Zur Überprüfung GitHub-HEAD dieses Branches abrufen; die HANDOVER-Datei selbst erzeugt einen weiteren Commit.

## Übernommene Arbeit

1. Bisheriges post-save `CalendarObjectCreatedEvent`/`CalendarObjectUpdatedEvent`-Handling einschließlich direktem `OCA\\DAV\\CalDAV\\CalDavBackend`-Zugriff aus dem Branch entfernt.
2. DAV-Anbindung über `appinfo/info.xml` (`types/dav`, `sabre/plugins`), geladen durch den Nextcloud-35-DAV-PluginManager; `TaskDateSabrePlugin` verarbeitet passende `PUT`-Anfragen vor dem regulären Speichern. Der zunächst erprobte `OCP\\SabrePluginEvent`-Listener wurde entfernt, da er auf dem normalen DAV-Pfad nicht aufgerufen wird.
3. `TaskUpdatePlanner` kapselt Aufgabenumwandlung; manuelle DUE, geänderte oder gelöschte Fälligkeit, idempotente Updates, Titelbereinigung und automatische Marker berücksichtigt.
4. `TaskWritePolicy` schützt vor Änderungen an DTSTART- und Wiederholungsaufgaben. Dies ist bewusst eingeschränkt.
5. CalDAV-HTTP-Request-Tests mit echten SabreDAV- und VObject-Klassen einschließlich Stream-Body-Reparatur hinzugefügt.
6. LICENSE wurde nicht neu lizenziert; `appinfo/info.xml` zeigt nun `gpl` passend zu GPL-3.0 im Repository.
7. Release-Staging, statisches API-/Lizenz-Gate, README und `docs/APPSTORE_RELEASE.md` aktualisiert.
8. Ausführlicher P1-P3-Plan in `docs/DEVELOPMENT_PLAN.md`.

## Verifizierte CI-Nachweise

- https://github.com/lfflr/Taskdate/actions/runs/46029191536 – realer SabreDAV-PUT-Request-Test und statisches API-/Lizenz-Gate bestanden.
- https://github.com/lfflr/Taskdate/actions/runs/46029284782 – Stream-Body-Regressionsfälle bestanden.
- https://github.com/lfflr/Taskdate/actions/runs/46029347331 – Regression nach manuell gelöschtem DUE bestanden.
- https://github.com/lfflr/Taskdate/actions/runs/46029894457 – deklarative DAV-Registrierung, echter SabreDAV-PUT-Test und Paketprüfung bestanden.

Keine automatische Prüfung ist ein Ersatz für die noch ausstehende echte NC35-Installation. Für letzte Doku-Commits neue CI-Ergebnisse abwarten, nicht ungeprüft auf grün schließen.

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

## Noch offen / Abnahmekriterien

- **Höchste Priorität:** Vollständige Testinstallation auf Nextcloud 35, echte Tasks-Anlage und -Bearbeitung, Rechte und externer Client. Besonders prüfen, ob `TaskDateSabrePlugin` über den deklarativen DAV-PluginManager geladen wird und tatsächlich `beforeMethod:PUT` für Nextcloud Tasks erreicht.
- Sicherstellen, dass die neue Plugin-Kette bei produktiven CalDAV-PUTs mit Berechtigungen, Locking, ETag, Validierung und Speicherung zusammenarbeitet.
- Die Berechnung verwendet noch lokale iCalendar-Fließzeit; Benutzerzeitzonen, Sommer-/Winterzeit und andere Clients überprüfen.
- Normale Tasks mit `DTSTART` werden übersprungen; nur nach nachgewiesenem Verhalten erweitern.
- Lizenzrechtsinhaber/Rechte, Screenshots, App-ID, Zertifikat/Signatur, offizieller App-Store-Eintrag.
- Vollständige Liste und Schritte: `docs/APPSTORE_RELEASE.md`.

## Nächste gebündelte Phase

Gemäß `docs/DEVELOPMENT_PLAN.md` **P2: Nextcloud-35-End-to-End**. Bis zu deren Abnahme ist PR #1 ein Entwurf und darf nicht als stabiles App-Store-Release veröffentlicht werden. Beim Abschließen dieses Pakets erneut Ausgangs-/End-SHA, geänderte Dateien, Migrationen, ausgeführte Tests, PR/Release/ZIP und nächsten Schritt dokumentieren.
