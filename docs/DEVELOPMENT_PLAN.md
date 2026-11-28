# TaskDate – Entwicklungsplan

Stand: 2026-11-27. Maßgebliche Übergabe: [HANDOVER.md](HANDOVER.md), ausführliche Release-Checkliste: [APPSTORE_RELEASE.md](APPSTORE_RELEASE.md).

## Grundregeln

- Repository `lfflr/Taskdate`, Pull Request #1, Branch `release/appstore-readiness-2026-11-26`; nur der überprüfte GitHub-Stand gilt.
- Keine ungetesteten Änderungen an produktiven Nutzerdaten, keine Freigabe als stabil ohne echte Nextcloud-Integration.
- Größere Entwicklungsblöcke bündeln. Nutzer nur zur gemeinsamen Abnahme oder für notwendige Account-/Signierungsaktivitäten einbinden.
- GitHub Actions sparsam einsetzen und tatsächlich ausgeführte Testläufe explizit belegen.
- Vor Merge, Tag und Release `docs/HANDOVER.md` aktualisieren; keine Branch-Löschungen ohne Freigabe.

## P1: DAV-Architektur und Komponententests – Entwicklungskandidat

- [x] Interne DAV-Backend-Abhängigkeit aus der aktiven Implementierung entfernen.
- [x] CalDAV-Anfragen mit deklarativ über `appinfo/info.xml` registriertem SabreDAV-Plugin per `method:PUT` mit Priorität 60 vor der Speicherung bearbeiten; verzögerte Plugin-Registrierung wird getestet.
- [x] Planner für manuelle DUE, Titelbereinigung und Idempotenz extrahieren.
- [x] Stream-Body wiederherstellen, falls PUT nicht verändert wird.
- [x] GPL-Lizenzmetadaten an vorhandene LICENSE anpassen.
- [x] SabreDAV- und Parser-Tests sowie Paketkontrolle über CI.
- [x] Neue Plugin-Registrierungsreihenfolge und alle Komponententests erfolgreich in GitHub Actions [#79](https://github.com/lfflr/Taskdate/actions/runs/46030136176) geprüft (Code-Commit `bbf37072be9fe91ce2e12538150ddfa8069607d3`). Reine Dokumentations-Commits nach diesem Code-Stand ändern keine Laufzeitlogik.

## P2: Nextcloud 33–35 CalDAV-Tests und gebündelte Nutzerabnahme

- [x] In isoliertem Docker-Nextcloud 35.0.3.2 die App aktivieren, den registrierten PUT-Hook und die CalDAV-Speicherung testen; [GitHub Actions #4](https://github.com/lfflr/Taskdate/actions/runs/46033274589) erfolgreich.
- [x] Authentifizierte CalDAV-Roundtrips für Nextcloud 33, 34 und 35 erfolgreich in isolierten Docker-Instanzen; [Matrix #14](https://github.com/lfflr/Taskdate/actions/runs/46059594089).
- [x] Zwei Nutzer mit eigenen privaten Kalendern, Ablehnung fremder Schreibzugriffe, ungültiges Passwort, konkurrierende Änderungen mit veraltetem ETag erfolgreich getestet; [NC35 #10](https://github.com/lfflr/Taskdate/actions/runs/46059313311).
- [ ] Nextcloud-Tasks-Weboberfläche, externen CalDAV-Client und geteilte Kalender mit abgestuften Schreibrechten testen.
- [ ] DTSTART bei normalen Tasks prüfen; vorerst vorsichtshalber ausgenommen.
- [x] Wiederholungsaufgabe mit RRULE wird von TaskDate nicht verändert; ganztägige DUE-Termine funktionieren in der direkten CalDAV-Teststrecke.
- [x] Schutz für UTC-/TZID-Fälligkeiten, VTIMEZONE und mehrfach vorhandene Kalenderkomponenten implementiert; fünf SabreDAV-Regressionstests hinzugefügt.
- [x] Parser- und Planner-Regressionen für DST-Lücken und doppelte Ortszeiten in `Europe/Berlin` implementiert und in [PHP-CI #125](https://github.com/lfflr/Taskdate/actions/runs/46106043899) erfolgreich geprüft.
- [x] Authentifizierte CalDAV-Benutzerzeitzone über die öffentlichen Schnittstellen `IUserSession` und `IDateTimeZone` eingebunden. Zwei Benutzer (`Europe/Berlin`, `UTC`) einschließlich DST-Lücke/-Überlappung auf **NC33, NC34 und NC35** erfolgreich getestet: [Docker-Matrix #48](https://github.com/lfflr/Taskdate/actions/runs/46109440198), 3/3. [PHP-/SabreDAV-CI #134](https://github.com/lfflr/Taskdate/actions/runs/46109440190) erfolgreich. UI und externer Client noch offen.
- [x] Live-CalDAV-Tests für UTC-DUE, TZID-DUE und Erhaltung der VTODO-Beschreibung auf NC33–35 abgeschlossen: [Matrix #31](https://github.com/lfflr/Taskdate/actions/runs/46104699628) 3/3 erfolgreich. [PHP/SabreDAV #117](https://github.com/lfflr/Taskdate/actions/runs/46104699641) ebenfalls erfolgreich.
- [x] CalDAV-HTTP-Roundtrips für automatische DUE, Titelbereinigung, Datumsänderung mit Uhrzeiterhalt, manuelle DUE und DUE-Löschung, ganztägige DUE, DTSTART-Ausnahme und unberechtigte PUTs geprüft.
- [ ] Sichtprüfung in Nextcloud Tasks und Kalender einschließlich Client-Synchronisation durchführen.
- [ ] Festgestellte Probleme im gleichen Entwicklungspaket lösen und relevante Tests ergänzen.
- [x] Deklarierte Nextcloud 33–35-Versionen für direkte CalDAV-HTTP-Integration auf jeweils frischen Docker-Testinstallationen verifiziert. UI- und Drittclient-Abnahme getrennt offen.

- [x] Relative Datumsangaben über Benutzer-Mitternacht (UTC, Europe/Berlin, America/New_York) sowie der Schutz gegen späteres ungewolltes Verschieben bereits verwalteter `heute`-/`morgen`-/Wochentags-Aufgaben sind durch Planner-Regressionen [PHP-CI #148](https://github.com/lfflr/Taskdate/actions/runs/46109844565) bestanden. Der zusätzliche reale CalDAV-Repeat-PUT-Test ist in der [NC33–35-Matrix #59](https://github.com/lfflr/Taskdate/actions/runs/46109799134) auf allen drei Versionen erfolgreich.

- [x] Im NC35-Entwicklungsbranch erweiterter Schutz für Aufgaben mit `COMPLETED`, `STATUS=COMPLETED/CANCELLED/IN-PROCESS`, `DURATION`, `RDATE` und `EXDATE` implementiert; `NEEDS-ACTION` bleibt zulässig. PHP-/SabreDAV-Tests bestanden: [CI #166](https://github.com/lfflr/Taskdate/actions/runs/46148177404).
- [ ] Echter NC35-CalDAV-Test für `STATUS`-Schutz läuft: [NC35 #80](https://github.com/lfflr/Taskdate/actions/runs/46148177399). Erst nach abgeschlossenem Lauf als verifiziert markieren.

## P3: App-Store-Release – gebündelt nach Abnahme

- [ ] Quellcode-Rechte prüfen, Icon/Screenshots finalisieren, erforderliche Metadaten vervollständigen.
- [ ] `taskdate` registrieren, CSR einreichen, Zertifikat erhalten (Kontoschritte durch Eigentümer).
- [ ] Signiertes Laufzeitpaket erzeugen und dessen Inhalt, Signatur und Installation prüfen.
- [ ] PR auf `main` übernehmen, Tag/Release veröffentlichen, App Store einreichen.
- [ ] Abschlussübergabe mit SHAs, Dateien, Tests, offenen Punkten und Links.

## Nächster Schritt

Der technische CalDAV-Serverteil von P2 ist auf Nextcloud 33, 34 und 35 bestanden. Es folgen **gebündelte UI-/Client-Abnahme** (Nextcloud Tasks, Kalender, Zeitzonen, externe Clients und geteilte Kalender) sowie Signierung und Veröffentlichung in P3. Keine App-Store-Freigabe vor vollständiger Abnahme.


**Verbindliche Testvorgabe vom 28.11.2026 (Korrektur):** **Ausschließlich Nextcloud 35 oder höher** testen; **keine neuen Tests auf Nextcloud 33 oder 34**. Der CI-Workflow testet derzeit ausschließlich NC35. Nextcloud 36+ erst nach gesonderter Kompatibilitätsentscheidung testen und freigeben. In `appinfo/info.xml` ist die Mindestversion deshalb 35; `max-version=35` bleibt als vorsichtige Release-Grenze bestehen, bis höhere Versionen ausdrücklich geprüft sind. Frühere NC33/NC34-Prüfungen dienen ausschließlich als historische Nachweise, nicht als aktuelles Testziel.
