<?php
declare(strict_types=1);


/**
 * Nextcloud - Backup
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Maxence Lange <maxence@artificial-owl.com>
 * @copyright 2019, Maxence Lange <maxence@artificial-owl.com>
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


use daita\MySmallPhpTools\Traits\TArrayTools;
use daita\MySmallPhpTools\Traits\TPathTools;
use daita\MySmallPhpTools\Traits\TStringTools;
use OCA\Backup\Model\Backup;
use OCA\Backup\Model\BackupArchive;
use OCA\Backup\Model\BackupChunk;


/**
 * Class FilesService
 *
 * @package OCA\Backup\Service
 */
class FilesService {


	use TArrayTools;
	use TStringTools;


	const APP_ROOT = __DIR__ . '/../../';


	/** @var ConfigService */
	private $configService;

	/** @var MiscService */
	private $miscService;


	/**
	 * FilesService constructor.
	 *
	 * @param ConfigService $configService
	 * @param MiscService $miscService
	 */
	public function __construct(ConfigService $configService, MiscService $miscService) {
		$this->configService = $configService;
		$this->miscService = $miscService;
	}


	/**
	 * @param BackupChunk $backupChunk
	 * @param string $path
	 */
	public function fillBackupChunk(BackupChunk $backupChunk, string $path = ''): void {

		// TODO: check no trailing slashes
		if (!is_dir($backupChunk->getAbsolutePath() . $path)) {
			$backupChunk->addFile($path);

			return;
		}

		if ($path !== '') {
			$path .= '/';
		}

		if (file_exists($backupChunk->getAbsolutePath() . $path . BackupService::NOBACKUP_FILE)) {
			return;
		}

		foreach (scandir($backupChunk->getAbsolutePath() . $path) as $entry) {
			if ($entry === '.' || $entry === '..') {
				continue;
			}

			$this->fillBackupChunk($backupChunk, $path . $entry);
		}
	}


	use TPathTools;

	/**
	 * @param BackupChunk $backupChunk
	 */
	public function initBackupChunk(BackupChunk $backupChunk): void {
		$root = '';
		switch ($backupChunk->getType()) {
			case BackupChunk::ROOT_DISK:
				$root = '/';
				break;

			case BackupChunk::ROOT_NEXTCLOUD:
				$root = \OC::$SERVERROOT;
				break;

			case BackupChunk::ROOT_DATA:
				$root = $this->configService->getSystemValue('datadirectory');
				break;

			case BackupChunk::FILE_CONFIG:
				$root = \OC::$SERVERROOT;
				$backupChunk->setPath('config/');
				$backupChunk->setUniqueFile('config.php');
				break;
		}

		if ($root !== '') {
			$backupChunk->setRoot($this->withEndSlash($root));
		}
	}

	/**
	 * @param string $path
	 *
	 * @return string[]
	 */
	public function getFilesFromApp($path = ''): array {
		$files = [];
		foreach (scandir(self::APP_ROOT . $path) as $entry) {
			if ($entry === '.' || $entry === '..') {
				continue;
			}

			if (is_dir(self::APP_ROOT . $path . $entry)) {
				$files = array_merge($files, $this->getFilesFromApp($path . $entry . '/'));
			}

			if (is_file(self::APP_ROOT . $path . $entry)) {
				$files[] = $path . $entry;
			}
		}

		return $files;
	}

}

