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


use ArtificialOwl\MySmallPhpTools\Traits\Nextcloud\nc23\TNC23Deserialize;
use ArtificialOwl\MySmallPhpTools\Traits\Nextcloud\nc23\TNC23Logger;
use OC\Files\FileInfo;
use OC\Files\Node\Folder;
use OCA\Backup\Db\ExternalFolderRequest;
use OCA\Backup\Exceptions\ExternalFolderNotFoundException;
use OCA\Backup\Model\ExternalFolder;
use OCP\Files\Config\IUserMountCache;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;


/**
 * Class FilesExternalService
 *
 * @package OCA\Backup\Service
 */
class FilesExternalService {


	use TNC23Deserialize;
	use TNC23Logger;


	/** @var ExternalFolderRequest */
	private $externalFolderRequest;

	/** @var ConfigService */
	private $configService;


	/**
	 * FilesExternalService constructor.
	 *
	 * @param ExternalFolderRequest $externalFolderRequest
	 * @param ConfigService $configService
	 */
	public function __construct(
		ExternalFolderRequest $externalFolderRequest,
		ConfigService $configService
	) {
		$this->externalFolderRequest = $externalFolderRequest;
		$this->configService = $configService;

		$this->setup('app', 'backup');
	}


	private function uploadToExternalFS(ExternalFolder $external): void {
		try {
			$node = $this->prepareNode($external);
		} catch (ExternalFolderNotFoundException $e) {
			$this->log(3, 'Cannot retrieve a valid mount point for storage ' . json_encode($external));

			return;
		}

		$node->newFile('test002.txt');
	}

	/**
	 * @param ExternalFolder $external
	 *
	 * @return Folder
	 * @throws ExternalFolderNotFoundException
	 */
	private function prepareNode(ExternalFolder $external): Folder {
		/** @var IUserMountCache $mountCache */
		$mountCache = \OC::$server->get(IUserMountCache::class);
		$mounts = $mountCache->getMountsForStorageId($external->getStorageId());

		foreach ($mounts as $mount) {
			/** @var Folder $node */
			$node = $mount->getMountPointNode();
			if ($node->getType() !== FileInfo::TYPE_FOLDER) {
				$this->log(3, 'Mount point Node is not a folder');
				continue;
			}

			foreach (explode('/', $external->getRoot()) as $dir) {
				try {
					$sub = $node->get($dir);
					if ($sub->getType() !== FileInfo::TYPE_FOLDER) {
						$this->log(3, 'File ' . $dir . ' is not a folder on External Filesystem');
						continue;
					}
				} catch (NotFoundException $e) {
					try {
						$sub = $node->newFolder($dir);
					} catch (NotPermittedException $e) {
						$this->log(3, 'Cannot create folder ' . $dir . ' on External Filesystem');
						continue;
					}
				}
				$node = $sub;
			}

			return $node;
		}

		throw new ExternalFolderNotFoundException();
	}

}
