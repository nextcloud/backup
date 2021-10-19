<?php

declare(strict_types=1);


/**
 * Nextcloud - Backup now. Restore later.
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Maxence Lange <maxence@artificial-owl.com>
 * @copyright 2021, Maxence Lange <maxence@artificial-owl.com>
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */


namespace OCA\Backup\AppInfo;

use OCA\Backup\Handlers\WebfingerHandler;
use OCA\Backup\Listeners\FilesAdditionalScripts;
use OCA\Backup\Listeners\NodeEvent;
use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Files\Events\Node\NodeCreatedEvent;
use OCP\Files\Events\Node\NodeRenamedEvent;
use OCP\Files\Events\Node\NodeWrittenEvent;

require_once __DIR__ . '/../../vendor/autoload.php';


/**
 * Class Application
 *
 * @package OCA\Backup\AppInfo
 */
class Application extends App implements IBootstrap {
	public const APP_ID = 'backup';
	public const APP_NAME = 'Backup';
	public const APP_SUBJECT = 'http://nextcloud.com/';
	public const APP_REL = 'https://apps.nextcloud.com/apps/backup';


	/**
	 * Application constructor.
	 *
	 * @param array $params
	 */
	public function __construct(array $params = []) {
		parent::__construct(self::APP_ID, $params);
	}


	/**
	 * @param IRegistrationContext $context
	 */
	public function register(IRegistrationContext $context): void {
		$context->registerEventListener(NodeCreatedEvent::class, NodeEvent::class);
		$context->registerEventListener(NodeWrittenEvent::class, NodeEvent::class);
		$context->registerEventListener(NodeRenamedEvent::class, NodeEvent::class);

		$context->registerWellKnownHandler(WebfingerHandler::class);

		$context->registerEventListener(LoadAdditionalScriptsEvent::class, FilesAdditionalScripts::class);
	}


	/**
	 * @param IBootContext $context
	 */
	public function boot(IBootContext $context): void {
	}
}
