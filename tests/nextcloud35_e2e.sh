#!/usr/bin/env bash
# Only for a disposable local Nextcloud 35 Docker instance.
set -euo pipefail
base="http://127.0.0.1:18085"
user="admin"
pass="taskdate-e2e-test"
calendar="$base/remote.php/dav/calendars/$user/taskdate-e2e"
temp="$(mktemp -d)"
trap 'rm -rf "$temp"' EXIT
check() {
  case ",$2," in *",$1,"*) ;; *) echo "HTTP $1 instead of $2" >&2; exit 1;; esac
}
cat > "$temp/calendar.xml" <<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<d:mkcalendar xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav">
<d:set><d:prop><d:displayname>TaskDate E2E</d:displayname>
<c:supported-calendar-component-set><c:comp name="VTODO"/></c:supported-calendar-component-set>
</d:prop></d:set></d:mkcalendar>
XML
http="$(curl -fsS -u "$user:$pass" -X MKCALENDAR -H 'Content-Type: application/xml' \
  --data-binary @"$temp/calendar.xml" -o "$temp/mkresponse" -w '%{http_code}' "$calendar/" || true)"
check "$http" "201,204"
make_ics() {
  local filename="$1" summary="$2" extra="$3"
  {
    printf 'BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//TaskDate//EN\r\nBEGIN:VTODO\r\n'
    printf 'UID:%s@example.invalid\r\nDTSTAMP:20261127T000000Z\r\nSUMMARY:%s\r\n' "$filename" "$summary"
    if test -n "$extra"; then printf '%s\r\n' "$extra"; fi
    printf 'END:VTODO\r\nEND:VCALENDAR\r\n'
  } > "$temp/$filename.ics"
}
put() {
  local filename="$1" source="$2" out="$3"
  http="$(curl -sS -u "$user:$pass" -X PUT -H 'Content-Type: text/calendar' \
    --data-binary @"$source" -o "$temp/$out" -w '%{http_code}' "$calendar/$filename.ics")"
  check "$http" "201,204"
}
fetch() {
  local filename="$1" output="$2"
  http="$(curl -sS -u "$user:$pass" -o "$temp/$output" -w '%{http_code}' "$calendar/$filename.ics")"
  check "$http" "200"
}
make_ics "auto" "Zahnarzt 261112 1530" ""
put "auto" "$temp/auto.ics" "put-auto"
fetch "auto" "auto-after.ics"
grep -Fq 'SUMMARY:Zahnarzt 261112' "$temp/auto-after.ics"
grep -Fq 'DUE:20261112T153000' "$temp/auto-after.ics"
grep -Fq 'X-TASKDATE-AUTO-DUE:20261112T153000' "$temp/auto-after.ics"
echo "PASS: task created with automatic due and clean title"
sed 's/SUMMARY:Zahnarzt 261112/SUMMARY:Zahnarzt 261113/' "$temp/auto-after.ics" > "$temp/updated.ics"
put "auto" "$temp/updated.ics" "put-update"
fetch "auto" "updated-after.ics"
grep -Fq 'DUE:20261113T153000' "$temp/updated-after.ics"
echo "PASS: task date updated with preserved time"
make_ics "manual" "Arzt 261112 1530" "DUE:20261112T120000"
put "manual" "$temp/manual.ics" "put-manual"
fetch "manual" "manual-after.ics"
grep -Fq 'SUMMARY:Arzt 261112 1530' "$temp/manual-after.ics"
grep -Fq 'DUE:20261112T120000' "$temp/manual-after.ics"
if grep -Fq 'X-TASKDATE-AUTO-DUE:' "$temp/manual-after.ics"; then exit 1; fi
echo "PASS: user-defined due not overwritten"
make_ics "start" "Meeting 261112 1530" "DTSTART:20261112T090000"
put "start" "$temp/start.ics" "put-start"
fetch "start" "start-after.ics"
grep -Fq 'SUMMARY:Meeting 261112 1530' "$temp/start-after.ics"
if grep -Fq 'X-TASKDATE-AUTO-DUE:' "$temp/start-after.ics"; then exit 1; fi
echo "PASS: DTSTART is excluded"
http="$(curl -sS -u "$user:incorrect-password" -X PUT -H 'Content-Type: text/calendar' \
  --data-binary @"$temp/auto.ics" -o "$temp/unauth" -w '%{http_code}' "$calendar/unauth.ics")"
check "$http" "401,403"
echo "PASS: unauthorized PUT rejected"
echo "Nextcloud 35 CalDAV end-to-end checks passed"
