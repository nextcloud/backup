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
use OCA\Backup\Service\ConfigService;
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


	/** @var PointService */
	private $pointService;

	/** @var PackService */
	private $packService;

	/** @var UploadService */
	private $uploadService;

	/** @var ConfigService */
	private $configService;


	/**
	 * Manage constructor.
	 *
	 * @param PointService $pointService
	 * @param PackService $packService
	 * @param UploadService $uploadService
	 * @param ConfigService $configService
	 */
	public function __construct(
		PointService $pointService,
		PackService $packService,
		UploadService $uploadService,
		ConfigService $configService
	) {
//		$this->setInterval(1);
		$this->setInterval(3600);

		$this->pointService = $pointService;
		$this->packService = $packService;
		$this->uploadService = $uploadService;
		$this->configService = $configService;
	}


	/**
	 * @param $argument
	 */
	protected function run($argument) {
		// packing
		foreach ($this->pointService->getLocalRestoringPoints() as $point) {
			try {
				$this->pointService->initBaseFolder($point);
				$this->packService->packPoint($point);
			} catch (Throwable $e) {
			}
		}

		// uploading
		foreach ($this->pointService->getLocalRestoringPoints() as $point) {
			try {
				$this->uploadService->uploadPoint($point);
			} catch (Throwable $e) {
			}
		}

		foreach ($this->pointService->getLocalRestoringPoints() as $point) {
			try {
//				$this->pointService->initBaseFolder($point);
				$this->pointService->generateHealth($point, true);
//				$this->packService->packPoint($point);
			} catch (Throwable $e) {
			}
		}
	}

}
