<?php
declare(strict_types=1);

date_default_timezone_set('Europe/Berlin');

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../lib/DateParser.php';
require_once __DIR__ . '/../lib/TaskWritePolicy.php';
require_once __DIR__ . '/../lib/TaskUpdatePlanner.php';
require_once __DIR__ . '/../lib/Sabre/TaskDateSabrePlugin.php';

use OCA\TaskDate\Sabre\TaskDateSabrePlugin;
use Sabre\DAV\Server;
use Sabre\HTTP\Request;
use Sabre\HTTP\Response;
use Sabre\VObject\Reader;

$plugin = new TaskDateSabrePlugin();
$server = new Server();
$server->addPlugin($plugin);
if ($server->getPlugin('taskdate') !== $plugin) {
    throw new RuntimeException('Sabre plugin registration failed');
}

function taskIcs(string $summary, ?string $due = null, ?string $managed = null, ?string $extra = null): string {
    $props = [
        'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//TaskDate integration test//EN',
        'BEGIN:VTODO', 'UID:taskdate-example@example.invalid',
        'DTSTAMP:20261110T000000Z', 'SUMMARY:' . $summary,
    ];
    if ($due !== null) {
        $props[] = strlen($due) === 8 ? 'DUE;VALUE=DATE:' . $due : 'DUE:' . $due;
    }
    if ($managed !== null) {
        $props[] = 'X-TASKDATE-AUTO-DUE:' . $managed;
    }
    if ($extra !== null) {
        $props[] = $extra;
    }
    $props[] = 'END:VTODO';
    $props[] = 'END:VCALENDAR';
    return implode("\r\n", $props) . "\r\n";
}

function runPut(string $ics, string $path = 'calendars/alice/tasks/example.ics'): array {
    $url = '/remote.php/dav/' . $path;
    $request = new Request('PUT', $url, ['Content-Type' => 'text/calendar'], $ics);
    $request->setBaseUrl('/remote.php/dav/');
    $plugin = new TaskDateSabrePlugin();
    $plugin->handlePut($request, new Response());
    return [$request->getBodyAsString(), $request->getHeader('Content-Length')];
}

$cases = [
    [taskIcs('Zahnarzt 261112 1530'), 'Zahnarzt 261112', '20261112T153000', '20261112T153000'],
    [taskIcs('Zahnarzt 261112 1537'), 'Zahnarzt 261112', '20261112T153700', '20261112T153700'],
    [taskIcs('Zahnarzt 261112'), 'Zahnarzt 261112', '20261112', '20261112'],
    [taskIcs('Zahnarzt 261113', '20261112T153000', '20261112T153000'), 'Zahnarzt 261113', '20261113T153000', '20261113T153000'],
    [taskIcs('Zahnarzt ohne Datum', '20261112T153000', '20261112T153000'), 'Zahnarzt ohne Datum', null, null],
];
foreach ($cases as $i => [$before, $expectedTitle, $due, $managed]) {
    [$after, $length] = runPut($before);
    $todo = Reader::read($after)->VTODO;
    if ((string)$todo->SUMMARY !== $expectedTitle) {
        throw new RuntimeException("PUT case $i wrong summary: " . (string)$todo->SUMMARY);
    }
    $actualDue = isset($todo->DUE) ? (string)$todo->DUE : null;
    $actualManaged = isset($todo->{'X-TASKDATE-AUTO-DUE'})
        ? (string)$todo->{'X-TASKDATE-AUTO-DUE'} : null;
    if ($actualDue !== $due || $actualManaged !== $managed) {
        throw new RuntimeException("PUT case $i wrong due / auto marker");
    }
    if ((int)$length !== strlen($after)) {
        throw new RuntimeException("PUT case $i wrong Content-Length");
    }
    [$replayed] = runPut($after);
    if ($replayed !== $after) {
        throw new RuntimeException("PUT case $i not idempotent");
    }
}

$unchanged = [
    taskIcs('Zahnarzt 261112 1530', '20261112T090000'), // manual DUE
    taskIcs('Zahnarzt 261112 1530', null, null, 'DTSTART:20261112T090000'),
    taskIcs('Zahnarzt 261112 1530', null, null, 'RRULE:FREQ=DAILY'),
];
foreach ($unchanged as $i => $before) {
    [$after] = runPut($before);
    if ($after !== $before) {
        throw new RuntimeException("Unchanged case $i unexpectedly rewritten");
    }
}
[$after] = runPut(taskIcs('Zahnarzt 261112 1530'), 'files/alice/notes.ics');
if ($after !== taskIcs('Zahnarzt 261112 1530')) {
    throw new RuntimeException('Non-calendar PUT unexpectedly rewritten');
}
$event = str_replace(['BEGIN:VTODO', 'END:VTODO'], ['BEGIN:VEVENT', 'END:VEVENT'], taskIcs('Meeting 261112 1530'));
[$after] = runPut($event);
if ($after !== $event) {
    throw new RuntimeException('VEVENT unexpectedly rewritten');
}
echo "TaskDate SabreDAV request tests: 5 rewrites, 5 exclusions passed\n";
