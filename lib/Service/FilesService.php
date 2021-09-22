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


use ArtificialOwl\MySmallPhpTools\Traits\TArrayTools;
use ArtificialOwl\MySmallPhpTools\Traits\TPathTools;
use ArtificialOwl\MySmallPhpTools\Traits\TStringTools;
use OCA\Backup\Db\ChangesRequest;
use OCA\Backup\Model\BackupChunk;
use OCA\Backup\Model\ChangedFile;
use OCA\Backup\Model\RestoringData;


/**
 * Class FilesService
 *
 * @package OCA\Backup\Service
 */
class FilesService {


	use TArrayTools;
	use TStringTools;
	use TPathTools;


	const APP_ROOT = __DIR__ . '/../../';


	/** @var ChangesRequest */
	private $changesRequest;

	/** @var ConfigService */
	private $configService;

	/** @var MiscService */
	private $miscService;


	/**
	 * FilesService constructor.
	 *
	 * @param ChangesRequest $changesRequest
	 * @param ConfigService $configService
	 * @param MiscService $miscService
	 */
	public function __construct(
		ChangesRequest $changesRequest,
		ConfigService $configService,
		MiscService $miscService
	) {
		$this->changesRequest = $changesRequest;
		$this->configService = $configService;
		$this->miscService = $miscService;
	}


	/**
	 * @param RestoringData $data
	 * @param string $path
	 */
	public function fillRestoringData(RestoringData $data, string $path): void {
		if (!is_dir($data->getAbsolutePath() . rtrim($path, '/'))) {
			$data->addFile($path);

			return;
		}

		if ($path !== '') {
			$path .= '/';
		}

		if (file_exists($data->getAbsolutePath() . $path . BackupService::NOBACKUP_FILE)) {
			return;
		}

		foreach (scandir($data->getAbsolutePath() . $path) as $entry) {
			if ($entry === '.' || $entry === '..') {
				continue;
			}

			$this->fillRestoringData($data, $path . $entry);
		}
	}


	/**
	 * @param RestoringData $data
	 */
	public function initRestoringData(RestoringData $data): void {
		$root = '';
		switch ($data->getType()) {
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
				$data->setPath('config/');
				$data->setUniqueFile('config.php');
				break;
		}

		if ($root !== '') {
			$data->setRoot($this->withEndSlash($root));
		}
	}

	/**
	 * @param string $path
	 *
	 * @return string[]
	 */
	public function getFilesFromApp(string $path = ''): array {
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


	/**
	 * @param ChangedFile $file
	 */
	public function changedFile(ChangedFile $file): void {
		$this->changesRequest->insertIfNotFound($file);
	}

}
