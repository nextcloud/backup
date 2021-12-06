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

use ArtificialOwl\MySmallPhpTools\Exceptions\SignatoryException;
use ArtificialOwl\MySmallPhpTools\Exceptions\SignatureException;
use ArtificialOwl\MySmallPhpTools\Traits\TArrayTools;
use OCA\Backup\Exceptions\ExternalFolderNotFoundException;
use OCA\Backup\Exceptions\RemoteInstanceException;
use OCA\Backup\Exceptions\RemoteInstanceNotFoundException;
use OCA\Backup\Exceptions\RemoteResourceNotFoundException;
use OCA\Backup\Exceptions\SettingsException;
use OCA\Backup\Model\ExternalFolder;
use OCA\Backup\Model\RemoteInstance;
use Throwable;

/**
 * Class CronService
 *
 * @package OCA\Backup\Service
 */
class CronService {
	use TArrayTools;


	public const MARGIN = 1800;
	public const HOURS_FOR_NEXT = 4000;


	/** @var PointService */
	private $pointService;

	/** @var RemoteService */
	private $remoteService;

	/** @var RemoteStreamService */
	private $remoteStreamService;

	/** @var ExternalFolderService */
	private $externalFolderService;

	/** @var OutputService */
	private $outputService;

	/** @var ConfigService */
	private $configService;


	/**
	 * CronService constructor.
	 *
	 * @param PointService $pointService
	 * @param RemoteService $remoteService
	 * @param RemoteStreamService $remoteStreamService
	 * @param ExternalFolderService $externalFolderService
	 * @param OutputService $outputService
	 * @param ConfigService $configService
	 */
	public function __construct(
		PointService $pointService,
		RemoteService $remoteService,
		RemoteStreamService $remoteStreamService,
		ExternalFolderService $externalFolderService,
		OutputService $outputService,
		ConfigService $configService
	) {
		$this->pointService = $pointService;
		$this->remoteService = $remoteService;
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
	 * @param bool $local
	 * @param string $remote
	 * @param string $external
	 *
	 * @return array
	 */
	public function getRPFromInstances(
		bool $local = false,
		string $remote = '',
		string $external = ''
	): array {
		if ($local) {
			$instances = [RemoteInstance::LOCAL];
		} elseif ($remote !== '') {
			$instances = ['remote:' . $remote];
		} elseif ($external !== '') {
			$instances = ['external:' . $external];
		} else {
			$instances = array_merge(
				[RemoteInstance::LOCAL],
				array_map(
					function (RemoteInstance $remoteInstance): string {
						return 'remote:' . $remoteInstance->getInstance();
					}, $this->remoteService->getOutgoing()
				),
				array_map(
					function (ExternalFolder $externalFolder): string {
						return 'external:' . $externalFolder->getStorageId();
					}, $this->externalFolderService->getAll()
				)
			);
		}

		$points = $dates = [];
		foreach ($instances as $instance) {
			$this->o('- retreiving data from <info>' . $instance . '</info>');

			$list = [];
			try {
				if ($instance === RemoteInstance::LOCAL) {
					$list = $this->pointService->getLocalRestoringPoints();
				} else {
					[$source, $id] = explode(':', $instance, 2);
					if ($source === 'remote') {
						$list = $this->remoteService->getRestoringPoints($id);
					} elseif ($source === 'external') {
						try {
							$external = $this->externalFolderService->getByStorageId((int)$id);
							$list = $this->externalFolderService->getRestoringPoints($external);
						} catch (ExternalFolderNotFoundException $e) {
						}
					}
				}
			} catch (RemoteInstanceException
			| RemoteInstanceNotFoundException
			| RemoteResourceNotFoundException $e) {
				continue;
			}

			foreach ($list as $item) {
				$this->o(' > found RestoringPoint <info>' . $item->getId() . '</info>');
				if (!array_key_exists($item->getId(), $points)) {
					$points[$item->getId()] = [];
				}

				$issue = '';
				if ($instance !== RemoteInstance::LOCAL) {
					$storedDate = $this->getInt($item->getId(), $dates);
					if ($storedDate > 0 && $storedDate !== $item->getDate()) {
						$this->o('  <error>! different date</error>');
						$issue = 'different date';
					}

					try {
						$this->remoteStreamService->verifyPoint($item);
					} catch (SignatoryException | SignatureException $e) {
						$this->o('  <error>! cannot confirm integrity</error>');
						$issue = 'cannot confirm integrity';
					}
				}

				$points[$item->getId()][$instance] = [
					'point' => $item,
					'issue' => $issue
				];

				$dates[$item->getId()] = $item->getDate();
			}
		}

		return $this->orderByDate($points, $dates);
	}


	/**
	 *
	 */
	public function purgeRestoringPoints(): void {
		$c = $this->configService->getAppValue(ConfigService::STORE_ITEMS);
		$i = 0;
		foreach ($this->pointService->getLocalRestoringPoints(0, 0, false) as $point) {
			if ($point->isArchive()) {
				continue;
			}
			$i++;
			if ($i > $c) {
				try {
					$this->pointService->delete($point);
				} catch (Throwable $e) {
				}
			}
		}
	}

	/**
	 *
	 */
	public function purgeRemoteRestoringPoints(): void {
	}


	/**
	 * @param array $points
	 * @param array $dates
	 *
	 * @return array
	 */
	private function orderByDate(array $points, array $dates): array {
		asort($dates);

		$result = [];
		foreach ($dates as $pointId => $date) {
			$result[$pointId] = $points[$pointId];
		}

		return $result;
	}


	/**
	 * @param string $line
	 * @param bool $ln
	 */
	private function o(string $line, bool $ln = true): void {
		$this->outputService->o($line, $ln);
	}


	/**
	 * @return bool
	 */
	public function isRealCron(): bool {
		$mode = $this->configService->getCoreValue('backgroundjobs_mode', '');

		if (!$this->configService->getAppValueBool(ConfigService::CRON_ENABLED)) {
			return false;
		}

		$this->configService->setAppValueBool(ConfigService::CRON_ENABLED, true);

		return (strtolower($mode) === 'cron' || strtolower($mode) === 'webcron');
	}
}
