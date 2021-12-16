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
use OCA\Backup\Exceptions\ExternalFolderNotFoundException;
use OCA\Backup\Exceptions\JobsTimeSlotException;
use OCA\Backup\Model\RestoringPoint;
use OCA\Backup\Service\ConfigService;
use OCA\Backup\Service\CronService;
use OCA\Backup\Service\ExternalFolderService;
use OCA\Backup\Service\OutputService;
use OCA\Backup\Service\PackService;
use OCA\Backup\Service\PointService;
use OCA\Backup\Service\UploadService;
use Throwable;

/**
 * Class Manage
 *
 * @package OCA\Backup\Cron
 */
class Manage extends TimedJob {
	public const DELAY_CHECK_HEALTH = 86400 * 7; // 7d

	/** @var CronService */
	private $cronService;

	/** @var PointService */
	private $pointService;

	/** @var PackService */
	private $packService;

	/** @var UploadService */
	private $uploadService;

	/** @var ExternalFolderService */
	private $externalFolderService;

	/** @var OutputService */
	private $outputService;

	/** @var ConfigService */
	private $configService;


	/**
	 * Manage constructor.
	 *
	 * @param CronService $cronService
	 * @param PointService $pointService
	 * @param PackService $packService
	 * @param UploadService $uploadService
	 * @param ExternalFolderService $externalFolderService
	 * @param OutputService $outputService
	 * @param ConfigService $configService
	 */
	public function __construct(
		CronService $cronService,
		PointService $pointService,
		PackService $packService,
		UploadService $uploadService,
		ExternalFolderService $externalFolderService,
		OutputService $outputService,
		ConfigService $configService
	) {
		$this->setInterval(3600);

		$this->cronService = $cronService;
		$this->pointService = $pointService;
		$this->packService = $packService;
		$this->uploadService = $uploadService;
		$this->externalFolderService = $externalFolderService;
		$this->outputService = $outputService;
		$this->configService = $configService;
	}


	/**
	 * @param $argument
	 */
	protected function run($argument) {
		if (!$this->cronService->isRunnable()) {
			return;
		}

		try {
			$this->cronService->lockCron(false);
			$this->manage();
			$this->cronService->unlockCron();
		} catch (JobsTimeSlotException $e) {
		}
	}


	/**
	 * @throws JobsTimeSlotException
	 */
	private function manage() {
		$generateLogs = $this->configService->getAppValueBool(ConfigService::GENERATE_LOGS);

		// TODO: purge old restoring points.
		$this->pointService->purgeRestoringPoints();
		$this->pointService->purgeRemoteRestoringPoints();

		// next steps are only available during night shift
		$this->cronService->lockCron();

		// uploading
		foreach ($this->pointService->getLocalRestoringPoints() as $point) {
			if ($point->isArchive()) {
				continue;
			}
			try {
				$this->pointService->initBaseFolder($point);
				if ($generateLogs) {
					$this->outputService->openFile($point, 'Manage Background Job (uploading)');
				}
				$this->uploadService->uploadPoint($point);
			} catch (JobsTimeSlotException $e) {
				break;
			} catch (Throwable $e) {
			}
		}

		// packing
		$this->cronService->lockCron();
		foreach ($this->pointService->getLocalRestoringPoints() as $point) {
			if ($point->isArchive()) {
				continue;
			}

			if ($point->isStatus(RestoringPoint::STATUS_PACKED)
				&& !$point->isStatus(RestoringPoint::STATUS_PACKING)) {
				continue;
			}

			try {
				$this->pointService->initBaseFolder($point);
				if ($generateLogs) {
					$this->outputService->openFile($point, 'Manage Background Job (packing)');
				}
				$this->packService->packPoint($point);
			} catch (JobsTimeSlotException $e) {
				break;
			} catch (Throwable $e) {
			}
		}

		// regenerate local health
		$this->cronService->lockCron();
		foreach ($this->pointService->getLocalRestoringPoints() as $point) {
			if ($point->hasHealth()
				&& $point->getHealth()->getChecked() > time() - self::DELAY_CHECK_HEALTH) {
				continue;
			}
			try {
				$this->cronService->lockCron();
				$this->pointService->initBaseFolder($point);
				if ($generateLogs) {
					$this->outputService->openFile($point, 'Manage Background Job (health)');
				}

				$this->pointService->generateHealth($point, true);
			} catch (JobsTimeSlotException $e) {
				break;
			} catch (Throwable $e) {
			}
		}

		// regenerate health on ExternalFolder
		$this->cronService->lockCron();
		foreach ($this->externalFolderService->getAll() as $external) {
			try {
				foreach ($this->externalFolderService->getRestoringPoints($external) as $point) {
					if ($point->hasHealth()
						&& $point->getHealth()->getChecked() > time() - 3600 * 12) {
						continue;
					}
					try {
						$this->cronService->lockCron();
						$this->pointService->initBaseFolder($point);
						if ($generateLogs) {
							$this->outputService->openFile(
								$point, 'Manage Background Job (health (external))'
							);
						}
						$this->externalFolderService->getCurrentHealth($external, $point);
					} catch (JobsTimeSlotException $e) {
						return;
					} catch (Throwable $e) {
					}
				}
			} catch (ExternalFolderNotFoundException $e) {
			}
		}
	}
}
