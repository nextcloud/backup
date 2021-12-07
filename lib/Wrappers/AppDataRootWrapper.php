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


namespace OCA\Backup\Wrappers;

use OC\Config;
use OC\Files\Node\File;
use OC\Files\Node\Folder;
use OC\Files\Node\Node;
use OC\Files\SimpleFS\SimpleFolder;
use OCA\Backup\AppInfo\Application;
use OCA\Backup\Model\ExternalFolder;
use OCP\Files\FileInfo;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\Files\SimpleFS\ISimpleRoot;
use OCP\IConfig;

/**
 * Class AppDataRootWrapper
 *
 * @package OCA\Backup\Service
 */
class AppDataRootWrapper {


	/** @var ISimpleRoot */
	private $simpleRoot;

	/** @var ExternalFolder */
	private $externalFolder;


	/**
	 *
	 */
	public function __construct() {
	}


	/**
	 * @param ISimpleRoot $simpleRoot
	 *
	 * @return AppDataRootWrapper
	 */
	public function setSimpleRoot(ISimpleRoot $simpleRoot): self {
		$this->simpleRoot = $simpleRoot;

		return $this;
	}

	/**
	 * @return ISimpleRoot
	 */
	public function getSimpleRoot(): ISimpleRoot {
		return $this->simpleRoot;
	}


	/**
	 * @param ExternalFolder $externalFolder
	 *
	 * @return AppDataRootWrapper
	 */
	public function setExternalFolder(ExternalFolder $externalFolder): self {
		$this->externalFolder = $externalFolder;

		return $this;
	}

	/**
	 * @return ExternalFolder
	 */
	public function getExternalFolder(): ExternalFolder {
		return $this->externalFolder;
	}


	/**
	 * @return bool
	 */
	public function isSimpleRoot(): bool {
		return !is_null($this->simpleRoot);
	}


	/**
	 * @return array
	 */
	public function getFolders(): array {
		if ($this->isSimpleRoot()) {
			try {
				return array_map(
					function (ISimpleFolder $folder): string {
						return $folder->getName();
					}, $this->getSimpleRoot()->getDirectoryListing()
				);
			} catch (NotFoundException $e) {
				return [];
			}
		}

		$external = $this->getExternalFolder();
		$folder = $external->getRootFolder();

		try {
			return array_values(
				array_filter(
					array_map(function (Node $node): string {
						if ($node->getType() !== FileInfo::TYPE_FOLDER) {
							return '';
						}

						return $node->getName();
					}, $folder->getDirectoryListing())
				)
			);
		} catch (NotFoundException $e) {
			return [];
		}
	}


	/**
	 * @return Folder
	 * @throws NotFoundException
	 */
	public function getRoot(): Folder {
		if ($this->isSimpleRoot()) {
			/** @var IRootFolder $rootFolder */
			$rootFolder = \OC::$server->get(IRootFolder::class);
			$root = $rootFolder->get($this->getAppDataPath());
		} else {
			$root = $this->getExternalFolder()->getRootFolder();
		}

		if (!($root instanceof Folder)) {
			throw new NotFoundException();
		}

		return $root;
	}

	/**
	 * @param string $path
	 *
	 * @return Node
	 * @throws NotFoundException
	 */
	public function getNode(string $path): Node {
		return $this->getRoot()->get($path);
	}


	/**
	 * @param string $path
	 *
	 * @return File
	 * @throws NotPermittedException
	 */
	public function newFile(string $path): File {
		return $this->getRoot()->newFile($path);
	}


	/**
	 * @param string $path
	 *
	 * @return ISimpleFolder
	 * @throws NotFoundException
	 */
	public function getFolder(string $path): ISimpleFolder {
		if ($this->isSimpleRoot()) {
			return $this->getSimpleRoot()->getFolder($path);
		}

		$external = $this->getExternalFolder();
		$folder = $external->getRootFolder();
		foreach (explode('/', $path) as $dir) {
			if ($dir === '') {
				continue;
			}

			$sub = $folder->get($dir);
			if ($folder->getType() !== FileInfo::TYPE_FOLDER) {
				throw new NotFoundException();
			}

			$folder = $sub;
		}

		return new SimpleFolder($folder);
	}


	/**
	 * @param string $path
	 *
	 * @return ISimpleFolder
	 * @throws NotPermittedException
	 */
	public function newFolder(string $path): ISimpleFolder {
		if ($this->isSimpleRoot()) {
			return $this->getSimpleRoot()->newFolder($path);
		}

		$external = $this->getExternalFolder();
		$folder = $external->getRootFolder();
		foreach (explode('/', $path) as $dir) {
			if ($dir === '') {
				continue;
			}

			try {
				$sub = $folder->get($dir);
			} catch (NotFoundException $e) {
				$sub = $folder->newFolder($dir);
			}

			if ($folder->getType() !== FileInfo::TYPE_FOLDER) {
				throw new NotPermittedException('path is filled with files');
			}

			$folder = $sub;
		}

		return new SimpleFolder($folder);
	}


	/**
	 * based on OC\Files\AppData\AppData::getAppDataFolderName() which is private.
	 *
	 * @return string
	 */
	private function getAppDataPath(): string {
		/** @var Config $config */
		$config = \OC::$server->get(IConfig::class);
		$instanceId = $config->getSystemValue('instanceid', null);
		if ($instanceId === null) {
			throw new \RuntimeException('no instance id!');
		}

		return 'appdata_' . $instanceId . '/' . Application::APP_ID;
	}
}
