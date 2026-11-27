<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/CalendarPayload.php';

use OCA\TaskDate\CalendarPayload;

$valid = ['uri' => 'test.ics', 'calendardata' => "BEGIN:VCALENDAR\r\nEND:VCALENDAR\r\n"];
if (CalendarPayload::uri($valid) !== 'test.ics'
    || CalendarPayload::calendarData($valid) !== $valid['calendardata']) {
    throw new RuntimeException('Valid calendar event payload rejected');
}
$invalid = [
    [],
    ['uri' => null, 'calendardata' => null],
    ['uri' => '', 'calendardata' => ''],
    ['uri' => 123, 'calendardata' => ['not-a-string']],
];
foreach ($invalid as $payload) {
    if (CalendarPayload::uri($payload) !== null
        || CalendarPayload::calendarData($payload) !== null) {
        throw new RuntimeException('Invalid calendar event payload accepted');
    }
}
echo "CalendarPayload: 5 cases passed\n";
