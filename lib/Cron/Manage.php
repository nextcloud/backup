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
use OCA\Backup\Service\ConfigService;
use OCA\Backup\Service\CronService;
use OCA\Backup\Service\ExternalFolderService;
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
	 * @param ConfigService $configService
	 */
	public function __construct(
		CronService $cronService,
		PointService $pointService,
		PackService $packService,
		UploadService $uploadService,
		ExternalFolderService $externalFolderService,
		ConfigService $configService
	) {
		$this->setInterval(1);
//		$this->setInterval(3600 * 3); // 3 hours ?

		$this->cronService = $cronService;
		$this->pointService = $pointService;
		$this->packService = $packService;
		$this->uploadService = $uploadService;
		$this->externalFolderService = $externalFolderService;
		$this->configService = $configService;
	}


	/**
	 * @param $argument
	 */
	protected function run($argument) {
		// uploading
		foreach ($this->pointService->getLocalRestoringPoints() as $point) {
			try {
				$this->uploadService->uploadPoint($point);
			} catch (Throwable $e) {
			}
		}

		// packing
		foreach ($this->pointService->getLocalRestoringPoints() as $point) {
			try {
				$this->pointService->initBaseFolder($point);
				$this->packService->packPoint($point);
			} catch (Throwable $e) {
			}
		}

		// next step are only executed during the night shift
		if (!$this->cronService->verifyTime()) {
			return;
		}


		// regenerate local health
		foreach ($this->pointService->getLocalRestoringPoints() as $point) {
			try {
				$this->pointService->generateHealth($point, true);
			} catch (Throwable $e) {
			}
		}

		// regenerate health on ExternalFolder
		foreach ($this->externalFolderService->getAll() as $external) {
			try {
				foreach ($this->externalFolderService->getRestoringPoints($external) as $point) {
					try {
						$this->externalFolderService->getCurrentHealth($external, $point);
					} catch (Throwable $e) {
					}
				}
			} catch (ExternalFolderNotFoundException $e) {
			}
		}
	}

}
