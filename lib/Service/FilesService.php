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

use ArtificialOwl\MySmallPhpTools\Exceptions\InvalidItemException;
use ArtificialOwl\MySmallPhpTools\Traits\Nextcloud\nc23\TNC23Deserialize;
use ArtificialOwl\MySmallPhpTools\Traits\Nextcloud\nc23\TNC23Logger;
use ArtificialOwl\MySmallPhpTools\Traits\TArrayTools;
use ArtificialOwl\MySmallPhpTools\Traits\TPathTools;
use ArtificialOwl\MySmallPhpTools\Traits\TStringTools;
use OC;
use OC\Files\Node\File;
use OC\Files\Node\Folder;
use OC\User\NoUserException;
use OCA\Backup\AppInfo\Application;
use OCA\Backup\Db\ChangesRequest;
use OCA\Backup\Exceptions\RestoringPointNotFoundException;
use OCA\Backup\Model\ChangedFile;
use OCA\Backup\Model\RestoringData;
use OCA\Backup\Model\RestoringPoint;
use OCP\Files\FileInfo;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\Lock\LockedException;

/**
 * Class FilesService
 *
 * @package OCA\Backup\Service
 */
class FilesService {
	use TArrayTools;
	use TStringTools;
	use TPathTools;
	use TNC23Deserialize;
	use TNC23Logger;

	public const APP_ROOT = __DIR__ . '/../../';


	/** @var IRootFolder */
	private $rootFolder;

	/** @var ChangesRequest */
	private $changesRequest;

	/** @var ConfigService */
	private $configService;


	/**
	 * FilesService constructor.
	 *
	 * @param IRootFolder $rootFolder
	 * @param ChangesRequest $changesRequest
	 * @param ConfigService $configService
	 */
	public function __construct(
		IRootFolder $rootFolder,
		ChangesRequest $changesRequest,
		ConfigService $configService
	) {
		$this->rootFolder = $rootFolder;
		$this->changesRequest = $changesRequest;
		$this->configService = $configService;

		$this->setup('app', Application::APP_ID);
	}


	/**
	 * @param RestoringData $data
	 * @param string $path
	 */
	public function fillRestoringData(RestoringData $data, string $path): void {
		if (!is_dir($data->getAbsolutePath() . rtrim($path, '/'))) {
			$log = $this->configService->getSystemValue(ConfigService::LOGFILE) ?: 'nextcloud.log';
			if ($data->getType() === RestoringData::ROOT_DATA
				&& !$this->configService->getAppValueBool(ConfigService::INCLUDE_LOGS)
				&& substr($path, 0, strlen($log)) === $log) {
				return;
			}

			$data->addFile($path);

			return;
		}

		if ($path !== '') {
			$path = rtrim($path, '/') . '/';
		}

		if (file_exists($data->getAbsolutePath() . $path . PointService::NOBACKUP_FILE)) {
			return;
		}

		if ($data->getType() === RestoringData::ROOT_NEXTCLOUD) {
			foreach (RestoringData::$FILTER_FROM_NC as $item) {
				if ($path === $item) {
					return;
				}
			}
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
			case RestoringData::ROOT_DISK:
				$root = '/';
				break;

			case RestoringData::ROOT_NEXTCLOUD:
				$root = OC::$SERVERROOT;
				break;

			case RestoringData::ROOT_DATA:
				$root = $this->configService->getSystemValue(ConfigService::DATA_DIRECTORY);
				break;

			case RestoringData::ROOT_APPS:
				$root = OC::$SERVERROOT;
				$data->setPath('apps/');
				break;

			case RestoringData::FILE_CONFIG:
				$root = OC::$SERVERROOT;
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

			if ($entry === 'node_modules') {
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


	/**
	 * @param int $fileId
	 * @param string $owner
	 * @param Folder|null $folder
	 *
	 * @return RestoringPoint
	 * @throws NotPermittedException
	 * @throws NoUserException
	 * @throws RestoringPointNotFoundException
	 */
	public function getPointFromFileId(int $fileId, string $owner, ?Folder &$folder = null): RestoringPoint {
		$storage = $this->rootFolder->getUserFolder($owner);
		$nodes = $storage->getById($fileId);

		foreach ($nodes as $node) {
			if ($node->getType() !== FileInfo::TYPE_FILE) {
				continue;
			}

			/** @var File $node */
			$folder = $node->getParent();

			try {
				/** @var RestoringPoint $point */
				$point = $this->deserializeJson($node->getContent(), RestoringPoint::class);

				return $point;
			} catch (InvalidItemException
			| NotPermittedException
			| LockedException $e) {
				continue;
			}
		}

		throw new RestoringPointNotFoundException();
	}


	/**
	 * @param Folder $node
	 * @param string $path
	 *
	 * @return Folder
	 */
	public function getPackFolder(Folder $node, string $path): Folder {
		foreach (explode('/', $path, 2) as $dir) {
			if ($dir === '') {
				continue;
			}

			try {
				$sub = $node->get($dir);
				if ($sub->getType() !== FileInfo::TYPE_FOLDER) {
					continue;
				}
			} catch (NotFoundException $e) {
				try {
					$sub = $node->newFolder($dir);
				} catch (NotPermittedException $e) {
					continue;
				}
			}
			$node = $sub;
		}

		return $node;
	}


	/**
	 * @param Folder $input
	 * @param ISimpleFolder $output
	 * @param string $filename
	 *
	 * @throws LockedException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function copyFileToAppData(
		Folder $input,
		ISimpleFolder $output,
		string $filename
	): void {
		/** @var File $orig */
		$orig = $input->get($filename);
		if ($orig->getType() !== FileInfo::TYPE_FILE) {
			return;
		}

		/** @var File $orig */
		try {
			$dest = $output->getFile($filename);
		} catch (NotFoundException $e) {
			$dest = $output->newFile($filename);
		}

		$dest->putContent($orig->getContent());
	}
}
