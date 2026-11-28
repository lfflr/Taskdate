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

$plugin = new TaskDateSabrePlugin(static fn(): DateTimeZone => new DateTimeZone('Europe/Berlin'));
$server = new Server();
$server->addPlugin($plugin);
if ($server->getPlugin('taskdate') !== $plugin) {
    throw new RuntimeException('Sabre plugin registration failed');
}


// Mimic Nextcloud: the DAV app loads third-party plugins while handling
// beforeMethod:*, after the event listener list has already been assembled.
// Our method:PUT listener must still be present for the subsequent method event.
$lateServer = new Server();
$latePlugin = new TaskDateSabrePlugin(static fn(): DateTimeZone => new DateTimeZone('Europe/Berlin'));
$lateServer->on('beforeMethod:*', static function () use ($lateServer, $latePlugin): void {
    $lateServer->addPlugin($latePlugin);
}, 30);
$lateRequest = new Request('PUT', '/remote.php/dav/calendars/alice/tasks/late.ics');
$lateRequest->setBaseUrl('/remote.php/dav/');
$lateServer->emit('beforeMethod:PUT', [$lateRequest, new Response()]);
$listeners = $lateServer->listeners('method:PUT');
if (count($listeners) < 2
    || $listeners[0] !== [$latePlugin, 'handlePut']) {
    throw new RuntimeException('TaskDate must run before Sabre CorePlugin PUT handler');
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
    $plugin = new TaskDateSabrePlugin(static fn(): DateTimeZone => new DateTimeZone('Europe/Berlin'));
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
    taskIcs('Ausnahme 261112 1530', null, null, 'EXDATE:20261113T153000'),
    taskIcs('Zusatztermin 261112 1530', null, null, 'RDATE:20261114T153000'),
    taskIcs('Dauer 261112 1530', null, null, 'DURATION:PT1H'),
    taskIcs('Erledigt 261112 1530', null, null, 'STATUS:COMPLETED'),
    taskIcs('Erledigt mit Zeit 261112 1530', null, null, 'COMPLETED:20261112T154000Z'),
    taskIcs('Abgesagt 261112 1530', null, null, 'STATUS:CANCELLED'),
    taskIcs('In Bearbeitung 261112 1530', null, null, 'STATUS:IN-PROCESS'),
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

// Real request-body streams are consumed by getBodyAsString(). All paths must
// preserve an unchanged payload after inspection, including malformed ICS.
$streamCases = [
    taskIcs('Manually scheduled 261112 1530', '20261112T090000'),
    taskIcs('Recurring 261112 1530', null, null, 'RRULE:FREQ=DAILY'),
    "this is not valid iCalendar\r\n",
];
foreach ($streamCases as $i => $input) {
    $handle = fopen('php://temp', 'w+');
    if ($handle === false) {
        throw new RuntimeException('Could not create stream');
    }
    fwrite($handle, $input);
    rewind($handle);
    $request = new Request('PUT', '/remote.php/dav/calendars/alice/tasks/stream.ics', [
        'Content-Type' => 'text/calendar',
        'Content-Length' => (string)strlen($input),
    ], $handle);
    $request->setBaseUrl('/remote.php/dav/');
    (new TaskDateSabrePlugin(static fn(): DateTimeZone => new DateTimeZone('Europe/Berlin')))->handlePut($request, new Response());
    if ($request->getBodyAsString() !== $input) {
        throw new RuntimeException("Stream case $i lost original body");
    }
    fclose($handle);
}

$input = taskIcs('Stream test 261112 1530');
$stream = fopen('php://temp', 'w+');
fwrite($stream, $input);
rewind($stream);
$request = new Request('PUT', '/remote.php/dav/calendars/alice/tasks/new.ics', [
    'Content-Type' => 'text/calendar', 'Content-Length' => (string)strlen($input),
], $stream);
$request->setBaseUrl('/remote.php/dav/');
(new TaskDateSabrePlugin(static fn(): DateTimeZone => new DateTimeZone('Europe/Berlin')))->handlePut($request, new Response());
if ((string)Reader::read($request->getBodyAsString())->VTODO->SUMMARY !== 'Stream test 261112') {
    throw new RuntimeException('Stream rewrite failed');
}
fclose($stream);


// Foreign calendar clients may send UTC dates, explicit TZIDs or multiple
// components. These documents must be forwarded byte-for-byte, not partially
// converted into floating local times.
$foreign = [
    taskIcs('UTC 261112 1530', '20261112T120000Z',
        '20261112T120000Z'),
    str_replace(
        'DUE:20261112T120000',
        'DUE;TZID=Europe/Berlin:20261112T120000',
        taskIcs('Zone 261112 1530', '20261112T120000',
            '20261112T120000')
    ),
    str_replace(
        'END:VCALENDAR',
        "BEGIN:VTODO\r\nUID:another@example.invalid\r\nSUMMARY:Second 261112 1530\r\nEND:VTODO\r\nEND:VCALENDAR",
        taskIcs('First 261112 1530')
    ),
    str_replace(
        'BEGIN:VTODO',
        "BEGIN:VTIMEZONE\r\nTZID:Europe/Berlin\r\nEND:VTIMEZONE\r\nBEGIN:VTODO",
        taskIcs('With timezone 261112 1530')
    ),
    str_replace(
        'END:VCALENDAR',
        "BEGIN:VEVENT\r\nUID:event@example.invalid\r\nDTSTAMP:20261110T000000Z\r\nDTSTART:20261112T140000\r\nSUMMARY:Event\r\nEND:VEVENT\r\nEND:VCALENDAR",
        taskIcs('Mixed 261112 1530')
    ),
];
foreach ($foreign as $i => $ics) {
    [$unchangedBody] = runPut($ics);
    if ($unchangedBody !== $ics) {
        throw new RuntimeException("External timezone/multicomponent case $i was changed");
    }
}
echo "TaskDate SabreDAV request tests: 5 rewrites, 12 exclusions, 4 stream cases, 5 foreign ICS cases passed\n";


