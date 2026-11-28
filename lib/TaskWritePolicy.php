<?php
declare(strict_types=1);

namespace OCA\TaskDate;

/**
 * Safety gate for features not yet validated against a live CalDAV server.
 *
 * Recurrence and DTSTART require special attention when rewriting VTODO DUE.
 * Fail closed to avoid changing an existing task into an invalid event.
 */
final class TaskWritePolicy {
    public static function allowsAutomaticDue(object $todo): bool {
        return isset($todo->SUMMARY)
            // Do not rewrite completed or explicitly cancelled tasks.
            && !isset($todo->COMPLETED)
            && (!isset($todo->STATUS) || strtoupper(trim((string)$todo->STATUS)) === 'NEEDS-ACTION')
            // DTSTART/DURATION and all recurrence date properties need their
            // own temporal-consistency handling before automatic DUE updates.
            && !isset($todo->DTSTART)
            && !isset($todo->DURATION)
            && !isset($todo->RRULE)
            && !isset($todo->RDATE)
            && !isset($todo->EXDATE)
            && !isset($todo->{'RECURRENCE-ID'});
    }
}
