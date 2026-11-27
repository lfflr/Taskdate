<?php
declare(strict_types=1);

namespace OCA\TaskDate\AppInfo;

use OCA\TaskDate\Listener\RegisterSabrePluginListener;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\SabrePluginEvent;

class Application extends App implements IBootstrap {
    public const APP_ID = 'taskdate';

    public function __construct() {
        parent::__construct(self::APP_ID);
    }

    public function register(IRegistrationContext $context): void {
        $context->registerEventListener(SabrePluginEvent::class, RegisterSabrePluginListener::class);
    }

    public function boot(IBootContext $context): void {
    }
}
