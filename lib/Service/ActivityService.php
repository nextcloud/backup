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


namespace OCA\Backup\Service;

use OCA\Backup\AppInfo\Application;
use OCP\Activity\IEvent;
use OCP\Activity\IManager as IActivityManager;
use OCP\IGroupManager;
use OCP\IUser;

/**
 * Class ActivityService
 *
 * @package OCA\Backup\Service
 */
class ActivityService {
	public const TYPE_GLOBAL = 'backup_global';
	public const CREATE = 'backup_create';
	public const RESTORE = 'backup_restore';
	public const RESTORE_FILE = 'backup_restore_file';

	public const LIMIT_TO_GROUP = 'admin';


	/** @var IActivityManager */
	private $activityManager;

	/** @var IGroupManager */
	private $groupManager;


	/**
	 * ActivityService constructor.
	 *
	 * @param IActivityManager $activityManager
	 * @param IGroupManager $groupManager
	 */
	public function __construct(IActivityManager $activityManager, IGroupManager $groupManager) {
		$this->activityManager = $activityManager;
		$this->groupManager = $groupManager;
	}


	/**
	 * @param string $subject
	 * @param array $params
	 */
	public function newActivity(string $subject, array $params = []): void {
		$type = ActivityService::TYPE_GLOBAL;
		$activity = $this->generateActivity($type);
		$activity->setSubject($subject, $params);

		$adminGroup = $this->groupManager->get(self::LIMIT_TO_GROUP);
		$this->publishActivity($activity, $adminGroup->getUsers());
	}


	/**
	 * @param string $type
	 *
	 * @return IEvent
	 */
	private function generateActivity(string $type): IEvent {
		$event = $this->activityManager->generateEvent();
		$event->setApp(Application::APP_ID)
			  ->setType($type);

		return $event;
	}


	/**
	 * @param IEvent $event
	 * @param IUser[] $users
	 */
	private function publishActivity(IEvent $event, array $users) {
		foreach ($users as $user) {
			$event->setAffectedUser($user->getUID());
			$this->activityManager->publish($event);
		}
	}
}
