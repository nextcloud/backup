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

use ArtificialOwl\MySmallPhpTools\Traits\Nextcloud\nc23\TNC23Logger;
use OC\BackgroundJob\TimedJob;
use OCA\Backup\Service\ConfigService;
use OCA\Backup\Service\CronService;
use OCA\Backup\Service\PointService;
use Throwable;

/**
 * Class Backup
 *
 * @package OCA\Backup\Cron
 */
class Backup extends TimedJob {
	use TNC23Logger;


	/** @var PointService */
	private $pointService;

	/** @var CronService */
	private $cronService;

	/** @var ConfigService */
	private $configService;


	/**
	 * Backup constructor.
	 *
	 * @param PointService $pointService
	 * @param CronService $cronService
	 * @param ConfigService $configService
	 */
	public function __construct(
		PointService $pointService,
		CronService $cronService,
		ConfigService $configService
	) {
		$this->setInterval(900);

		$this->pointService = $pointService;
		$this->cronService = $cronService;
		$this->configService = $configService;
	}


	/**
	 * @param $argument
	 */
	protected function run($argument):void {
		if (!$this->cronService->isRealCron()) {
			return;
		}

		$time = time();
		if ($this->configService->getAppValueInt(ConfigService::MOCKUP_DATE) > 0) {
			$time = $this->configService->getAppValueInt(ConfigService::MOCKUP_DATE);
			$this->configService->setAppValueInt(ConfigService::MOCKUP_DATE, 0);
		}

		if (!$this->cronService->verifyTime($time)) {
			return;
		}

		$this->runBackup($time);
	}


	/**
	 *
	 */
	private function runBackup(int $time): void {
		if ($this->cronService->verifyFullBackup($time)) {
			$this->runFullBackup();
		} elseif ($this->cronService->verifyDifferentialBackup($time)) {
			$this->runDifferentialBackup();
		}
	}


	private function runFullBackup(): void {
		try {
			$this->pointService->create(true);
		} catch (Throwable $e) {
		}
	}


	/**
	 *
	 */
	private function runDifferentialBackup(): void {
		try {
			$this->pointService->create(false);
		} catch (Throwable $e) {
		}
	}
}
