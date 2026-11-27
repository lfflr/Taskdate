# TaskDate: Nextcloud App Store preparation

## Status (2026-11-27)

Repository: https://github.com/lfflr/Taskdate
Development branch: release/appstore-readiness-2026-11-26

**NOT RELEASED.** Version 0.2.3 in metadata is a prerelease candidate, not a stable/public claim. No App Store registration, certificate, signed release or live Nextcloud 35 integration test is confirmed.

## Continued development (2026-11-27)

- Extended compact HHMM parsing from quarter-hours to arbitrary valid minutes (`1405`, `1537`, `2359`), with parser and title-cleanup regression tests.
- Changes are confined to PR #1; no App Store release, tag or signature has been issued.
- Existing task-write architecture continues to depend on an internal Nextcloud DAV backend and is **not cleared for the App Store**.
- Expanded HHMM parser regression tests and PHP syntax passed in [CI run 45940606432](https://github.com/lfflr/Taskdate/actions/runs/45940606432), commit `4f12096f4ec9923c85672edbb3db449da6fcf318`.
- This remains a parser test, **not** a Nextcloud 35 integration test.

## Safety policy added (2026-11-27)

The listener now uses `TaskWritePolicy::allowsAutomaticDue()` and conservatively skips:
- recurring VTODOs (`RRULE` or `RECURRENCE-ID`);
- VTODOs with `DTSTART` until DTSTART/DUE ordering and timezone behavior are integration-tested;
- items without `SUMMARY`.

This is a **temporary behavioral limitation**, not evidence of Nextcloud 35 compatibility. Task apps may populate `DTSTART` for ordinary tasks. The release remains blocked until that behavior is measured and a correct policy implemented. `tests/task_write_policy_test.php` documents the current safety contract.

## Event payload handling (2026-11-27)

- Consume the current event's `getObjectData()['calendardata']` only if it is supplied; the event metadata does **not** guarantee this field. The backend fallback remains necessary.
- If missing, fall back to `getCalendarObject`, still an **internal** DAV dependency.
- Reject absent/empty/non-string URIs and raw iCalendar payloads without trying to parse them.
- Ignore objects whose event `component` explicitly identifies `VEVENT`/`VJOURNAL` rather than `VTODO`. With missing metadata, continue to inspect the parsed iCalendar component.
- `tests/calendar_payload_test.php` checks payload validation; [CI run 45980616375](https://github.com/lfflr/Taskdate/actions/runs/45980616375) passed PHP syntax, parser, write-policy and payload tests. This is **not** a live Nextcloud event-dispatch integration test.
- The listener still writes with `OCA\\DAV\\CalDAV\\CalDavBackend::updateCalendarObject`. Public API and App Store compatibility remain blocked and must be investigated independently.

## Packaging checks (2026-11-27)

A new `tests/release_staging_test.sh` validates unsigned staging structure and basic appinfo metadata, and is now invoked from the PHP CI workflow. [Run 45994185733](https://github.com/lfflr/Taskdate/actions/runs/45994185733) passed all five checks: syntax, parser, safety policy, event payload, and staging smoke test. The script does **not** sign the release or validate it against the official Nextcloud appinfo schema. Do not interpret a green staging test as App Store approval.

## Review note (2026-11-27)

The previous description claiming that Nextcloud 35 events generally include `calendardata` was too strong. The listener now documents that event payloads may contain only metadata and therefore continues to use backend lookup for the raw object. The public OCP calendar event is useful for detecting changes, not proof of a public existing-object write API. No claim of App Store acceptance is made.

[CI run 46022264052](https://github.com/lfflr/Taskdate/actions/runs/46022264052) succeeded for PHP syntax, parser, task-write safety, event payload filtering, and staging verification at commit `56e0f42fb32e9b371afd2530c906d0f402bb25af`. This does not establish Nextcloud 35 integration or public API compatibility.

## Before approval

- [x] Automated PHP syntax and baseline parser tests passed in run 45755694292 (previous commit).
- [x] Expanded parser regression workflow passed on commit 8361851f266b708b4bebdc5e4a681ee95ce0077a (GitHub Actions run 45819377473).
- [ ] Test task creation and edits against a disposable Nextcloud 35 test installation, including CalDAV update events, title cleanup, timezone, START/DUE handling and recurring tasks.
- [ ] Exercise `Zahnarzt 261112 1530`, confirm DUE 2026-11-12 15:30 and SUMMARY `Zahnarzt 261112`.
- [ ] Verify existing manual DUE remains unchanged, including after changing SUMMARY.
- [ ] Check that creating an event on a read-only/shared calendar is ignored without damage.
- [ ] Verify how Nextcloud Tasks populates DTSTART; revise conservative skip to support normal tasks without allowing DUE earlier than DTSTART.
- [ ] Check Europe/Berlin summer/winter timezone changes and recurrence behavior.
- [ ] The documented public calendar API is read-only for existing objects; design and validate a supported task update path before release. Do not assume ICreateFromString is an update API. Replace internal Nextcloud class dependency `OCA\DAV\CalDAV\CalDavBackend` with a compliant supported public interface or secure supported integration. App Store policy requires public APIs.
- [ ] Confirm the licensing and rights for all code; the repository LICENSE is GPL-3.0 while current appinfo describes AGPL. Do not silently relicense.
- [ ] Add an app icon (`img/app.svg`) and screenshots, then verify correct rendering.
- [ ] Review description, contact / issue URL, and author identification.
- [ ] Register the `taskdate` App ID with the Nextcloud App Store.
- [ ] Create signing key **locally**, never commit the private key.
- [ ] Submit CSR via https://github.com/nextcloud/app-certificate-requests and receive the certificate.
- [ ] Sign the exact staged distribution with `occ integrity:sign-app`.
- [ ] Package signed app directory as `taskdate.tar.gz` and upload to a GitHub release.
- [ ] Submit release URL via Nextcloud App Store dashboard.

## API risk (release blocker)

The listener currently injects `OCA\\DAV\\CalDAV\\CalDavBackend` and calls
`getCalendarObject` and `updateCalendarObject`. The OCP event can contain raw iCalendar content; the listener now uses it
when available and falls back to internal DAV read methods otherwise. This
is **not proof that the App Store will accept the dependency or that all
calendars are safe to write**. Do not merge/release until a supported write architecture
and a Nextcloud 35 integration test are established.

## Private key and CSR (on your trusted computer)

```bash
openssl req -nodes -newkey rsa:4096 -keyout taskdate.key -out taskdate.csr -subj "/CN=taskdate"
```

Keep taskdate.key offline/private. Only submit taskdate.csr.

## Prepare runtime-only staging

Run `bash scripts/prepare-release.sh` to create `dist/taskdate` containing runtime files **and the repository LICENSE file**. Do not upload or publish this directory before signing. The script deliberately excludes `.github/`, `tests/`, and private keys.

## Signing (after certificate approval)

```bash
php occ integrity:sign-app --privateKey=/secure/taskdate.key --certificate=/secure/taskdate.crt --path=/absolute/path/to/taskdate
cd /absolute/path/to/dist
tar -czf taskdate.tar.gz taskdate
```

Use a clean staging directory containing runtime files and LICENSE; no tests, GitHub workflows or private keys. Run the signing command **before** archiving, and do not edit any signed files.

Official Nextcloud documentation:
- https://docs.nextcloud.com/server/stable/developer_manual/app_publishing_maintenance/release_process.html
- https://docs.nextcloud.com/server/stable/developer_manual/app_publishing_maintenance/code_signing.html
- https://docs.nextcloud.com/server/stable/developer_manual/app_publishing_maintenance/publishing.html
