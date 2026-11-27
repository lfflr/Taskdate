<?php
declare(strict_types=1);

namespace OCA\TaskDate\Listener;

use OCA\DAV\CalDAV\CalDavBackend;
use OCP\Calendar\Events\CalendarObjectCreatedEvent;
use OCP\Calendar\Events\CalendarObjectUpdatedEvent;
use OCA\TaskDate\DateParser;
use OCA\TaskDate\TaskWritePolicy;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use Psr\Log\LoggerInterface;
use Sabre\VObject\Reader;

/** @template-implements IEventListener<CalendarObjectCreatedEvent|CalendarObjectUpdatedEvent> */
class TaskDateListener implements IEventListener {
    private const AUTO_DUE_PROPERTY = 'X-TASKDATE-AUTO-DUE';

    public function __construct(
        private CalDavBackend $backend,
        private LoggerInterface $logger,
    ) {
    }

    public function handle(Event $event): void {
        if (!($event instanceof CalendarObjectCreatedEvent) && !($event instanceof CalendarObjectUpdatedEvent)) {
            return;
        }

        try {
            $calendarId = $event->getCalendarId();
            $object = $event->getObjectData();
            $uri = $object['uri'] ?? null;
            if (!is_string($uri) || $uri === '') {
                return;
            }

            // Nextcloud 35's calendar object event generally includes calendardata.
            // Prefer that snapshot; fall back to the backend for older payloads.
            $rawData = $object['calendardata'] ?? null;
            if (!is_string($rawData) || $rawData === '') {
                $stored = $this->backend->getCalendarObject($calendarId, $uri);
                $rawData = is_array($stored) ? ($stored['calendardata'] ?? null) : null;
            }
            if (!is_string($rawData) || $rawData === '') {
                return;
            }
            $vobj = Reader::read($rawData);
            $todo = $vobj->VTODO ?? null;
            if ($todo === null || !TaskWritePolicy::allowsAutomaticDue($todo)) {
                return;
            }

            $managed = isset($todo->{self::AUTO_DUE_PROPERTY});
            $oldAutoDue = $managed ? (string)$todo->{self::AUTO_DUE_PROPERTY} : null;
            $currentDue = isset($todo->DUE) ? (string)$todo->DUE : null;
            // Never replace a due date that was set or changed manually.
            if ($currentDue !== null && (!$managed || $currentDue !== $oldAutoDue)) {
                return;
            }

            $parsed = DateParser::parse((string)$todo->SUMMARY, new \DateTimeImmutable('now'));
            if ($parsed === null) {
                // Remove only dates previously created by TaskDate.
                if ($managed && $currentDue !== null) {
                    unset($todo->DUE);
                    unset($todo->{self::AUTO_DUE_PROPERTY});
                    $this->backend->updateCalendarObject($calendarId, $uri, $vobj->serialize());
                }
                return;
            }
            [$date, $hasTime] = $parsed;

            // The listener runs a second time after it removes the time from SUMMARY.
            // Retain the previously extracted time while TaskDate still manages DUE.
            if (!$hasTime && $managed && $oldAutoDue !== null && strlen($oldAutoDue) === 15) {
                $date = $date->setTime((int)substr($oldAutoDue, 9, 2), (int)substr($oldAutoDue, 11, 2));
                $hasTime = true;
            }

            $due = $hasTime ? $date->format('Ymd\THis') : $date->format('Ymd');
            $title = (string)$todo->SUMMARY;
            $cleanTitle = $hasTime ? DateParser::withoutTime($title, $date) : $title;
            if ($currentDue === $due && $managed && $cleanTitle === $title) {
                return; // also prevents the update event from writing again
            }

            if (isset($todo->DUE)) {
                unset($todo->DUE);
            }

            if ($hasTime) {
                $todo->add('DUE', $due);
            } else {
                $todo->add('DUE', $due, ['VALUE' => 'DATE']);
            }
            if (isset($todo->{self::AUTO_DUE_PROPERTY})) {
                unset($todo->{self::AUTO_DUE_PROPERTY});
            }
            $todo->add(self::AUTO_DUE_PROPERTY, $due);
            if ($cleanTitle !== $title) {
                $todo->SUMMARY = $cleanTitle;
            }

            $this->backend->updateCalendarObject(
                $calendarId,
                $uri,
                $vobj->serialize()
            );
        } catch (\Throwable $e) {
            $this->logger->warning('taskdate: ' . $e->getMessage(), ['exception' => $e]);
        }
    }
}
