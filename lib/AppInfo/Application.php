<?php
declare(strict_types=1);

namespace OCA\TaskDate\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

/**
 * DAV plugin registration lives in appinfo/info.xml (sabre/plugins).
 * Nextcloud loads it for the normal authenticated CalDAV server.
 */
class Application extends App implements IBootstrap {
    public const APP_ID = 'taskdate';

    public function __construct() {
        parent::__construct(self::APP_ID);
    }

    public function register(IRegistrationContext $context): void {
    }

    public function boot(IBootContext $context): void {
    }
}
