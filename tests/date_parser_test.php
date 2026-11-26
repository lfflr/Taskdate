<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/DateParser.php';

use OCA\TaskDate\DateParser;

$now = new DateTimeImmutable('2026-11-10 09:00:00', new DateTimeZone('Europe/Berlin'));
$cases = [
    'Zahnarzt 20261112 1400' => ['2026-11-12 14:00', true],
    'Zahnarzt 261112 1530' => ['2026-11-12 15:30', true],
    'Zahnarzt 261112' => ['2026-11-12 00:00', false],
    'Zahnarzt 261112 1400' => ['2026-11-12 14:00', true],
    'Besprechung 261112 14:30' => ['2026-11-12 14:30', true],
    'Besprechung 261112 14 Uhr' => ['2026-11-12 14:00', true],
    'Besprechung 261112 14.30 Uhr' => ['2026-11-12 14:30', true],
    'Neujahr 270101 0915' => ['2027-01-01 09:15', true],
    'Termin 261231 2345' => ['2026-12-31 23:45', true],
    'Ungültiges Datum 261332 1400' => null,
    'Ungültiger Februar 260230 1400' => null,
    'Zahnarzt 20261112 1430' => ['2026-11-12 14:30', true],
    'Zahnarzt 12.11.2026 14:30' => ['2026-11-12 14:30', true],
    'Zahnarzt 12.11.26 14.30 Uhr' => ['2026-11-12 14:30', true],
    'Zahnarzt 2026-11-12' => ['2026-11-12 00:00', false],
    'Zahnarzt 12.11.' => ['2026-11-12 00:00', false],
    'Bericht morgen 14 Uhr' => ['2026-11-11 14:00', true],
    'Steuer Freitag' => ['2026-11-13 00:00', false],
    'Anruf heute' => ['2026-11-10 00:00', false],
    'Unbekannt 20261340' => null,
    'Nur ein Titel' => null,
];

foreach ($cases as $title => $expected) {
    $actual = DateParser::parse($title, $now);
    if ($expected === null) {
        if ($actual !== null) {
            throw new RuntimeException("Unexpected date for: $title");
        }
        continue;
    }
    if ($actual === null || $actual[0]->format('Y-m-d H:i') !== $expected[0] || $actual[1] !== $expected[1]) {
        throw new RuntimeException("Wrong result for: $title; got " . var_export($actual, true));
    }
}

$cleanupCases = [
    'Zahnarzt 261112 1530' => 'Zahnarzt 261112',
    'Zahnarzt 20261112 1400' => 'Zahnarzt 20261112',
    'Termin 12.11.2026 14:30' => 'Termin 12.11.2026',
    'Termin 12.11.2026 14.30 Uhr' => 'Termin 12.11.2026',
];
foreach ($cleanupCases as $original => $expectedTitle) {
    $parsed = DateParser::parse($original, $now);
    if ($parsed === null || !$parsed[1]) {
        throw new RuntimeException('Missing time: ' . $original);
    }
    $cleaned = DateParser::withoutTime($original, $parsed[0]);
    if ($cleaned !== $expectedTitle) {
        throw new RuntimeException('Time removal failed: ' . $original . ' -> ' . $cleaned);
    }
}

echo 'DateParser: ' . count($cases) . ' parse cases and ' . count($cleanupCases) . " cleanup cases passed\n";
