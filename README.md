# TaskDate for Nextcloud

TaskDate erkennt Datums- und Uhrzeitangaben in Nextcloud-Aufgabentiteln (VTODO) und setzt automatisch das Fälligkeitsdatum. Bei einer erkannten Uhrzeit wird diese aus dem Titel entfernt. Es entsteht **kein zusätzlicher Kalendereintrag**: Die bestehende Aufgabe kann mit aktivierter Aufgabenanzeige im Kalender erscheinen.

**Status:** 0.2.3 ist ein Entwicklungskandidat in [PR #1](https://github.com/lfflr/Taskdate/pull/1). Kein freigegebenes App-Store-Release. Nextcloud 33–35 ist deklariert; ein realer Nextcloud-35-Integrationstest steht noch aus.

## Beispiele

| Eingabe | Automatische Fälligkeit | Gespeicherter Titel |
|---|---|---|
| `Zahnarzt 261112 1530` | 12.11.2026, 15:30 | `Zahnarzt 261112` |
| `Termin 20261112 1405` | 12.11.2026, 14:05 | `Termin 20261112` |
| `Anruf 12.11.2026 14:30` | 12.11.2026, 14:30 | `Anruf 12.11.2026` |
| `Zahnarzt 261112` | 12.11.2026, ganztägig | `Zahnarzt 261112` |

Unterstützt sind unter anderem `TT.MM.JJJJ`, `TT.MM.JJ`, `TT.MM.`, `JJJJ-MM-TT`, `JJJJMMTT`, `JJMMTT` sowie `heute`, `morgen`, `übermorgen` und Wochentage. Uhrzeiten: `14:30`, `14.30`, `14 Uhr`, `14.30 Uhr`, `1400`, `1537` (gültige Minuten von 00 bis 59).

## Funktionsweise

Die Nextcloud-DAV-Integration erfolgt deklarativ über `appinfo/info.xml` (`types/dav` und `sabre/plugins`). Nextcloud lädt das SabreDAV-Plugin für den regulären Kalender-DAV-Server; es passt eingehende Kalender-`PUT`-Anfragen **vor** der eigentlichen Speicherung an. Es benötigt keine internen Klassen der DAV-App und erzeugt keinen zweiten Schreibvorgang. Die normalen CalDAV-Berechtigungen, Validierungen und Speicheroperationen bleiben Nextcloud überlassen.

`TaskUpdatePlanner` berechnet die Änderung. `X-TASKDATE-AUTO-DUE` kennzeichnet automatisch verwaltete Fälligkeiten. Bereits manuell gesetzte oder veränderte Fälligkeiten bleiben unverändert, ebenso ausdrücklich gelöschte Fälligkeiten. Eine bei der Titelbereinigung entfernte Uhrzeit wird bei späteren Datumsänderungen nach Möglichkeit beibehalten.

**Vorerst nicht automatisch bearbeitet:** Aufgaben mit `DTSTART`, `RRULE` oder `RECURRENCE-ID`. Damit sollen ungetestete Änderungen an Startzeiten und Wiederholungen vermieden werden. Bei Importen oder Bearbeitung mit externen CalDAV-Clients muss zusätzlich geprüft werden, ob diese die Kennzeichnung `X-TASKDATE-AUTO-DUE` erhalten.

**Noch zu prüfen:** Zeitzonen und Sommerzeit, tatsächliche Nextcloud-35-Aufgabenflüsse (einschließlich Nextcloud Tasks), Starts und Wiederholungen, unterschiedliche Benutzerrechte, lesegeschützte Kalender sowie Verhalten anderer Clients. Zeitangaben ohne explizite Zone werden derzeit als iCalendar-Fließzeit gespeichert.

## Entwicklung und Tests

```sh
composer update --no-interaction --no-progress --prefer-dist
php tests/date_parser_test.php
php tests/task_update_planner_test.php
php tests/task_write_policy_test.php
php tests/sabre_put_test.php
bash tests/release_staging_test.sh
bash scripts/check-release-blockers.sh
```

Composer und `vendor/` werden ausschließlich für Entwicklungstests verwendet und **nicht** in die App gepackt. Der Test `sabre_put_test.php` verwendet echte SabreDAV-Request- und iCalendar-Objekte. Ein erfolgreicher Lauf ist kein Nachweis der Kompatibilität mit einer vollständigen Nextcloud-Installation.

## Testinstallation und App Store

Für eine manuelle Testinstallation die App-Dateien unter `custom_apps/taskdate/` ablegen und mit `php occ app:enable taskdate` aktivieren. Vorher ein Backup bzw. eine separate Testinstanz verwenden.

Die Freigabeschritte, Testmatrix, Signierung und verbleibenden Punkte stehen in [docs/APPSTORE_RELEASE.md](docs/APPSTORE_RELEASE.md). Der aktive Entwicklungsstand und die Übergabe stehen in [docs/HANDOVER.md](docs/HANDOVER.md).

Lizenz: GPL-3.0 (siehe `LICENSE`).
