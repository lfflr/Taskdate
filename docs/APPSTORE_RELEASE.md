# TaskDate: Nextcloud App Store preparation

## Status (2026-11-26)

Repository: https://github.com/lfflr/Taskdate
Development branch: release/appstore-readiness-2026-11-26

**NOT RELEASED.** Version 0.2.3 in metadata is a prerelease candidate, not a stable/public claim. No App Store registration, certificate, signed release or live Nextcloud 35 integration test is confirmed.

## Before approval

- [ ] Run the PHP checks and review CI evidence.
- [ ] Test task creation and edits against a disposable Nextcloud 35 test installation, including CalDAV update events, title cleanup, timezone, START/DUE handling and recurring tasks.
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

## Private key and CSR (on your trusted computer)

```bash
openssl req -nodes -newkey rsa:4096 -keyout taskdate.key -out taskdate.csr -subj "/CN=taskdate"
```

Keep taskdate.key offline/private. Only submit taskdate.csr.

## Signing (after certificate approval)

```bash
php occ integrity:sign-app --privateKey=/secure/taskdate.key --certificate=/secure/taskdate.crt --path=/absolute/path/to/taskdate
tar -czf taskdate.tar.gz taskdate
```

Use a clean staging directory containing only runtime files; no tests, GitHub workflows or private keys. Run the signing command **before** archiving, and do not edit any signed files.

Official Nextcloud documentation:
- https://docs.nextcloud.com/server/stable/developer_manual/app_publishing_maintenance/release_process.html
- https://docs.nextcloud.com/server/stable/developer_manual/app_publishing_maintenance/code_signing.html
- https://docs.nextcloud.com/server/stable/developer_manual/app_publishing_maintenance/publishing.html
