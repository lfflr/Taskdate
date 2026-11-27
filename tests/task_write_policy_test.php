<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/TaskWritePolicy.php';

use OCA\TaskDate\TaskWritePolicy;

$cases = [
    ['SUMMARY' => 'Zahnarzt 261112 1530'],
    ['SUMMARY' => 'Zahnarzt 261112 1530', 'RRULE' => 'FREQ=DAILY'],
    ['SUMMARY' => 'Zahnarzt 261112 1530', 'RECURRENCE-ID' => '20261112T153000'],
    ['SUMMARY' => 'Zahnarzt 261112 1530', 'DTSTART' => '20261112T120000'],
    ['DUE' => '20261112T153000'],
];
$expected = [true, false, false, false, false];
foreach ($cases as $i => $properties) {
    $todo = (object)$properties;
    if (TaskWritePolicy::allowsAutomaticDue($todo) !== $expected[$i]) {
        throw new RuntimeException('TaskWritePolicy case ' . $i . ' failed');
    }
}
echo 'TaskWritePolicy: ' . count($cases) . " cases passed\n";
