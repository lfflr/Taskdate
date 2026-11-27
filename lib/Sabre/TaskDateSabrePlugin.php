<?php
declare(strict_types=1);

namespace OCA\TaskDate\Sabre;

use DateTimeImmutable;
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

    public function getPluginName(): string {
        return 'taskdate';
    }

    public function initialize(Server $server): void {
        // After the standard auth/ACL plugins; before the DAV PUT handler.
        $server->on('beforeMethod:PUT', [$this, 'handlePut'], 60);
    }

    public function handlePut($request, $response): void {
        // This plugin must never change arbitrary WebDAV uploads.
        $path = $request->getPath();
        if (!is_string($path)
            || preg_match('~(?:^|/)calendars/[^/]+/[^/]+/[^/]+\.ics$~i', $path) !== 1) {
            return;
        }

        try {
            $raw = $request->getBodyAsString();
            if (!is_string($raw) || $raw === ''
                || strlen($raw) > self::MAX_ICAL_BYTES) {
                return;
            }
            $vobj = Reader::read($raw);
            if (!isset($vobj->VTODO) || isset($vobj->VEVENT)) {
                return;
            }
            $todo = $vobj->VTODO;
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
                new DateTimeImmutable('now')
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
        } catch (\Throwable $e) {
            // Fail closed: leave the original request for the DAV validator.
            // The plugin must not prevent a user's unmodified task from saving.
            return;
        }
    }
}
