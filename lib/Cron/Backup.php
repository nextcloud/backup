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


use ArtificialOwl\MySmallPhpTools\Model\SimpleDataStore;
use OC\BackgroundJob\TimedJob;
use OCA\Backup\Service\ConfigService;


/**
 * Class Backup
 *
 * @package OCA\Backup\Cron
 */
class Backup extends TimedJob {


	/** @var ConfigService */
	private $configService;


	/**
	 * Backup constructor.
	 *
	 * @param ConfigService $configService
	 */
	public function __construct(ConfigService $configService) {
		$this->setInterval(1800);

		$this->configService = $configService;
	}


	/**
	 * @param $argument
	 */
	protected function run($argument) {
		$this->runBackup();
	}


	/**
	 *
	 */
	private function runBackup(): void {
//		$last = new SimpleDataStore();
//		$last->json($this->configService->getAppValue(ConfigService::MAINTENANCE_UPDATE));
//
//		$last->sInt('maximum', $this->maximumLevelBasedOnTime(($last->gInt('5') === 0)));
//		for ($i = 5; $i > 0; $i--) {
//			if ($this->canRunLevel($i, $last)) {
//				try {
//					$this->maintenanceService->runMaintenance($i);
//				} catch (MaintenanceException $e) {
//					continue;
//				}
//				$last->sInt((string)$i, time());
//			}
//		}
//
//		$this->configService->setAppValue(ConfigService::MAINTENANCE_UPDATE, json_encode($last));
	}


	/**
	 * @param bool $force
	 *
	 * @return int
	 */
	private function maximumLevelBasedOnTime(bool $force = false): int {
		$currentHour = (int)date('H');
		$currentDay = (int)date('N');
		$isWeekEnd = ($currentDay >= 6);

		if ($currentHour > 2 && $currentHour < 5 && ($isWeekEnd || $force)) {
			return 5;
		}

		if ($currentHour > 1 && $currentHour < 6) {
			return 4;
		}

		return 3;
	}


	private function canRunLevel(int $level, SimpleDataStore $last): bool {
		if ($last->gInt('maximum') < $level) {
			return false;
		}

		$now = time();
		$timeLastRun = $last->gInt((string)$level);
		if ($timeLastRun === 0) {
			return true;
		}

		return ($timeLastRun + self::$DELAY[$level] < $now);
	}
}
