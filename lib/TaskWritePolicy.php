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
            && !isset($todo->RRULE)
            && !isset($todo->{'RECURRENCE-ID'})
            && !isset($todo->DTSTART);
    }
}
