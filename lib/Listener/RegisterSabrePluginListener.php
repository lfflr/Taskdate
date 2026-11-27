<?php
declare(strict_types=1);

namespace OCA\TaskDate\Listener;

use OCA\TaskDate\Sabre\TaskDateSabrePlugin;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\SabrePluginEvent;

/** @template-implements IEventListener<SabrePluginEvent> */
final class RegisterSabrePluginListener implements IEventListener {
    public function handle(Event $event): void {
        if (!$event instanceof SabrePluginEvent) {
            return;
        }

        $server = $event->getServer();
        if ($server !== null) {
            $server->addPlugin(new TaskDateSabrePlugin());
        }
    }
}
