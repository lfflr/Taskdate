<?php
declare(strict_types=1);

namespace OCA\TaskDate\Sabre;

use DateTimeImmutable;
use DateTimeZone;
use Closure;
use OCP\IDateTimeZone;
use OCA\TaskDate\TaskUpdatePlanner;
use OCA\TaskDate\TaskWritePolicy;
use Sabre\DAV\Server;
use Sabre\DAV\ServerPlugin;
use Sabre\VObject\Reader;

/**
 * Transforms a client's CalDAV PUT *before* Nextcloud validates and stores it.
 *
 * No internal OCA\DAV classes, no backend write, and no self-generated
 * update event. Nextcloud's own CalDAV pipeline performs permission checks,
 * validation, persistence and notifications.
 */
final class TaskDateSabrePlugin extends ServerPlugin {
    private const AUTO_DUE_PROPERTY = 'X-TASKDATE-AUTO-DUE';
    private const MAX_ICAL_BYTES = 1048576;

    /**
     * An explicit resolver is only needed in isolated SabreDAV tests.
     * In Nextcloud, use the public user-aware IDateTimeZone service.
     */
    public function __construct(private ?Closure $timezoneResolver = null) {
    }

    private function resolveUserTimeZone(): DateTimeZone {
        if ($this->timezoneResolver !== null) {
            $timezone = ($this->timezoneResolver)();
        } else {
            $timezone = \OCP\Server::get(IDateTimeZone::class)->getTimeZone();
        }

        if (!$timezone instanceof DateTimeZone) {
            throw new \RuntimeException('Could not resolve authenticated user timezone');
        }
        return $timezone;
    }

    public function getPluginName(): string {
        return 'taskdate';
    }

    public function initialize(Server $server): void {
        // Nextcloud loads app plugins during beforeMethod:* (priority 30).
        // Its event listener list is snapshotted before emission, so a newly
        // registered beforeMethod:PUT handler would miss this very request.
        // method:PUT is emitted afterwards: priority 60 runs before Sabre's
        // CorePlugin handler at the default priority 100.
        $server->on('method:PUT', [$this, 'handlePut'], 60);
    }

    public function handlePut($request, $response): void {
        // Never change other WebDAV resources.
        $path = $request->getPath();
        if (!is_string($path)
            || preg_match('~(?:^|/)calendars/[^/]+/[^/]+/[^/]+\.ics$~i', $path) !== 1) {
            return;
        }

        // Do not buffer known oversized requests.
        $length = $request->getHeader('Content-Length');
        if (is_string($length) && ctype_digit($length)
            && (float)$length > self::MAX_ICAL_BYTES) {
            return;
        }

        $raw = null;
        $rewritten = false;
        try {
            // SabreHTTP may consume a request-body stream here. Always put it
            // back on a skipped / invalid request, not just on successful PUTs.
            $raw = $request->getBodyAsString();
            if (!is_string($raw) || $raw === ''
                || strlen($raw) > self::MAX_ICAL_BYTES) {
                return;
            }
            $vobj = Reader::read($raw);
            // Be conservative with mixed/multi-component documents and
            // explicit timezone definitions until client timezone semantics
            // have been verified. Never partially rewrite a calendar object.
            if (count($vobj->select('VTODO')) !== 1
                || count($vobj->select('VEVENT')) !== 0
                || count($vobj->select('VJOURNAL')) !== 0
                || count($vobj->select('VTIMEZONE')) !== 0) {
                return;
            }
            $todo = $vobj->VTODO;
            // TaskDate creates floating times. Do not silently discard a
            // foreign client's explicit DUE timezone on subsequent edits.
            if (isset($todo->DUE)
                && (isset($todo->DUE['TZID'])
                    || str_ends_with((string)$todo->DUE, 'Z'))) {
                return;
            }
            if (!TaskWritePolicy::allowsAutomaticDue($todo)) {
                return;
            }

            $currentDue = isset($todo->DUE) ? (string)$todo->DUE : null;
            $managedDue = isset($todo->{self::AUTO_DUE_PROPERTY})
                ? (string)$todo->{self::AUTO_DUE_PROPERTY}
                : null;
            $plan = TaskUpdatePlanner::plan(
                (string)$todo->SUMMARY,
                $currentDue,
                $managedDue,
                new DateTimeImmutable('now', $this->resolveUserTimeZone())
            );
            if ($plan === null) {
                return;
            }

            if (isset($todo->DUE)) {
                unset($todo->DUE);
            }
            if (isset($todo->{self::AUTO_DUE_PROPERTY})) {
                unset($todo->{self::AUTO_DUE_PROPERTY});
            }
            if ($plan['due'] !== null) {
                if (strlen($plan['due']) === 8) {
                    $todo->add('DUE', $plan['due'], ['VALUE' => 'DATE']);
                } else {
                    $todo->add('DUE', $plan['due']);
                }
                $todo->add(self::AUTO_DUE_PROPERTY, $plan['due']);
            }
            $todo->SUMMARY = $plan['summary'];

            $serialized = $vobj->serialize();
            $request->setBody($serialized);
            $request->setHeader('Content-Length', (string)strlen($serialized));
            $rewritten = true;
        } catch (\Throwable $e) {
            // Leave original data to Nextcloud's standard CalDAV validator.
        } finally {
            if (!$rewritten && is_string($raw)) {
                $request->setBody($raw);
            }
        }
    }

}
