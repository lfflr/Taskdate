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
];
foreach ($cases as $i => [$summary, $due, $managedDue, $expected]) {
    $actual = TaskUpdatePlanner::plan($summary, $due, $managedDue, $now);
    if ($actual !== $expected) {
        throw new RuntimeException("Planner case $i failed: " . var_export($actual, true));
    }
}
echo 'TaskUpdatePlanner: ' . count($cases) . " cases passed\n";
