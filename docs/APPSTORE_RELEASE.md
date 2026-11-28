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

**Achtung:** Die eigenständigen SabreDAV-Tests und der inzwischen erfolgreiche Docker-CalDAV-Test belegen den Schreibweg auf einer Nextcloud-35.0.3.2-Instanz. Auch die Nextcloud-Versionen 33 und 34 wurden inzwischen in Docker-CalDAV-Tests bestanden. Die Nextcloud-Tasks-Oberfläche, externe Clients und zeitbezogene Sonderfälle bleiben gesondert abzunehmen.

## 2. Behobene technische Punkte

- Automatische Fälligkeit und Titelbereinigung sind idempotent und werden vor der Speicherung berechnet.
- Manuell vergebene, veränderte oder gelöschte DUE-Werte werden nicht überschrieben.
- Datum `261112` sowie Zeiten `1530`, `1405`, `1537` usw. werden unterstützt.
- Aufgaben mit `DTSTART`, `RRULE` oder `RECURRENCE-ID` werden vorerst ausgelassen.
- Explizite DUE-Zeitzonen (`TZID`, UTC `Z`), VTIMEZONE sowie gemischte oder mehrfach enthaltene Komponenten werden bis zur gesonderten Client-/Zeitzonenabnahme unverändert weitergereicht. Fünf SabreDAV-Regressionstests dokumentieren diese Schutzgrenze.
- Unpassende DAV-Pfade, VEVENT und unlesbare iCalendar-Daten werden nicht verändert.
- SabreHTTP kann Body-Streams bei `getBodyAsString()` verbrauchen: die Originaldaten werden bei unveränderten Anfragen nun wiederhergestellt.
- Bei bekannten großen `Content-Length`-Werten wird das Lesen übersprungen; nach einer Änderung wird die Länge aktualisiert.
- Die App-Metadaten verwenden `GPL-3.0-only`, passend zur vorhandenen GPL-3.0-Lizenzdatei (keine Änderung am Lizenztext).
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

**Erfolgreicher echter Nextcloud-35-CalDAV-Test:** [GitHub Actions #4](https://github.com/lfflr/Taskdate/actions/runs/46033274589), Commit `c5e164e16c05d20bbdd65793f77f10a6d5ae43d7`. Nextcloud 35.0.3.2 aus dem offiziellen Docker-Image wurde mit SQLite neu installiert; TaskDate 0.2.3 ließ sich aktivieren. Über authentifizierte CalDAV-`MKCALENDAR`, `PUT` und `GET` wurden folgende Fälle erfolgreich geprüft: Fälligkeit+Titelbereinigung, Datumsänderung mit Uhrzeiterhalt, manuelle Fälligkeiten, DTSTART-Schutz, ganztägiges DUE, manuelle DUE-Entfernung trotz bestehendem Marker und Abweisung falscher Anmeldedaten. Kein Nutzer-Produktivsystem wurde verändert.

**Erweiterte Nachweise (27.11.2026):** [Mehrbenutzer- und Sicherheitstest #10](https://github.com/lfflr/Taskdate/actions/runs/46059313311) auf Nextcloud 35 bestanden: zwei eigene Konten und Kalender, Zugriffssperre zwischen Nutzern, veralteter ETag verhindert Überschreiben, RRULE-Aufgabe bleibt unverändert. [Nextcloud-Versionenmatrix #14](https://github.com/lfflr/Taskdate/actions/runs/46059594089) mit **33, 34 und 35 vollständig grün**; dieselbe CalDAV-Teststrecke wird je Version auf einer eigenen frischen Docker-/SQLite-Instanz ausgeführt.

**Testgrenzen:** Kein echter Nextcloud-Tasks-Browserlauf, kein externer CalDAV-Client, keine geteilten oder schreibgeschützten Kalender, keine MariaDB-/PostgreSQL-Datenbank und keine Zeitzonenübergänge. Der RRULE-Test bestätigt nur das bewusste Überspringen wiederkehrender Aufgaben, nicht ihre automatische Verarbeitung. Die CalDAV-Serverintegration wurde inzwischen zusätzlich auf Nextcloud 33 und 34 erfolgreich geprüft; Browser-/Drittclient-Kompatibilität bleibt offen.

**Neuer Live-Nachweis (28.11.2026):** [CI-Matrix #31](https://github.com/lfflr/Taskdate/actions/runs/46104699628) erfolgreich auf Nextcloud 33, 34 und 35. Ergänzende direkte CalDAV-`PUT`/`GET`-Tests prüfen jetzt auf allen drei Versionen UTC-`DUE`, `DUE;TZID=Europe/Berlin` sowie das Beibehalten von `DESCRIPTION` bei der automatischen Titelanpassung. [PHP-/SabreDAV-Lauf #117](https://github.com/lfflr/Taskdate/actions/runs/46104699641) ebenfalls erfolgreich. Das ist ein Test des unveränderten Durchreichens zonierter Aufgaben, keine vollständige Zeitzonen-/DST-Implementierung. Ein externer Client und die Tasks-Weboberfläche sind weiterhin offen.

**Sommerzeit-Regressionsschutz (28.11.2026):** Die Parser- und Planner-Logik vermeidet jetzt automatische Änderungen bei einer nicht existierenden oder doppelt vorkommenden lokalen Uhrzeit in der jeweils übergebenen Zeitzone. Testfälle für `Europe/Berlin` (28.03. und 31.10.2027) sowie eine UTC-Kontrolle sind in [PHP-CI #125](https://github.com/lfflr/Taskdate/actions/runs/46106043899) bestanden. **Nachtrag (28.11.2026):** Die Benutzerzeitzone wird inzwischen über `IUserSession` und `IDateTimeZone` des authentifizierten Nextcloud-Benutzers bezogen. [NC33–35-Matrix #48](https://github.com/lfflr/Taskdate/actions/runs/46109440198) bestätigt unterschiedliche Benutzerzeitzonen (`Europe/Berlin` und `UTC`) an beiden DST-Übergängen: 3/3 erfolgreich. [PHP-/SabreDAV-CI #134](https://github.com/lfflr/Taskdate/actions/runs/46109440190) erfolgreich. Browser, externe CalDAV-Clients und die Interpretation der weiterhin als iCalendar-Floating-Time gespeicherten Fälligkeiten sind weiterhin separat abzunehmen.

**Relativdatum-Schutz (28.11.2026):** Bereits verwaltete Fälligkeiten aus `heute`, `morgen`, `übermorgen` oder Wochentagen werden bei späteren PUT-Anfragen nicht erneut relativ zum neuen Speicherdatum berechnet. Neue Planner-Regressionen prüfen lokale Mitternachtsgrenzen in UTC, Europe/Berlin und America/New_York. Ein zusätzlicher echter CalDAV-PUT/GET-Test prüft, dass eine zweite Speicherung den gespeicherten Termin nicht verschiebt. Bestanden: [PHP-Regressionen #148](https://github.com/lfflr/Taskdate/actions/runs/46109844565) und [NC33–35-CalDAV-Matrix #59](https://github.com/lfflr/Taskdate/actions/runs/46109799134) mit 3/3 erfolgreichen Versionen. Ein bewusstes Umschalten zwischen zwei relativen Wörtern wird vorerst nicht automatisch neu interpretiert.

## 4. Gebündelte ergänzende Praxistests – Freigabegruppe A

Die serverseitigen Standardfälle sind mit der isolierten Docker-Instanz bereits getestet. Für die verbleibende **UI-/Client-Abnahme** gemäß [UI_CLIENT_ACCEPTANCE.md](UI_CLIENT_ACCEPTANCE.md) nur auf einer **separaten Nextcloud-35-Testinstallation** mit Testkalender und mindestens einer Nutzer-Testkennung, bevor Produktivdaten verwendet werden:

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

Eine gebündelte Prüftabelle mit Testfällen A1–A8, B1–B5 und C1–C4 sowie einem Fehlerprotokoll liegt unter [UI_CLIENT_ACCEPTANCE.md](UI_CLIENT_ACCEPTANCE.md). Die dort beschriebenen Praxisfälle sind **nicht** bereits als ausgeführt zu werten.

## 5. Freigabegruppe B – Veröffentlichung

- [x] Kein direkter Zugriff der aktiven App auf `OCA\\DAV\\CalDAV\\CalDavBackend`.
- [x] GPL-Metadaten zur vorhandenen Lizenzdatei passend.
- [x] Parser-, Planner-, SabreDAV- und Packaging-Testläufe dokumentiert.
- [x] Serverseitiges CalDAV-End-to-End auf Nextcloud 35.0.3.2 mit Docker und echtem HTTP-Verkehr bestanden.
- [ ] Ergänzende UI-/Client-/Zeitzonen-/Freigabe-Rechte-Abnahme aus Gruppe A vollständig durchführen; Zweibenutzer-ACL und ETag sind bereits automatisiert geprüft.
- [x] Nextcloud 33/34/35 für echte direkte CalDAV-HTTP-Roundtrips im Docker-/SQLite-Test erfolgreich verifiziert; [CI-Matrix #14](https://github.com/lfflr/Taskdate/actions/runs/46059594089). Keine generelle UI-/Drittclient-Freigabe daraus ableiten.
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

Der veröffentlichungsfähige Status ist weiterhin **NICHT FREIGEGEBEN**: offene UI-/Client-/Zeitzonen-Abnahme sowie App-ID, Screenshots, Signaturzertifikat und Paketunterschrift.

Offizielle Nextcloud-Dokumentation:
- https://docs.nextcloud.com/server/stable/developer_manual/app_publishing_maintenance/release_process.html
- https://docs.nextcloud.com/server/stable/developer_manual/app_publishing_maintenance/code_signing.html
- https://docs.nextcloud.com/server/stable/developer_manual/app_publishing_maintenance/publishing.html

Zentrale Übergabe und weitere Meilensteine: [HANDOVER.md](HANDOVER.md) und [DEVELOPMENT_PLAN.md](DEVELOPMENT_PLAN.md).


**Verbindliche Testvorgabe vom 28.11.2026 (Korrektur):** **Ausschließlich Nextcloud 35 oder höher** testen; **keine neuen Tests auf Nextcloud 33 oder 34**. Der CI-Workflow testet derzeit ausschließlich NC35. Nextcloud 36+ erst nach gesonderter Kompatibilitätsentscheidung testen und freigeben. In `appinfo/info.xml` ist die Mindestversion deshalb 35; `max-version=35` bleibt als vorsichtige Release-Grenze bestehen, bis höhere Versionen ausdrücklich geprüft sind. Frühere NC33/NC34-Prüfungen dienen ausschließlich als historische Nachweise, nicht als aktuelles Testziel.
