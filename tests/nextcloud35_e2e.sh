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
<c:mkcalendar xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav">
<d:set><d:prop><d:displayname>TaskDate E2E</d:displayname>
<c:supported-calendar-component-set><c:comp name="VTODO"/></c:supported-calendar-component-set>
</d:prop></d:set></c:mkcalendar>
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
make_ics "allday" "Ganztag 261112" ""
put "allday" "$temp/allday.ics" "put-allday"
fetch "allday" "allday-after.ics"
grep -Fq 'DUE;VALUE=DATE:20261112' "$temp/allday-after.ics"
grep -Fq 'X-TASKDATE-AUTO-DUE:20261112' "$temp/allday-after.ics"
echo "PASS: date-only all-day DUE"

# Client manually removes DUE but leaves the old TaskDate marker.
sed '/^DUE:/d' "$temp/updated-after.ics" > "$temp/removed-due.ics"
put "auto" "$temp/removed-due.ics" "put-removed-due"
fetch "auto" "removed-after.ics"
if grep -Eq '^DUE(;|:)' "$temp/removed-after.ics"; then
  echo "FAIL: manually deleted due date recreated" >&2
  exit 1
fi
echo "PASS: manually deleted DUE not recreated"


# Repeat and malformed content should never receive TaskDate's auto-due.
make_ics "recurring" "Wiederkehrend 261112 1530" "RRULE:FREQ=DAILY;COUNT=3"
put "recurring" "$temp/recurring.ics" "put-recurring"
fetch "recurring" "recurring-after.ics"
grep -Fq 'SUMMARY:Wiederkehrend 261112 1530' "$temp/recurring-after.ics"
if grep -Fq 'X-TASKDATE-AUTO-DUE:' "$temp/recurring-after.ics"; then
  echo "FAIL: recurrence unexpectedly rewritten" >&2
  exit 1
fi
echo "PASS: RRULE task remains unchanged"

# Completed and cancelled tasks remain unchanged by TaskDate even if their
# titles contain parseable dates. These are real NC35 CalDAV round trips.
for status in COMPLETED CANCELLED IN-PROCESS; do
  case "$status" in
    COMPLETED) filename="completed-status" ;;
    CANCELLED) filename="cancelled-status" ;;
    *) filename="in-process-status" ;;
  esac
  make_ics "$filename" "Fertig 261112 1530" "STATUS:$status"
  put "$filename" "$temp/$filename.ics" "put-$filename"
  fetch "$filename" "$filename-after.ics"
  grep -Fq 'SUMMARY:Fertig 261112 1530' "$temp/$filename-after.ics"
  if grep -Fq 'X-TASKDATE-AUTO-DUE:' "$temp/$filename-after.ics"; then
    echo "FAIL: status $status unexpectedly auto-managed" >&2
    exit 1
  fi
  echo "PASS: $status VTODO unchanged"
done

# A normal actionable task is transformed, but subsequent completion
# must preserve its managed DUE, marker and all other properties.
make_ics "lifecycle" "Aktion 261112 1405" "STATUS:NEEDS-ACTION"
put "lifecycle" "$temp/lifecycle.ics" "put-lifecycle"
fetch "lifecycle" "lifecycle-created.ics"
grep -Fq 'STATUS:NEEDS-ACTION' "$temp/lifecycle-created.ics"
grep -Fq 'SUMMARY:Aktion 261112' "$temp/lifecycle-created.ics"
grep -Fq 'DUE:20261112T140500' "$temp/lifecycle-created.ics"
grep -Fq 'X-TASKDATE-AUTO-DUE:20261112T140500' "$temp/lifecycle-created.ics"
echo "PASS: NEEDS-ACTION is auto-managed"

sed -e 's/STATUS:NEEDS-ACTION/STATUS:COMPLETED/' \
    -e 's/SUMMARY:Aktion 261112/SUMMARY:Aktion 261113/' \
    "$temp/lifecycle-created.ics" > "$temp/lifecycle-completed.ics"
put "lifecycle" "$temp/lifecycle-completed.ics" "put-lifecycle-completed"
fetch "lifecycle" "lifecycle-after.ics"
grep -Fq 'STATUS:COMPLETED' "$temp/lifecycle-after.ics"
grep -Fq 'SUMMARY:Aktion 261113' "$temp/lifecycle-after.ics"
grep -Fq 'DUE:20261112T140500' "$temp/lifecycle-after.ics"
grep -Fq 'X-TASKDATE-AUTO-DUE:20261112T140500' "$temp/lifecycle-after.ics"
echo "PASS: completed task retains original due and user-edited summary"

# A previously managed task marked cancelled must also retain its due.
sed -e 's/STATUS:COMPLETED/STATUS:CANCELLED/' \
    -e 's/SUMMARY:Aktion 261113/SUMMARY:Aktion 261114/' \
    "$temp/lifecycle-after.ics" > "$temp/lifecycle-cancelled.ics"
put "lifecycle" "$temp/lifecycle-cancelled.ics" "put-lifecycle-cancelled"
fetch "lifecycle" "lifecycle-cancelled-after.ics"
grep -Fq 'STATUS:CANCELLED' "$temp/lifecycle-cancelled-after.ics"
grep -Fq 'SUMMARY:Aktion 261114' "$temp/lifecycle-cancelled-after.ics"
grep -Fq 'DUE:20261112T140500' "$temp/lifecycle-cancelled-after.ics"
echo "PASS: cancelled task retains existing due"

# Third-party clients can carry explicitly zoned DUE properties. Preserve
# them and do not turn UTC or TZID data into floating time.
make_ics "utc" "UTC Termin 261112 1530" "DUE:20261112T120000Z"
put "utc" "$temp/utc.ics" "put-utc"
fetch "utc" "utc-after.ics"
grep -Fq 'SUMMARY:UTC Termin 261112 1530' "$temp/utc-after.ics"
grep -Fq 'DUE:20261112T120000Z' "$temp/utc-after.ics"
if grep -Fq 'X-TASKDATE-AUTO-DUE:' "$temp/utc-after.ics"; then
  echo "FAIL: UTC DUE unexpectedly auto-managed" >&2
  exit 1
fi
echo "PASS: UTC DUE preserved"

make_ics "tzid" "Zone Termin 261112 1530" "DUE;TZID=Europe/Berlin:20261112T120000"
put "tzid" "$temp/tzid.ics" "put-tzid"
fetch "tzid" "tzid-after.ics"
grep -Fq 'SUMMARY:Zone Termin 261112 1530' "$temp/tzid-after.ics"
grep -Fq 'DUE;TZID=Europe/Berlin:20261112T120000' "$temp/tzid-after.ics"
if grep -Fq 'X-TASKDATE-AUTO-DUE:' "$temp/tzid-after.ics"; then
  echo "FAIL: TZID DUE unexpectedly auto-managed" >&2
  exit 1
fi
echo "PASS: TZID DUE preserved"

# Verify that unrelated properties survive automatic title rewriting.
make_ics "metadata" "Metadaten 261112 1405" "DESCRIPTION:Notiz zum Termin"
put "metadata" "$temp/metadata.ics" "put-metadata"
fetch "metadata" "metadata-after.ics"
grep -Fq 'DESCRIPTION:Notiz zum Termin' "$temp/metadata-after.ics"
grep -Fq 'SUMMARY:Metadaten 261112' "$temp/metadata-after.ics"
grep -Fq 'DUE:20261112T140500' "$temp/metadata-after.ics"
echo "PASS: VTODO description preserved after transformation"

# A second account must receive the same application behavior, but cannot
# write into the first account's private calendar.
other="taskdateuser"
other_pass="taskdate-second-user"
other_calendar="$base/remote.php/dav/calendars/$other/taskdate-e2e"
http="$(curl -sS -u "$other:$other_pass" -X MKCALENDAR \
  -H 'Content-Type: application/xml' --data-binary @"$temp/calendar.xml" \
  -o "$temp/mk-other" -w '%{http_code}' "$other_calendar/")"
check "$http" "201,204"
make_ics "other" "Anderer 261112 1537" ""
http="$(curl -sS -u "$other:$other_pass" -X PUT -H 'Content-Type: text/calendar' \
  --data-binary @"$temp/other.ics" -o "$temp/put-other" -w '%{http_code}' "$other_calendar/other.ics")"
check "$http" "201,204"
http="$(curl -sS -u "$other:$other_pass" -o "$temp/other-after.ics" -w '%{http_code}' "$other_calendar/other.ics")"
check "$http" "200"
grep -Fq 'SUMMARY:Anderer 261112' "$temp/other-after.ics"
grep -Fq 'DUE:20261112T153700' "$temp/other-after.ics"
echo "PASS: separate user receives TaskDate transformation"

http="$(curl -sS -u "$other:$other_pass" -X PUT -H 'Content-Type: text/calendar' \
  --data-binary @"$temp/other.ics" -o "$temp/forbidden" -w '%{http_code}' "$calendar/forbidden.ics")"
check "$http" "403,404"
http="$(curl -sS -u "$user:$pass" -o "$temp/no-forbidden" -w '%{http_code}' "$calendar/forbidden.ics")"
check "$http" "404"
echo "PASS: cross-user write access blocked and no task created"

# Check per-user local timezone at spring DST gap / autumn fold.
for spec in "spring 270328" "fall 271031"; do
  set -- $spec
  label="$1"
  datepart="$2"
  make_ics "berlin-$label" "Berlin $datepart 0230" ""
  put "berlin-$label" "$temp/berlin-$label.ics" "put-berlin-$label"
  fetch "berlin-$label" "berlin-$label-after.ics"
  grep -Fq "SUMMARY:Berlin $datepart 0230" "$temp/berlin-$label-after.ics"
  if grep -Eq '^DUE(;|:)' "$temp/berlin-$label-after.ics"; then
    echo "FAIL: Berlin DST gap/fold was changed" >&2
    exit 1
  fi
  make_ics "utc-$label" "UTC $datepart 0230" ""
  http="$(curl -sS -u "$other:$other_pass" -X PUT -H 'Content-Type: text/calendar' \
    --data-binary @"$temp/utc-$label.ics" -o "$temp/put-utc-$label" -w '%{http_code}' \
    "$other_calendar/utc-$label.ics")"
  check "$http" "201,204"
  http="$(curl -sS -u "$other:$other_pass" -o "$temp/utc-$label-after.ics" \
    -w '%{http_code}' "$other_calendar/utc-$label.ics")"
  check "$http" "200"
  grep -Fq "SUMMARY:UTC $datepart" "$temp/utc-$label-after.ics"
  grep -Fq "DUE:20${datepart}T023000" "$temp/utc-$label-after.ics"
  echo "PASS: timezone-specific DST $label for Europe/Berlin versus UTC"
done

# Relative words remain in the title. Re-saving a previously auto-managed
# task must never move DUE just because 'morgen' is evaluated on another day.
make_ics "relative" "Relativ morgen 1405" ""
put "relative" "$temp/relative.ics" "put-relative"
fetch "relative" "relative-before.ics"
grep -Fq 'X-TASKDATE-AUTO-DUE:' "$temp/relative-before.ics"
grep -Fq 'DUE:' "$temp/relative-before.ics"
put "relative" "$temp/relative-before.ics" "put-relative-again"
fetch "relative" "relative-after.ics"
before_due="$(grep '^DUE:' "$temp/relative-before.ics" | tr -d '\r')"
after_due="$(grep '^DUE:' "$temp/relative-after.ics" | tr -d '\r')"
if test "$before_due" != "$after_due"; then
  echo "FAIL: relative date shifted on repeat PUT" >&2
  exit 1
fi
echo "PASS: relative due date remains stable on repeat PUT"

# A stale ETag must not overwrite the task.
http="$(curl -sS -u "$user:$pass" -X PUT -H 'Content-Type: text/calendar' \
  -H 'If-Match: "definitely-stale-etag"' \
  --data-binary @"$temp/manual.ics" -o "$temp/stale" -w '%{http_code}' "$calendar/manual.ics")"
check "$http" "412"
fetch "manual" "manual-still.ics"
grep -Fq 'DUE:20261112T120000' "$temp/manual-still.ics"
echo "PASS: ETag precondition prevents overwrite"

http="$(curl -sS -u "$user:incorrect-password" -X PUT -H 'Content-Type: text/calendar' \
  --data-binary @"$temp/auto.ics" -o "$temp/unauth" -w '%{http_code}' "$calendar/unauth.ics")"
check "$http" "401,403"
echo "PASS: unauthorized PUT rejected"
echo "Nextcloud CalDAV end-to-end checks passed"
