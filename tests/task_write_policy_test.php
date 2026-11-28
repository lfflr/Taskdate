<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/TaskWritePolicy.php';

use OCA\TaskDate\TaskWritePolicy;

$cases = [
    ['SUMMARY' => 'Zahnarzt 261112 1530'],
    ['SUMMARY' => 'Zahnarzt 261112 1530', 'RRULE' => 'FREQ=DAILY'],
    ['SUMMARY' => 'Zahnarzt 261112 1530', 'RECURRENCE-ID' => '20261112T153000'],
    ['SUMMARY' => 'Zahnarzt 261112 1530', 'DTSTART' => '20261112T120000'],
    ['SUMMARY' => 'Termin 261112 1530', 'RDATE' => '20261112T153000'],
    ['SUMMARY' => 'Termin 261112 1530', 'EXDATE' => '20261113T153000'],
    ['SUMMARY' => 'Termin 261112 1530', 'DURATION' => 'PT1H'],
    ['SUMMARY' => 'Termin 261112 1530', 'COMPLETED' => '20261112T154000Z'],
    ['SUMMARY' => 'Termin 261112 1530', 'STATUS' => 'COMPLETED'],
    ['SUMMARY' => 'Termin 261112 1530', 'STATUS' => 'CANCELLED'],
    ['SUMMARY' => 'Termin 261112 1530', 'STATUS' => 'IN-PROCESS'],
    ['SUMMARY' => 'Termin 261112 1530', 'STATUS' => 'NEEDS-ACTION'],
    ['DUE' => '20261112T153000'],
];
$expected = [true, false, false, false, false, false, false, false, false, false, false, true, false];
foreach ($cases as $i => $properties) {
    $todo = (object)$properties;
    if (TaskWritePolicy::allowsAutomaticDue($todo) !== $expected[$i]) {
        throw new RuntimeException('TaskWritePolicy case ' . $i . ' failed');
    }
}
echo 'TaskWritePolicy: ' . count($cases) . " cases passed\n";
