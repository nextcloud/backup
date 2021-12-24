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
use ArtificialOwl\MySmallPhpTools\Traits\TFileTools;
use Exception;
use OC;
use OC\Files\Cache\Storage;
use OC\Files\FileInfo;
use OC\Files\Node\File;
use OC\Files\Node\Folder;
use OCA\Backup\Db\ExternalFolderRequest;
use OCA\Backup\Exceptions\ArchiveNotFoundException;
use OCA\Backup\Exceptions\ExternalFolderNotFoundException;
use OCA\Backup\Exceptions\MetadataException;
use OCA\Backup\Exceptions\RestoringChunkPartNotFoundException;
use OCA\Backup\Exceptions\RestoringPointException;
use OCA\Backup\Exceptions\RestoringPointNotFoundException;
use OCA\Backup\Exceptions\RestoringPointPackException;
use OCA\Backup\Exceptions\RestoringPointUploadException;
use OCA\Backup\Model\ChunkPartHealth;
use OCA\Backup\Model\ExternalFolder;
use OCA\Backup\Model\RestoringChunk;
use OCA\Backup\Model\RestoringChunkPart;
use OCA\Backup\Model\RestoringHealth;
use OCA\Backup\Model\RestoringPoint;
use OCA\Files_External\Lib\InsufficientDataForMeaningfulAnswerException;
use OCA\Files_External\Lib\StorageConfig;
use OCA\Files_External\MountConfig;
use OCA\Files_External\Service\GlobalStoragesService;
use OCP\AppFramework\QueryException;
use OCP\Files\Config\IUserMountCache;
use OCP\Files\GenericFileException;
use OCP\Files\InvalidPathException;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Files\Storage\IStorage;
use OCP\Files\StorageNotAvailableException;
use OCP\Lock\LockedException;

/**
 * Class ExternalFolderService
 *
 * @package OCA\Backup\Service
 */
class ExternalFolderService {
	use TNC23Deserialize;
	use TNC23Logger;
	use TFileTools;


	/** @var GlobalStoragesService */
	private $globalStoragesService;

	/** @var ExternalFolderRequest */
	private $externalFolderRequest;

	/** @var OutputService */
	private $outputService;

	/** @var ConfigService */
	private $configService;


	/**
	 * ExternalFolderService constructor.
	 *
	 * @param ExternalFolderRequest $externalFolderRequest
	 * @param OutputService $outputService
	 * @param ConfigService $configService
	 */
	public function __construct(
		ExternalFolderRequest $externalFolderRequest,
		OutputService $outputService,
		ConfigService $configService
	) {
		try {
			$this->globalStoragesService = OC::$server->get(GlobalStoragesService::class);
		} catch (Exception $e) {
		}

		$this->externalFolderRequest = $externalFolderRequest;
		$this->outputService = $outputService;
		$this->configService = $configService;

		$this->setup('app', 'backup');
	}


	/**
	 * @return ExternalFolder[]
	 */
	public function getAll(): array {
		return $this->externalFolderRequest->getAll();
	}

	/**
	 * @param int $mountId
	 *
	 * @return ExternalFolder
	 * @throws ExternalFolderNotFoundException
	 */
	public function getById(int $mountId): ExternalFolder {
		return $this->externalFolderRequest->getById($mountId);
	}

	/**
	 * @param int $storageId
	 *
	 * @return ExternalFolder
	 * @throws ExternalFolderNotFoundException
	 */
	public function getByStorageId(int $storageId): ExternalFolder {
		return $this->externalFolderRequest->getByStorageId($storageId);
	}


	/**
	 * @param ExternalFolder $external
	 *
	 * @return RestoringPoint[]
	 * @throws ExternalFolderNotFoundException
	 */
	public function getRestoringPoints(ExternalFolder $external): array {
		$this->initRootFolder($external);
		$folder = $external->getRootFolder();

		$points = [];
		try {
			$nodes = $folder->getDirectoryListing();
		} catch (NotFoundException $e) {
			throw new ExternalFolderNotFoundException();
		}

		foreach ($nodes as $node) {
			if ($node->getType() !== FileInfo::TYPE_FOLDER) {
				continue;
			}

			try {
				$points[] = $this->getRestoringPoint($external, $node->getName());
			} catch (
			ExternalFolderNotFoundException |
			RestoringChunkPartNotFoundException |
			RestoringPointException |
			RestoringPointNotFoundException |
			RestoringPointPackException |
			GenericFileException |
			NotPermittedException $e) {
			}
		}

		return $points;
	}


	/**
	 * @param ExternalFolder $external
	 * @param string $pointId
	 * @param bool $current
	 *
	 * @return RestoringPoint
	 * @throws ExternalFolderNotFoundException
	 * @throws RestoringPointException
	 * @throws RestoringPointNotFoundException
	 * @throws RestoringChunkPartNotFoundException
	 * @throws RestoringPointPackException
	 * @throws GenericFileException
	 * @throws NotPermittedException
	 */
	public function getRestoringPoint(
		ExternalFolder $external,
		string $pointId,
		bool $current = false
	): RestoringPoint {
		$folder = $this->getExternalPointFolder($external, $pointId, false);

		try {
			/** @var File $metadata */
			$metadata = $folder->get(MetadataService::METADATA_FILE);
			if ($metadata->getType() !== FileInfo::TYPE_FILE) {
				throw new RestoringChunkPartNotFoundException('metadata is not a file but a folder');
			}

			/** @var RestoringPoint $point */
			$point = $this->deserializeJson($metadata->getContent(), RestoringPoint::class);

			if ($current) {
				$this->generateHealth($external, $point);
				$metadata->putContent(json_encode($point, JSON_PRETTY_PRINT));
			}

			return $point;
		} catch (
		InvalidItemException |
		NotFoundException |
		NotPermittedException |
		LockedException $e) {
		}

		throw new RestoringPointNotFoundException();
	}


	/**
	 * @param ExternalFolder $external
	 * @param RestoringPoint $point
	 * @param RestoringHealth $health
	 * @param RestoringChunk $chunk
	 * @param RestoringChunkPart $part
	 *
	 * @return void
	 * @throws ExternalFolderNotFoundException
	 * @throws GenericFileException
	 * @throws LockedException
	 * @throws NotPermittedException
	 * @throws RestoringChunkPartNotFoundException
	 * @throws RestoringPointException
	 * @throws RestoringPointNotFoundException
	 */
	public function uploadPart(
		ExternalFolder $external,
		RestoringPoint $point,
		RestoringHealth $health,
		RestoringChunk $chunk,
		RestoringChunkPart $part
	): void {
		$folder = $this->getExternalChunkFolder($external, $point, $chunk, true);
		$file = $folder->newFile($part->getName());
		$file->putContent(base64_decode($part->getContent()));
		$this->updateChunkPartHealth($external, $point, $health, $chunk, $part);
	}


	/**
	 * @param ExternalFolder $external
	 * @param RestoringPoint $point
	 * @param RestoringChunk $chunk
	 * @param RestoringChunkPart $part
	 *
	 * @return void
	 * @throws ExternalFolderNotFoundException
	 * @throws LockedException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws RestoringChunkPartNotFoundException
	 * @throws RestoringPointException
	 * @throws RestoringPointNotFoundException
	 */
	public function downloadPart(
		ExternalFolder $external,
		RestoringPoint $point,
		RestoringChunk $chunk,
		RestoringChunkPart $part
	): void {
		$folder = $this->getExternalChunkFolder($external, $point, $chunk, true);

		/** @var File $file */
		$file = $folder->get($part->getName());
		if ($file->getType() !== FileInfo::TYPE_FILE) {
			throw new RestoringChunkPartNotFoundException('remote part is not a file');
		}

		$part->setContent(base64_encode($file->getContent()));
	}


	/**
	 * @param ExternalFolder $external
	 *
	 * @throws ExternalFolderNotFoundException
	 */
	public function initRootFolder(ExternalFolder $external): void {
		if ($external->hasRootFolder()) {
			return;
		}

		/** @var IUserMountCache $mountCache */
		$mountCache = OC::$server->get(IUserMountCache::class);
		$mounts = $mountCache->getMountsForStorageId($external->getStorageId());

		foreach ($mounts as $mount) {
			/** @var Folder $node */
			$node = $mount->getMountPointNode();
			if ($node->getType() !== FileInfo::TYPE_FOLDER) {
				$this->log(3, 'Mount point Node is not a folder');
				continue;
			}

			foreach (explode('/', $external->getRoot()) as $dir) {
				if ($dir === '') {
					continue;
				}

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

			if ($node->getType() !== FileInfo::TYPE_FOLDER) {
				$this->log(3, 'path is not a folder');
				continue;
			}

			$external->setRootFolder($node);

			return;
		}

		throw new ExternalFolderNotFoundException();
	}


	/**
	 * @param ExternalFolder $folder
	 * @param RestoringPoint $point
	 *
	 * @return RestoringPoint
	 * @throws ExternalFolderNotFoundException
	 * @throws RestoringPointException
	 * @throws RestoringPointNotFoundException
	 */
	public function confirmPoint(
		ExternalFolder $folder,
		RestoringPoint $point
	): RestoringPoint {
		try {
			$stored = $this->getRestoringPoint($folder, $point->getId(), true);
			$this->o('  > restoring point found');
//		} catch (RemoteInstanceException $e) {
//			$this->o('  ! <error>check configuration on remote instance</error>');
//			throw $e;
//		} catch (
//		RemoteInstanceNotFoundException
//		| RemoteResourceNotFoundException $e) {
//			$this->o('  ! <error>cannot communicate with remote instance</error>');
//			throw $e;
		} catch (RestoringPointNotFoundException $e) {
			$this->o('  > <comment>restoring point not found</comment>');
			try {
				$this->createPoint($folder, $point);
				$this->o('  > restoring point created');
				$this->o('  * initiating health check');
				$stored = $this->getRestoringPoint($folder, $point->getId(), true);
			} catch (Exception $e) {
				$this->o('  ! <error>cannot create restoring point</error>');
				throw $e;
			}
		}

		return $stored;
	}


	/**
	 * @param string $pointId
	 */
	public function deletePoint(string $pointId): void {
		foreach ($this->getAll() as $external) {
			try {
				$this->deletePointExternal($external, $pointId);
			} catch (Exception $e) {
			}
		}
	}


	/**
	 * @param ExternalFolder $external
	 * @param string $pointId
	 *
	 * @throws ExternalFolderNotFoundException
	 * @throws GenericFileException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws RestoringChunkPartNotFoundException
	 * @throws RestoringPointException
	 * @throws RestoringPointNotFoundException
	 * @throws RestoringPointPackException
	 * @throws InvalidPathException
	 */
	public function deletePointExternal(ExternalFolder $external, string $pointId): void {
		$this->getRestoringPoint($external, $pointId);

		$this->initRootFolder($external);
		$folder = $this->getExternalPointFolder($external, $pointId);
		$folder->delete();
	}


	/**
	 * @param ExternalFolder $external
	 * @param RestoringPoint $point
	 *
	 * @return RestoringPoint
	 * @throws GenericFileException
	 * @throws LockedException
	 * @throws NotPermittedException
	 * @throws RestoringPointException
	 * @throws RestoringPointNotFoundException
	 * @throws ExternalFolderNotFoundException
	 * @throws MetadataException
	 */
	public function createPoint(ExternalFolder $external, RestoringPoint $point): RestoringPoint {
		$this->o('  * Creating Restoring Point on external folder: ', false);

		try {
			$metadataFile = $this->updateMetadataFile($external, $point);

			/** @var RestoringPoint $stored */
			try {
				$stored = $this->deserializeJson($metadataFile->getContent(), RestoringPoint::class);
			} catch (InvalidItemException $e) {
				throw new RestoringPointNotFoundException('restoring point not created');
			}
		} catch (NotPermittedException
		| RestoringPointNotFoundException $e) {
			$this->o('<error>' . $e->getMessage() . '</error>');
			throw $e;
		}

		$this->o('<info>ok</info>');

		return $stored;
	}


	/**
	 * @param RestoringPoint $point
	 * @param ExternalFolder|null $external
	 */
	public function updateMetadata(RestoringPoint $point, ?ExternalFolder $external = null): void {
		if (is_null($external)) {
			$externals = $this->getAll();
		} else {
			$externals = [$external];
		}

		foreach ($externals as $external) {
			try {
				$this->o(
					'updating metadata on <info>' . $external->getStorageId()
					. '</info>:<info>' . $external->getRoot() . '</info>: ',
					false
				);
				$this->updateMetadataFile($external, $point, false);
				$this->o('<info>ok</info>');
			} catch (Exception $e) {
				$this->o('<error>failed</error> ' . $e->getMessage());
			}
		}
	}


	/**
	 * @param ExternalFolder $external
	 * @param RestoringPoint $point
	 * @param bool $create
	 *
	 * @return File
	 * @throws ExternalFolderNotFoundException
	 * @throws GenericFileException
	 * @throws LockedException
	 * @throws MetadataException
	 * @throws NotPermittedException
	 * @throws RestoringPointException
	 * @throws RestoringPointNotFoundException
	 * @throws NotFoundException
	 */
	public function updateMetadataFile(
		ExternalFolder $external,
		RestoringPoint $point,
		bool $create = true
	): File {
		$folder = $this->getExternalPointFolder($external, $point->getId());
		try {
			$metadataFile = $folder->get(MetadataService::METADATA_FILE);
		} catch (NotFoundException $e) {
			if (!$create) {
				throw new MetadataException('metadata file not found');
			}

			$metadataFile = $folder->newFile(MetadataService::METADATA_FILE, '');
		}

		try {
			/** @var RestoringPoint $stored */
			$stored = $this->deserializeJson($metadataFile->getContent(), RestoringPoint::class);
			$stored->setComment($point->getComment())
				   ->setArchive($point->isArchive())
				   ->setSubSignature($point->getSubSignature());
		} catch (Exception $e) {
			$stored = $point;
		}

		$metadataFile->putContent(json_encode($stored, JSON_PRETTY_PRINT));

		return $metadataFile;
	}


	/**
	 * @param ExternalFolder $external
	 * @param RestoringPoint $point
	 *
	 * @return RestoringHealth
	 * @throws ExternalFolderNotFoundException
	 * @throws GenericFileException
	 * @throws NotPermittedException
	 * @throws RestoringChunkPartNotFoundException
	 * @throws RestoringPointException
	 * @throws RestoringPointNotFoundException
	 * @throws RestoringPointPackException
	 * @throws RestoringPointUploadException
	 */
	public function getCurrentHealth(ExternalFolder $external, RestoringPoint $point): RestoringHealth {
		$stored = $this->getRestoringPoint($external, $point->getId(), true);
		if (!$stored->hasHealth()) {
			throw new RestoringPointUploadException('no health status attached');
		}

		return $stored->getHealth();
	}


	/**
	 * Update $point with it, but also returns the generated RestoringHealth
	 *
	 * @param ExternalFolder $external
	 * @param RestoringPoint $point
	 * @param RestoringChunkPart|null $part
	 *
	 * @return RestoringHealth
	 * @throws ExternalFolderNotFoundException
	 * @throws GenericFileException
	 * @throws NotPermittedException
	 * @throws RestoringChunkPartNotFoundException
	 * @throws RestoringPointException
	 * @throws RestoringPointPackException
	 */
	public function generateHealth(ExternalFolder $external, RestoringPoint $point): RestoringHealth {
		if (!$point->isStatus(RestoringPoint::STATUS_PACKED)) {
			throw new RestoringPointPackException('restoring point is not packed');
		}

		$health = new RestoringHealth();
		$globalStatus = RestoringHealth::STATUS_OK;
		foreach ($point->getRestoringData() as $data) {
			foreach ($data->getChunks() as $chunk) {
				foreach ($chunk->getParts() as $part) {
					$partHealth = new ChunkPartHealth(true);
					$status = $this->generatePartHealthStatus($external, $point, $chunk, $part);
					if ($status !== ChunkPartHealth::STATUS_OK) {
						$globalStatus = 0;
					}

					$partHealth->setDataName($data->getName())
							   ->setChunkName($chunk->getName())
							   ->setPartName($part->getName())
							   ->setStatus($status);
					$health->addPart($partHealth);
				}
			}
		}

		if ($globalStatus === RestoringHealth::STATUS_OK && $point->getParent() !== '') {
			try {
				$this->getRestoringPoint($external, $point->getParent());
			} catch (RestoringPointNotFoundException $e) {
				$globalStatus = RestoringHealth::STATUS_ORPHAN;
			}
		}

		$health->setChecked(time())
			   ->setStatus($globalStatus);
		$point->setHealth($health);

		return $health;
	}


	/**
	 * Update $point with it, but also returns the generated RestoringHealth
	 *
	 * @param ExternalFolder $external
	 * @param RestoringPoint $point
	 * @param RestoringHealth $health
	 * @param RestoringChunk $chunk
	 * @param RestoringChunkPart $part
	 *
	 * @return void
	 * @throws RestoringChunkPartNotFoundException
	 */
	public function updateChunkPartHealth(
		ExternalFolder $external,
		RestoringPoint $point,
		RestoringHealth $health,
		RestoringChunk $chunk,
		RestoringChunkPart $part
	): void {
		$partHealth = $health->getPart($chunk->getName(), $part->getName());
		$status = $this->generatePartHealthStatus($external, $point, $chunk, $part);
		$partHealth->setStatus($status);
	}


	/**
	 * @param ExternalFolder $external
	 * @param RestoringPoint $point
	 * @param RestoringChunk $chunk
	 * @param RestoringChunkPart $part
	 *
	 * @return int
	 */
	private function generatePartHealthStatus(
		ExternalFolder $external,
		RestoringPoint $point,
		RestoringChunk $chunk,
		RestoringChunkPart $part
	): int {
		try {
			$checksum = $this->getChecksum($external, $point, $chunk, $part);
			if ($checksum !== $part->getCurrentChecksum()) {
				return ChunkPartHealth::STATUS_CHECKSUM;
			}

			return ChunkPartHealth::STATUS_OK;
		} catch (ArchiveNotFoundException $e) {
			return ChunkPartHealth::STATUS_MISSING;
		}
	}


	/**
	 * @param ExternalFolder $external
	 * @param RestoringPoint $point
	 * @param RestoringChunk $chunk
	 * @param RestoringChunkPart $part
	 *
	 * @return string
	 * @throws ArchiveNotFoundException
	 */
	public function getChecksum(
		ExternalFolder $external,
		RestoringPoint $point,
		RestoringChunk $chunk,
		RestoringChunkPart $part
	): string {
		try {
			$path = '';
			if ($point->isPackage()) {
				throw new Exception('not managed yet, use documentation');
			} else {
				$folder = $this->getExternalChunkFolder($external, $point, $chunk, true);
				/** @var File $file */
				$file = $folder->get($part->getName());
				$stream = $file->fopen('rb');
//				*/
//				$file->
//				$stream = $file->();
			}
		} catch (Exception $e) {
			throw new ArchiveNotFoundException(
				'Part ' . $part->getName() . ' from ' . $chunk->getFilename() . ' not found. path: ' . $path
			);
		}

		if (is_bool($stream)) {
			throw new ArchiveNotFoundException('Chunk ' . $chunk->getFilename() . ' not valid');
		}

		return $this->getChecksumFromStream($stream);
	}


	/**
	 * @param ExternalFolder $external
	 * @param string $pointId
	 * @param bool $create
	 *
	 * @return Folder
	 * @throws ExternalFolderNotFoundException
	 * @throws NotPermittedException
	 * @throws RestoringPointException
	 * @throws RestoringPointNotFoundException
	 */
	public function getExternalPointFolder(
		ExternalFolder $external,
		string $pointId,
		bool $create = true
	): Folder {
		$this->initRootFolder($external);
		$folder = $external->getRootFolder();

		try {
			$node = $folder->get($pointId);
		} catch (NotFoundException $e) {
			if (!$create) {
				throw new RestoringPointNotFoundException();
			}

			$node = $folder->newFolder($pointId);
			$node->newFile(PointService::NOBACKUP_FILE);
		}

		if ($node->getType() !== FileInfo::TYPE_FOLDER) {
			throw new RestoringPointException('Mount point Node is not a folder');
		}

		return $node;
	}


	/**
	 * @param ExternalFolder $external
	 * @param RestoringPoint $point
	 * @param RestoringChunk $chunk
	 * @param bool $pack
	 *
	 * @return Folder
	 * @throws ExternalFolderNotFoundException
	 * @throws NotPermittedException
	 * @throws RestoringPointException
	 * @throws RestoringPointNotFoundException
	 */
	public function getExternalChunkFolder(
		ExternalFolder $external,
		RestoringPoint $point,
		RestoringChunk $chunk,
		bool $pack = false
	): Folder {
		$folder = $this->getExternalPointFolder($external, $point->getId());

		foreach (explode('/', $chunk->getPath()) as $subName) {
			try {
				/** @var Folder $sub */
				$sub = $folder->get($subName);
				if ($sub->getType() !== FileInfo::TYPE_FOLDER) {
					throw new ExternalFolderNotFoundException('External Chunk path is not a folder');
				}
			} catch (NotFoundException $e) {
				$sub = $folder->newFolder($subName);
			}

			$folder = $sub;
		}

		if (!$pack) {
			return $folder;
		}

		try {
			$sub = $folder->get($chunk->getName());
			if ($sub->getType() !== FileInfo::TYPE_FOLDER) {
				throw new ExternalFolderNotFoundException('External ChunkPart path is not a folder');
			}
		} catch (NotFoundException $e) {
			$sub = $folder->newFolder($chunk->getName());
		}

		return $sub;
	}


	/**
	 * @return ExternalFolder[]
	 * @throws InsufficientDataForMeaningfulAnswerException
	 * @throws StorageNotAvailableException
	 */
	public function getStorages(bool $includeUnusedStorage = true): array {
		if (is_null($this->globalStoragesService)) {
			return [];
		}

		$externals = $this->getAll();
		foreach ($this->globalStoragesService->getAllStorages() as $globalStorage) {
			try {
				$this->prepareStorageConfig($globalStorage);
			} catch (Exception $e) {
				$this->e($e);
				continue;
			}
			$storage = $this->constructStorage($globalStorage);
			$storageId = Storage::getNumericStorageId($storage->getId());

			$itemFound = false;
			foreach ($externals as $external) {
				if ($external->getStorageId() === $storageId) {
					$external->setStorage($storage->getId());
					$itemFound = true;
					break;
				}
			}

			if (!$itemFound && $includeUnusedStorage) {
				$externals[] = new ExternalFolder($storageId, $storage->getId());
			}
		}

		// Cleaning deprecated external storage
		foreach ($externals as $external) {
			if ($external->getRoot() !== '' && $external->getStorage() === '') {
				$this->remove($external->getStorageId());

				return $this->getStorages($includeUnusedStorage);
			}
		}

		return $externals;
	}

	/**
	 * @param int $storageId
	 *
	 * @return ExternalFolder
	 * @throws InsufficientDataForMeaningfulAnswerException
	 * @throws StorageNotAvailableException
	 * @throws ExternalFolderNotFoundException
	 */
	public function getStorageById(int $storageId): ExternalFolder {
		foreach ($this->getStorages() as $external) {
			if ($external->getStorageId() === $storageId) {
				return $external;
			}
		}

		throw new ExternalFolderNotFoundException('storage not found');
	}


	/**
	 * Process storage ready for mounting
	 * based on apps/files_external/lib/Config/ConfigAdapter.php
	 *
	 * @param StorageConfig $storage
	 *
	 * @throws InsufficientDataForMeaningfulAnswerException
	 * @throws StorageNotAvailableException
	 * @throws QueryException
	 */
	private function prepareStorageConfig(StorageConfig &$storage) {
		foreach ($storage->getBackendOptions() as $option => $value) {
			$storage->setBackendOption($option, MountConfig::substitutePlaceholdersInConfig($value));
		}

		$objectStore = $storage->getBackendOption('objectstore');
		if ($objectStore) {
			$objectClass = $objectStore['class'];
			if (!is_subclass_of($objectClass, '\OCP\Files\ObjectStore\IObjectStore')) {
				throw new \InvalidArgumentException('Invalid object store');
			}
			$storage->setBackendOption('objectstore', new $objectClass($objectStore));
		}

		$storage->getAuthMechanism()->manipulateStorageConfig($storage, $user);
		$storage->getBackend()->manipulateStorageConfig($storage, $user);
	}

	/**
	 * Construct the storage implementation
	 * based on apps/files_external/lib/Config/ConfigAdapter.php
	 *
	 * @param StorageConfig $storageConfig
	 *
	 * @return IStorage
	 * @throws InsufficientDataForMeaningfulAnswerException
	 * @throws StorageNotAvailableException
	 */
	private function constructStorage(StorageConfig $storageConfig): IStorage {
		$class = $storageConfig->getBackend()->getStorageClass();
		$storage = new $class($storageConfig->getBackendOptions());
		$storage = $storageConfig->getBackend()->wrapStorage($storage);

		return $storageConfig->getAuthMechanism()->wrapStorage($storage);
	}


	/**
	 * @param string $line
	 * @param bool $ln
	 */
	private function o(string $line, bool $ln = true): void {
		$this->outputService->o($line, $ln);
	}


	/**
	 * @param ExternalFolder $storage
	 */
	public function save(ExternalFolder $storage) {
		$this->externalFolderRequest->save($storage);
	}


	/**
	 * @param int $storageId
	 */
	public function remove(int $storageId) {
		$this->externalFolderRequest->remove($storageId);
	}
}
