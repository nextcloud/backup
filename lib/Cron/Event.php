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


namespace OCA\Backup\Cron;


use OC\BackgroundJob\TimedJob;
use OCA\Backup\Db\EventRequest;
use OCA\Backup\Model\BackupEvent;
use OCA\Backup\Service\ConfigService;


/**
 * Class Event
 *
 * @package OCA\Backup\Cron
 */
class Event extends TimedJob {


	/** @var EventRequest */
	private $eventRequest;

	/** @var ConfigService */
	private $configService;


	/**
	 * Event constructor.
	 *
	 * @param ConfigService $configService
	 */
	public function __construct(EventRequest $eventRequest, ConfigService $configService) {
		$this->setInterval(1);

		$this->eventRequest = $eventRequest;
		$this->configService = $configService;
	}


	/**
	 * @param $argument
	 */
	protected function run($argument) {
		foreach($this->eventRequest->getQueue() as $event) {
			if ($event->getType() === 'ScanLocalFolder') {
				$this->scanLocalFolder($event);
			}
		}
	}


	/**
	 * @param BackupEvent $event
	 */
	private function scanLocalFolder(BackupEvent $event): void {
		$event->setResult(['ok']);
		$event->setStatus(BackupEvent::STATUS_DONE);

		$this->eventRequest->update($event);
	}
}
