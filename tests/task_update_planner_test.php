<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/DateParser.php';
require_once __DIR__ . '/../lib/TaskUpdatePlanner.php';

use OCA\TaskDate\TaskUpdatePlanner;

$now = new DateTimeImmutable('2026-11-10 09:00:00', new DateTimeZone('Europe/Berlin'));
$cases = [
    // summary, current DUE, automatically managed DUE, expected plan
    ['Zahnarzt 261112 1530', null, null, ['due' => '20261112T153000', 'summary' => 'Zahnarzt 261112']],
    ['Zahnarzt 261112 1537', null, null, ['due' => '20261112T153700', 'summary' => 'Zahnarzt 261112']],
    ['Zahnarzt 261112', null, null, ['due' => '20261112', 'summary' => 'Zahnarzt 261112']],
    ['Zahnarzt 261112', '20261112T153000', '20261112T153000', null],
    ['Zahnarzt 261113', '20261112T153000', '20261112T153000', ['due' => '20261113T153000', 'summary' => 'Zahnarzt 261113']],
    ['Zahnarzt 261112 1405', '20261112T153000', '20261112T153000', ['due' => '20261112T140500', 'summary' => 'Zahnarzt 261112']],
    ['Zahnarzt ohne Datum', '20261112T153000', '20261112T153000', ['due' => null, 'summary' => 'Zahnarzt ohne Datum']],
    ['Zahnarzt ohne Datum', null, null, null],
    ['Zahnarzt 261112 1530', '20261112T140000', null, null],
    ['Zahnarzt 261112 1530', '20261112T140000', '20261112T153000', null],
    ['Zahnarzt 261112', '20261112', '20261112', null],
    ['Zahnarzt 261113', '20261112', '20261112', ['due' => '20261113', 'summary' => 'Zahnarzt 261113']],
    ['Zahnarzt 261112 1530', null, '20261111', null], // manual DUE deletion
    ['Termin 270328', '20270327T023000', '20270327T023000', null], // DST gap if time retained
    ['Termin 271031', '20271030T023000', '20271030T023000', null], // DST overlap if time retained
    ['Termin 270328', '20270327T013000', '20270327T013000', ['due' => '20270328T013000', 'summary' => 'Termin 270328']],
    ['Termin 271031', '20271030T033000', '20271030T033000', ['due' => '20271031T033000', 'summary' => 'Termin 271031']],
];
foreach ($cases as $i => [$summary, $due, $managedDue, $expected]) {
    $actual = TaskUpdatePlanner::plan($summary, $due, $managedDue, $now);
    if ($actual !== $expected) {
        throw new RuntimeException("Planner case $i failed: " . var_export($actual, true));
    }
}

// Same instant in UTC and Europe/Berlin must produce a different civil day.
// This simulates two authenticated users close to local midnight.
$instant = new DateTimeImmutable('2026-11-28T23:30:00+00:00');
$relativeCases = [
    ['UTC', 'Erinnerung heute 1405', '20261128T140500'],
    ['Europe/Berlin', 'Erinnerung heute 1405', '20261129T140500'],
    ['UTC', 'Erinnerung morgen 1405', '20261129T140500'],
    ['Europe/Berlin', 'Erinnerung morgen 1405', '20261130T140500'],
    ['America/New_York', 'Erinnerung heute 1405', '20261128T140500'],
];
foreach ($relativeCases as [$timezone, $summary, $expectedDue]) {
    $nowForUser = $instant->setTimezone(new DateTimeZone($timezone));
    $planned = TaskUpdatePlanner::plan($summary, null, null, $nowForUser);
    if ($planned === null || $planned['due'] !== $expectedDue) {
        throw new RuntimeException('Wrong user-local civil day for ' . $timezone . ': ' . var_export($planned, true));
    }
}

echo 'TaskUpdatePlanner: ' . count($cases) . ' scenarios plus ' . count($relativeCases) . " user-local midnight cases passed\n";
