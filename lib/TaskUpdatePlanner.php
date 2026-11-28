<?php
declare(strict_types=1);

namespace OCA\TaskDate;

use DateTimeImmutable;

/**
 * Computes an update without any DAV dependency or side effects.
 *
 * @return array{due: ?string, summary: string}|null
 * Null means do not change a task. A null due in a returned plan means
 * remove the previously auto-managed due date.
 */
final class TaskUpdatePlanner {
    public static function plan(
        string $summary,
        ?string $currentDue,
        ?string $managedDue,
        DateTimeImmutable $now
    ): ?array {
        $managed = $managedDue !== null;
        if ($managed && $currentDue === null) {
            // A client explicitly removed the due date but left our marker.
            // Never silently recreate a due date after this manual action.
            return null;
        }
        if ($currentDue !== null && (!$managed || $currentDue !== $managedDue)) {
            // Respect existing and manually modified dates.
            return null;
        }

        // Relative dates must not drift whenever a client later resaves
        // an already managed task. Without an original absolute anchor,
        // today's/morrow's reference date cannot be safely reconstructed.
        if ($managed && preg_match('/(?<![\\pL])(heute|morgen|übermorgen|uebermorgen)(?![\\pL])/iu', $summary) === 1) {
            return null;
        }

        $parsed = DateParser::parse($summary, $now);
        if ($parsed === null) {
            // Only remove a due date that TaskDate actually managed.
            return $managed && $currentDue !== null
                ? ['due' => null, 'summary' => $summary]
                : null;
        }

        [$date, $hasTime] = $parsed;
        if (!$hasTime && $managed && $managedDue !== null
            && preg_match('/^\d{8}T\d{6}$/', $managedDue) === 1) {
            $h = (int)substr($managedDue, 9, 2);
            $m = (int)substr($managedDue, 11, 2);
            $seconds = (int)substr($managedDue, 13, 2);
            if ($h > 23 || $m > 59 || $seconds > 59
                || !DateParser::isSafeLocalTime($date, $h, $m)) {
                // Never normalize a retained time into a different or
                // ambiguous wall-clock hour after changing the date.
                return null;
            }
            $date = $date->setTime($h, $m, $seconds);
            $hasTime = true;
        }

        $due = $hasTime ? $date->format('Ymd\THis') : $date->format('Ymd');
        $newSummary = $hasTime ? DateParser::withoutTime($summary, $date) : $summary;

        if ($managed && $currentDue === $due && $newSummary === $summary) {
            // Idempotent: avoids a second write for our own event.
            return null;
        }

        return ['due' => $due, 'summary' => $newSummary];
    }
}
