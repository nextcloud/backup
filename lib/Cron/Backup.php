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
use OCA\Backup\Service\PointService;
use Throwable;


/**
 * Class Backup
 *
 * @package OCA\Backup\Cron
 */
class Backup extends TimedJob {


	use TNC23Logger;


	const MARGIN = 1800;


	/** @var PointService */
	private $pointService;

	/** @var ConfigService */
	private $configService;


	/**
	 * Backup constructor.
	 *
	 * @param PointService $pointService
	 * @param ConfigService $configService
	 */
	public function __construct(PointService $pointService, ConfigService $configService) {
		$this->setInterval(900);
//		$this->setInterval(1);

		$this->pointService = $pointService;
		$this->configService = $configService;
	}


	/**
	 * @param $argument
	 */
	protected function run($argument) {
		$time = time();
		if ($this->configService->getAppValueInt(ConfigService::MOCKUP_DATE) > 0) {
			$time = $this->configService->getAppValueInt(ConfigService::MOCKUP_DATE);
			$this->configService->setAppValueInt(ConfigService::MOCKUP_DATE, 0);
		}

		if (!$this->verifyTime($time)) {
			return;
		}

		$this->runBackup($time);
	}


	/**
	 *
	 */
	private function runBackup(int $time): void {
		if ($this->verifyFullBackup($time)) {
			$this->runFullBackup();
		} else if ($this->verifyIncrementalBackup($time)) {
			$this->runIncrementalBackup();
		}
	}


	/**
	 * @param int $time
	 *
	 * @return bool
	 */
	private function verifyTime(int $time): bool {
		[$st, $end] = explode('-', $this->configService->getAppValue(ConfigService::TIME_SLOTS));

		if (!is_numeric($st) || !is_numeric($end)) {
			$this->log(3, 'Issue with Time Slots format, please check configuration');

			return false;
		}

		$st = (int)$st;
		$end = (int)$end;

		$timeStart = mktime(
			$st,
			0,
			0,
			(int)date('n', $time),
			// we go back one day in time under some condition
			(int)date('j', $time) - ($st >= $end) * ((int)date('H', $time) < $end),
			(int)date('Y', $time)
		);

		$timeEnd = mktime(
			$end,
			0,
			0,
			(int)date('n', $time),
			// we go one day forward on a night-day configuration (ie. 23-5)
			(int)date('j', $time) + ($st >= $end) * ((int)date('H', $time) > $end),
			(int)date('Y', $time)
		);

		return ($timeStart < $time && $time < $timeEnd);
	}


	/**
	 * @param int $time
	 *
	 * @return bool
	 */
	private function verifyFullBackup(int $time): bool {
		if (!$this->configService->getAppValueBool(ConfigService::ALLOW_WEEKDAY)
			&& !$this->isWeekEnd($time)) {
			return false;
		}

		$last = $this->configService->getAppValueInt(ConfigService::DATE_FULL_RP);
		$delay = $this->configService->getAppValueInt(ConfigService::DELAY_FULL_RP);
		$delayUnit = $this->configService->getAppValue(ConfigService::DELAY_UNIT);
		$delay = $delay * 3600 * (($delayUnit !== 'h') ? 24 : 1);

		return ($last + $delay - self::MARGIN < $time);
	}


	private function runFullBackup(): void {
		try {
			$this->pointService->create(true);
		} catch (Throwable $e) {
		}
	}


	/**
	 * @param int $time
	 *
	 * @return bool
	 */
	private function verifyIncrementalBackup(int $time): bool {
		$last = max(
			$this->configService->getAppValueInt(ConfigService::DATE_PARTIAL_RP),
			$this->configService->getAppValueInt(ConfigService::DATE_FULL_RP)
		);
		$delay = $this->configService->getAppValueInt(ConfigService::DELAY_FULL_RP);
		$delayUnit = $this->configService->getAppValue(ConfigService::DELAY_UNIT);
		$delay = $delay * 3600 * (($delayUnit !== 'h') ? 24 : 1);

		return ($last + $delay - self::MARGIN < $time);
	}


	/**
	 *
	 */
	private function runIncrementalBackup(): void {
		try {
			$this->pointService->create(false);
		} catch (Throwable $e) {
		}
	}


	/**
	 * @param int $time
	 *
	 * @return bool
	 */
	private function isWeekEnd(int $time): bool {
		return ((int)date('N', $time) >= 6);
	}

}
