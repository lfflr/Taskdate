<?php
declare(strict_types=1);

namespace OCA\TaskDate;

/**
 * Read the raw iCalendar object from a calendar object event.
 * Empty and non-string payloads are intentionally rejected.
 */
final class CalendarPayload {
    public static function calendarData(array $object): ?string {
        $data = $object['calendardata'] ?? null;
        return is_string($data) && $data !== '' ? $data : null;
    }

    public static function uri(array $object): ?string {
        $uri = $object['uri'] ?? null;
        return is_string($uri) && $uri !== '' ? $uri : null;
    }
}
