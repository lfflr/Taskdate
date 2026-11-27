# TaskDate – Nextcloud App Store / Release-Kandidat

**Stand: 27.11.2026**  
Repository: https://github.com/lfflr/Taskdate  
Entwicklungsbranch: `release/appstore-readiness-2026-11-26`  
Pull Request: https://github.com/lfflr/Taskdate/pull/1  
App-Version im PR: **0.2.3**  
Status: **NICHT veröffentlicht, nicht nach main übernommen, keine Freigabe für produktive Installation.**

## 1. Architektur (neuer Kandidat)

Die ursprünglichen `CalendarObjectCreatedEvent`-/`CalendarObjectUpdatedEvent`-Listener und der direkte Zugriff auf `OCA\\DAV\\CalDAV\\CalDavBackend` wurden aus dem aktiven Branch entfernt.

Stattdessen wird `TaskDateSabrePlugin` über die deklarative DAV-Plugin-Konfiguration in `appinfo/info.xml` (`types/dav`, `sabre/plugins`) geladen. Der Nextcloud-35-`PluginManager` verwendet diesen Pfad ausdrücklich für den normalen DAV-Server. Der zuvor versuchte `OCP\\SabrePluginEvent`-Listener wurde wieder entfernt, da `apps/dav/lib/Server.php` im normalen CalDAV-Pfad stattdessen ein DAV-internes Ereignis auslöst. Das Plugin verarbeitet ein eingehendes `PUT` unter `calendars/<user>/<calendar>/<object>.ics` vor der normalen Nextcloud-Speicherung und verändert nur VTODOs, für die der konservative Sicherheitsfilter zustimmt. Nextcloud fügt App-Plugins während `beforeMethod:*` hinzu. Daher registriert das TaskDate-Plugin **`method:PUT` mit Priorität 60** und arbeitet vor dem CorePlugin-PUT-Handler (Standardpriorität 100). Die Reihenfolge wird in `tests/sabre_put_test.php` simuliert und geprüft. Der Request-Body-Ersatz wird von SabreHTTP unterstützt.

Die Aufgabenlogik ist in `TaskUpdatePlanner` ohne Speicherzugriff gekapselt. Nach der Mutation speichert **Nextcloud selbst** über den regulären DAV-Pfad, einschließlich der normalen Berechtigungs- und Kalenderprüfungen. Es wird **kein zweites Update** aus einem nachgelagerten Listener ausgelöst.

Verifizierte Quellcode-Bezüge:
- https://github.com/nextcloud/server/blob/stable35/lib/public/SabrePluginEvent.php
- https://github.com/nextcloud/server/blob/stable35/apps/dav/lib/Server.php
- https://github.com/sabre-io/dav/blob/master/lib/DAV/Server.php
- https://github.com/sabre-io/http/blob/master/lib/Message.php

**Achtung:** Die eigenständigen SabreDAV-Tests beweisen noch nicht, dass Nextcloud Tasks auf jeder unterstützten Nextcloud-Version genau diesen PUT-Pfad verwendet. Keine öffentliche Freigabe ohne Praxistest.

## 2. Behobene technische Punkte

- Automatische Fälligkeit und Titelbereinigung sind idempotent und werden vor der Speicherung berechnet.
- Manuell vergebene, veränderte oder gelöschte DUE-Werte werden nicht überschrieben.
- Datum `261112` sowie Zeiten `1530`, `1405`, `1537` usw. werden unterstützt.
- Aufgaben mit `DTSTART`, `RRULE` oder `RECURRENCE-ID` werden vorerst ausgelassen.
- Unpassende DAV-Pfade, VEVENT und unlesbare iCalendar-Daten werden nicht verändert.
- SabreHTTP kann Body-Streams bei `getBodyAsString()` verbrauchen: die Originaldaten werden bei unveränderten Anfragen nun wiederhergestellt.
- Bei bekannten großen `Content-Length`-Werten wird das Lesen übersprungen; nach einer Änderung wird die Länge aktualisiert.
- Die App-Metadaten verwenden `gpl`, passend zur vorhandenen GPL-3.0-Lizenzdatei (keine Änderung am Lizenztext).
- Der Release-Staging-Test packt nur Laufzeitdateien, App-Icon und LICENSE.

## 3. Automatisierte Prüfungen

Die GitHub-Actions-Konfiguration `.github/workflows/php-checks.yml` prüft:
- PHP-Syntax
- Datum/Uhrzeit-Parser
- Reinen Task-Update-Planer
- Konservative Schreibschutzregeln
- SabreDAV-HTTP-PUT-Tests mit echten SabreDAV-/VObject-Bibliotheken, auch Stream-Body und idempotente Updates
- Installationsverzeichnis / App-Metadaten
- Statische App-Store-Sperre auf interne DAV-API und widersprüchliche Lizenzangaben

Nachweise:
- [Run #61](https://github.com/lfflr/Taskdate/actions/runs/46029191536): öffentliche Sabre-Architektur und statische Release-Prüfung erfolgreich.
- [Run #63](https://github.com/lfflr/Taskdate/actions/runs/46029284782): Testfall für Stream-Body erfolgreich.
- [Run #65](https://github.com/lfflr/Taskdate/actions/runs/46029347331): manuell gelöschte Fälligkeiten werden nicht wieder eingesetzt.
- [Run #79](https://github.com/lfflr/Taskdate/actions/runs/46030136176): deklaratives Plugin, verzögerte Registrierung, `method:PUT`-Priorität vor CorePlugin und alle Paket-/Sicherheitstests erfolgreich.

Tests ersetzen ausdrücklich **nicht** die echte Nextcloud-35-Prüfung.

## 4. Gebündelter Praxistest – Freigabegruppe A

Nur auf einer **separaten Nextcloud-35-Testinstallation** mit Testkalender und mindestens einer Nutzer-Testkennung, bevor Produktivdaten verwendet werden:

1. App aus dem PR-Branch nach `custom_apps/taskdate` installieren und aktivieren.
2. In Nextcloud Tasks `Zahnarzt 261112 1530` anlegen. Prüfen: DUE 12.11.2026 15:30, Titel `Zahnarzt 261112`, Anzeige im Kalender bei aktivierten Aufgaben.
3. `Zahnarzt 261112 1405` und `Besprechung 20261112 1537` anlegen: DUE, Titelbereinigung und Zeitzone prüfen.
4. Eine TaskDate-verwaltete Aufgabe auf anderes Datum ändern: Uhrzeit erhalten; nach Neuladen keine weiteren Schreibereignisse.
5. Neue Aufgabe mit manuell gesetzter Fälligkeit: nicht überschreiben; DUE nachträglich manuell verändern/löschen und Titel ändern: Entscheidung erhalten.
6. Aufgaben mit `DTSTART`, `RRULE` und `RECURRENCE-ID`: keine automatische Mutation.
7. VEVENT und andere DAV-Dateien, Kalender ohne Schreibrechte sowie geteilte Kalender: unverändert bzw. korrekt vom Server abgewiesen.
8. Mit einem externen CalDAV-Client eine Aufgabe anlegen und ändern; Ergebnis auf Client und Server vergleichen.
9. Zeitzone Europa/Berlin einschließlich Zeitumstellungen und Aufgaben ohne Uhrzeit prüfen.
10. Browser, Server-Logs und Datenintegrität beobachten; dokumentieren, welche App/Client-Version tatsächlich geprüft wurde.

Besonders wichtig: Prüfen, ob Nextcloud Tasks bei gewöhnlichen Aufgaben automatisch `DTSTART` setzt. In diesem Fall würde der derzeitige Schutzfilter diese Aufgaben ausschließen. Dann im eigenen Entwicklungsschritt mit passenden Tests erweitern.

## 5. Freigabegruppe B – Veröffentlichung

- [x] Kein direkter Zugriff der aktiven App auf `OCA\\DAV\\CalDAV\\CalDavBackend`.
- [x] GPL-Metadaten zur vorhandenen Lizenzdatei passend.
- [x] Parser-, Planner-, SabreDAV- und Packaging-Testläufe dokumentiert.
- [ ] End-to-End auf Nextcloud 35 erfolgreich, einschließlich aller Fälle aus Gruppe A.
- [ ] Nextcloud 33/34 nur dann als kompatibel deklarieren, wenn passend verifiziert; andernfalls Versionsbereich einschränken.
- [ ] Rechte an sämtlichen Quellen/Lizenzinhaberschaft klären.
- [ ] App-Icon visuell prüfen und Screenshots aufnehmen.
- [ ] App-ID `taskdate` im App Store registrieren.
- [ ] Signaturzertifikat beantragen und erhalten.
- [ ] Saubere signierte `taskdate.tar.gz` erzeugen.
- [ ] Version/Release-Link im Nextcloud App Store eintragen.

## 6. Signieren – erst nach A/B-Freigabe

Privaten Schlüssel ausschließlich auf einem vertrauenswürdigen eigenen Rechner erzeugen, **niemals ins Repository hochladen**:

```bash
openssl req -nodes -newkey rsa:4096 -keyout taskdate.key -out taskdate.csr -subj "/CN=taskdate"
```

CSR über https://github.com/nextcloud/app-certificate-requests beantragen.

```bash
bash scripts/prepare-release.sh
# Mit dem auf dem eigenen Nextcloud-Server verfügbaren OCC signieren:
php occ integrity:sign-app --privateKey=/secure/taskdate.key --certificate=/secure/taskdate.crt --path=/absolute/path/to/dist/taskdate
cd /absolute/path/to/dist
tar -czf taskdate.tar.gz taskdate
```

Die Dateien in `dist/taskdate` nach der Signierung nicht mehr verändern. Der Upload in den App Store darf erst auf Grundlage des signierten GitHub-Releases erfolgen.

Offizielle Nextcloud-Dokumentation:
- https://docs.nextcloud.com/server/stable/developer_manual/app_publishing_maintenance/release_process.html
- https://docs.nextcloud.com/server/stable/developer_manual/app_publishing_maintenance/code_signing.html
- https://docs.nextcloud.com/server/stable/developer_manual/app_publishing_maintenance/publishing.html

Zentrale Übergabe und weitere Meilensteine: [HANDOVER.md](HANDOVER.md) und [DEVELOPMENT_PLAN.md](DEVELOPMENT_PLAN.md).
