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

use ArtificialOwl\MySmallPhpTools\Traits\TArrayTools;
use OCA\Backup\Exceptions\JobsTimeSlotException;
use OCA\Backup\Exceptions\SettingsException;

/**
 * Class CronService
 *
 * @package OCA\Backup\Service
 */
class CronService {
	use TArrayTools;


	public const MARGIN = 1800;
	public const HOURS_FOR_NEXT = 4000;
	public const LOCK_TIMEOUT = 3600;


	/** @var RemoteStreamService */
	private $remoteStreamService;

	/** @var ExternalFolderService */
	private $externalFolderService;

	/** @var OutputService */
	private $outputService;

	/** @var ConfigService */
	private $configService;


	/** @var bool */
	private $ranFromCron = false;


	/**
	 * CronService constructor.
	 *
	 * @param RemoteStreamService $remoteStreamService
	 * @param ExternalFolderService $externalFolderService
	 * @param OutputService $outputService
	 * @param ConfigService $configService
	 */
	public function __construct(
		RemoteStreamService $remoteStreamService,
		ExternalFolderService $externalFolderService,
		OutputService $outputService,
		ConfigService $configService
	) {
		$this->remoteStreamService = $remoteStreamService;
		$this->externalFolderService = $externalFolderService;
		$this->outputService = $outputService;
		$this->configService = $configService;
	}


	/**
	 * @return int[]
	 */
	public function nextBackups(): array {
		$partialETA = $fullETA = -1;

		$delayPartial = $this->configService->getAppValueInt(ConfigService::DELAY_PARTIAL_RP);
		$delayUnit = $this->configService->getAppValue(ConfigService::DELAY_UNIT);
		$delayPartial = $delayPartial * 3600 * (($delayUnit !== 'h') ? 24 : 1);

		try {
			$this->getTime();
			$time = time() - 3600; // we start checking now.
			for ($h = 0; $h <= self::HOURS_FOR_NEXT; $h++) {
				$time += 3600;
				if (!$this->verifyTime($time)) {
					continue;
				}

				$last = max($fullETA, $this->configService->getAppValueInt(ConfigService::DATE_FULL_RP));

				// TODO: minor glitch: this will estimate the partial backup with one hour late.
				if ($fullETA === -1 && $this->verifyFullBackup($time)) {
					$fullETA = $time;
				} elseif ($partialETA === -1
						  && $this->verifyDifferentialBackup($time)
						  && ($last > 0) // we check that the differential backup can have a parent
						  && ($time - $last) > $delayPartial) { // we check the time since next full rp
					$partialETA = $time;
				}

				if ($fullETA > 0 && $partialETA > 0) {
					break;
				}
			}
		} catch (SettingsException $e) {
		}

		return [
			'partial' => $partialETA + 300,
			'full' => $fullETA + 300
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
	public function verifyTime(int $time = 0): bool {
		if ($time === 0) {
			$time = time();
		}

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
	public function verifyDifferentialBackup(int $time): bool {
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


	/**
	 * @param string $line
	 * @param bool $ln
	 */
	private function o(string $line, bool $ln = true): void {
		$this->outputService->o($line, $ln);
	}


	/**
	 * we assume that calling this method indicate the process was initiated from BackgroundJobs
	 *
	 * @return bool
	 */
	public function isRunnable(): bool {
		$mode = strtolower($this->configService->getCoreValue('backgroundjobs_mode', ''));
		if ($mode !== 'cron' && $mode !== 'webcron') {
			return false;
		}

		if (!$this->configService->getAppValueBool(ConfigService::CRON_ENABLED)) {
			return false;
		}

		$this->configService->setAppValueBool(ConfigService::CRON_ENABLED, true);
		$this->ranFromCron = true;

		return ($this->configService->getAppValueInt(ConfigService::CRON_LOCK) < time() - self::LOCK_TIMEOUT);
	}


	/**
	 * @param bool $verifyTime
	 *
	 * @throws JobsTimeSlotException
	 */
	public function lockCron(bool $verifyTime = true): void {
		if (!$this->ranFromCron) {
			return;
		}

		if ($verifyTime && !$this->verifyTime()) {
			throw new JobsTimeSlotException();
		}

		$this->configService->setAppValueInt(ConfigService::CRON_LOCK, time());
	}

	/**
	 *
	 */
	public function unlockCron(): void {
		$this->configService->setAppValueInt(ConfigService::CRON_LOCK, 0);
	}
}
