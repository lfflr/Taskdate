<?php
declare(strict_types=1);

namespace OCA\TaskDate\Listener;

use OCA\DAV\CalDAV\CalDavBackend;
use OCP\Calendar\Events\CalendarObjectCreatedEvent;
use OCP\Calendar\Events\CalendarObjectUpdatedEvent;
use OCA\TaskDate\CalendarPayload;
use OCA\TaskDate\TaskWritePolicy;
use OCA\TaskDate\TaskUpdatePlanner;
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
            if (!CalendarPayload::mayContainTodo($object)) {
                return;
            }
            $uri = CalendarPayload::uri($object);
            if ($uri === null) {
                return;
            }

            // Event metadata does not guarantee raw calendardata.
            // Use it when supplied, otherwise fetch the complete object.
            $rawData = CalendarPayload::calendarData($object);
            if ($rawData === null) {
                $stored = $this->backend->getCalendarObject($calendarId, $uri);
                $rawData = is_array($stored) ? CalendarPayload::calendarData($stored) : null;
            }
            if ($rawData === null) {
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

            $title = (string)$todo->SUMMARY;
            $plan = TaskUpdatePlanner::plan(
                $title,
                $currentDue,
                $oldAutoDue,
                new \DateTimeImmutable('now')
            );
            if ($plan === null) {
                return;
            }
            if ($plan['due'] === null) {
                unset($todo->DUE);
                unset($todo->{self::AUTO_DUE_PROPERTY});
                $this->backend->updateCalendarObject($calendarId, $uri, $vobj->serialize());
                return;
            }
            $due = $plan['due'];
            $cleanTitle = $plan['summary'];

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
