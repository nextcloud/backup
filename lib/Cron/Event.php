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

use ArtificialOwl\MySmallPhpTools\Traits\TArrayTools;
use OC\BackgroundJob\TimedJob;
use OCA\Backup\Db\EventRequest;
use OCA\Backup\Model\BackupEvent;
use OCA\Backup\Service\ConfigService;
use OCA\Backup\Service\CronService;
use OCA\Backup\Service\FilesService;
use OCA\Backup\Service\PointService;

/**
 * Class Event
 *
 * @package OCA\Backup\Cron
 */
class Event extends TimedJob {
	use TArrayTools;


	/** @var EventRequest */
	private $eventRequest;

	/** @var PointService */
	private $pointService;

	/** @var FilesService */
	private $filesService;

	/** @var CronService */
	private $cronService;

	/** @var ConfigService */
	private $configService;


	/**
	 * Event constructor.
	 *
	 * @param EventRequest $eventRequest
	 * @param PointService $pointService
	 * @param FilesService $filesService
	 * @param CronService $cronService
	 * @param ConfigService $configService
	 */
	public function __construct(
		EventRequest $eventRequest,
		PointService $pointService,
		FilesService $filesService,
		CronService $cronService,
		ConfigService $configService
	) {
		$this->setInterval(1);

		$this->eventRequest = $eventRequest;
		$this->pointService = $pointService;
		$this->filesService = $filesService;
		$this->cronService = $cronService;
		$this->configService = $configService;
	}


	/**
	 * @param $argument
	 */
	protected function run($argument) {
		if (!$this->cronService->isRunnable()) {
			return;
		}

		foreach ($this->eventRequest->getQueue() as $event) {
			if ($event->getType() === 'ScanLocalFolder') {
				$this->scanLocalFolder($event);
			}
		}
	}


	/**
	 * @param BackupEvent $event
	 */
	private function scanLocalFolder(BackupEvent $event): void {
		$data = $event->getData();
		$fileId = $this->getInt('fileId', $data);
		$owner = $event->getAuthor();

		$point = $this->pointService->generatePointFromFolder($fileId, $owner);

		$this->successEvent($event);
	}


	/**
	 * @param BackupEvent $event
	 * @param string $message
	 */
	private function failEvent(BackupEvent $event, string $message): void {
		$event->setResult(['status' => 0, 'message' => $message])
			  ->setStatus(BackupEvent::STATUS_DONE);

		$this->eventRequest->update($event);
	}


	/**
	 * @param BackupEvent $event
	 * @param string $message
	 */
	private function successEvent(BackupEvent $event, string $message = ''): void {
		$event->setResult(['status' => 1, 'message' => $message])
			  ->setStatus(BackupEvent::STATUS_DONE);

		$this->eventRequest->update($event);
	}
}
