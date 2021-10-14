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


use OCA\Backup\Exceptions\SettingsException;

/**
 * Class CronService
 *
 * @package OCA\Backup\Service
 */
class CronService {


	const MARGIN = 1800;
	const HOURS_FOR_NEXT = 4000;


	/** @var ConfigService */
	private $configService;


	/**
	 * CronService constructor.
	 *
	 * @param ConfigService $configService
	 */
	public function __construct(ConfigService $configService) {
		$this->configService = $configService;
	}


	/**
	 * @return int[]
	 */
	public function nextBackups(): array {
		$partialETA = $fullETA = -1;

		try {
			$this->getTime();
			$time = time() - 3600; // we start checking now.
			for ($h = 0; $h <= self::HOURS_FOR_NEXT; $h++) {
				$time += 3600;
				if (!$this->verifyTime($time)) {
					continue;
				}
				if ($fullETA === -1 && $this->verifyFullBackup($time)) {
					$fullETA = $time;
				} else if ($partialETA === -1
						   && $this->verifyIncrementalBackup($time)
						   && ($this->configService->getAppValueInt(ConfigService::DATE_FULL_RP) > 0
							   || $fullETA
								  > 0)) { // we also check that the incremental backup can have a parent
					$partialETA = $time;
				}

				if ($fullETA > 0 && $partialETA > 0) {
					break;
				}
			}
		} catch (SettingsException $e) {
		}

		return [
			'partial' => $partialETA,
			'full' => $fullETA
		];

	}


	/**
	 * @return array
	 * @throws SettingsException
	 */
	public function getTime(): array {
		[$st, $end] = explode('-', $this->configService->getAppValue(ConfigService::TIME_SLOTS));

		if (!is_numeric($st) || !is_numeric($end)) {
			throw new SettingsException();
		}

		return [$st, $end];
	}


	/**
	 * @param int $time
	 *
	 * @return bool
	 */
	public function verifyTime(int $time): bool {
		try {
			[$st, $end] = $this->getTime();
		} catch (SettingsException $e) {
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
	public function verifyFullBackup(int $time): bool {
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


	/**
	 * @param int $time
	 *
	 * @return bool
	 */
	public function verifyIncrementalBackup(int $time): bool {
		$last = max(
			$this->configService->getAppValueInt(ConfigService::DATE_PARTIAL_RP),
			$this->configService->getAppValueInt(ConfigService::DATE_FULL_RP)
		);
		$delay = $this->configService->getAppValueInt(ConfigService::DELAY_PARTIAL_RP);
		$delayUnit = $this->configService->getAppValue(ConfigService::DELAY_UNIT);
		$delay = $delay * 3600 * (($delayUnit !== 'h') ? 24 : 1);

		return ($last + $delay - self::MARGIN < $time);
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

