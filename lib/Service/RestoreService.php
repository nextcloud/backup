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
use ArtificialOwl\MySmallPhpTools\Traits\TFileTools;
use ArtificialOwl\MySmallPhpTools\Traits\TStringTools;
use DateTime;
use Exception;
use OC\Files\Node\File;
use OC\Files\Node\Folder;
use OC\User\NoUserException;
use OCA\Backup\Db\CoreRequestBuilder;
use OCA\Backup\Exceptions\MetadataException;
use OCA\Backup\Exceptions\RestoringPointException;
use OCA\Backup\Model\RestoringChunk;
use OCA\Backup\Model\RestoringChunkPart;
use OCA\Backup\Model\RestoringData;
use OCA\Backup\Model\RestoringPoint;
use OCP\Files\FileInfo;
use OCP\Files\GenericFileException;
use OCP\Files\InvalidPathException;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Lock\LockedException;

/**
 * Class RestoreService
 *
 * @package OCA\Backup\Service
 */
class RestoreService {
	use TArrayTools;
	use TStringTools;
	use TFileTools;


	/** @var IRootFolder */
	private $rootFolder;

	/** @var CoreRequestBuilder */
	private $coreRequestBuilder;

	/** @var FilesService */
	private $filesService;

	/** @var ConfigService */
	private $configService;


	/**
	 * RestoreService constructor.
	 *
	 * @param IRootFolder $rootFolder
	 * @param CoreRequestBuilder $coreRequestBuilder
	 * @param FilesService $filesService
	 * @param ConfigService $configService
	 */
	public function __construct(
		IRootFolder $rootFolder,
		CoreRequestBuilder $coreRequestBuilder,
		FilesService $filesService,
		ConfigService $configService
	) {
		$this->rootFolder = $rootFolder;
		$this->coreRequestBuilder = $coreRequestBuilder;
		$this->filesService = $filesService;
		$this->configService = $configService;
	}


	/**
	 *
	 */
	public function finalizeFullRestore(): void {
		$this->configService->setAppValue(ConfigService::LAST_FULL_RP, '');

		$this->coreRequestBuilder->emptyTable(CoreRequestBuilder::TABLE_AUTHTOKEN);
	}


	/**
	 * @param int $fileId
	 * @param string $owner
	 * @param Folder|null $folder
	 *
	 * @return RestoringPoint
	 * @throws LockedException
	 * @throws NoUserException
	 * @throws NotPermittedException
	 * @throws RestoringPointException
	 */
	public function actionFromFileId(int $fileId, string $owner, ?Folder &$folder = null): RestoringPoint {
		$storage = $this->rootFolder->getUserFolder($owner);
		$nodes = $storage->getById($fileId);

		foreach ($nodes as $node) {
			if ($node->getType() !== FileInfo::TYPE_FILE) {
				continue;
			}

			/** @var File $node */
			$folder = $node->getParent();
			$content = json_decode($node->getContent(), true);
			if ($this->get('action', $content) === 'generate') {
				return $this->restoreMetadataFromFolder($folder, $this->get('id', $content));
			}
		}

		throw new RestoringPointException();
	}


	/**
	 * @param Folder $folder
	 * @param string $id
	 *
	 * @return RestoringPoint
	 * @throws LockedException
	 * @throws MetadataException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws GenericFileException
	 */
	public function restoreMetadataFromFolder(Folder $folder, string $id): RestoringPoint {
		$point = new RestoringPoint();

		$date = time();
		if ($id !== '' && ($pos = strpos($id, '-')) > 0) {
			try {
				$fromId = (int)substr($id, 0, $pos);
				if ($fromId > 0) {
					$dT = new DateTime((string)$fromId);
					$date = $dT->getTimestamp();
				}
			} catch (Exception $e) {
			}
		}

		$point->setDate($date);
		if ($id === '') {
			$id = date('YmdHis', $point->getDate()) . $this->token();
		}

		$isPacked = false;
		$point->setId($id);
		$data = $this->generateRestoringDataFromFolder($folder, $isPacked);
		if ($isPacked) {
			$point->addStatus(RestoringPoint::STATUS_UNKNOWN)
				  ->addStatus(RestoringPoint::STATUS_PACKED)
				  ->addStatus(RestoringPoint::STATUS_PACKING);
		}

		$point->setRestoringData($data)
			  ->setArchive(true);

		/** @var File $metadata */
		try {
			$metadata = $folder->get(MetadataService::METADATA_FILE);
		} catch (NotFoundException $e) {
			$metadata = $folder->newFile(MetadataService::METADATA_FILE);
		}

		if ($metadata->getType() !== FileInfo::TYPE_FILE) {
			throw new MetadataException(MetadataService::METADATA_FILE . ' is not a file');
		}
		$metadata->putContent(json_encode($point, JSON_PRETTY_PRINT));

		return $point;
	}


	/**
	 * @param Folder $folder
	 * @param bool $isPacked
	 *
	 * @return array
	 * @throws InvalidPathException
	 * @throws LockedException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	private function generateRestoringDataFromFolder(Folder $folder, bool &$isPacked): array {
		$result = [];
		foreach ($folder->getDirectoryListing() as $node) {
			/** @var Folder $node */
			if ($node->getType() !== FileInfo::TYPE_FOLDER) {
				continue;
			}

			$dataName = $node->getName();
			$data = $this->generateRestoringDataBasedOnDataName($dataName);
			$this->generateChunkPartsFromFolder($data, $node, $isPacked);

			$result[] = $data;
		}

		return $result;
	}


	/**
	 * @param string $dataName
	 *
	 * @return RestoringData
	 */
	private function generateRestoringDataBasedOnDataName(string $dataName): RestoringData {
		$data = new RestoringData();
		$data->setName($dataName);

		switch ($dataName) {
			case RestoringData::NEXTCLOUD:
				$data->setType(RestoringData::ROOT_NEXTCLOUD);
				break;
			case RestoringData::APPS:
				$data->setType(RestoringData::ROOT_APPS);
				break;
			case RestoringData::DATA:
				$data->setType(RestoringData::ROOT_DATA);
				break;
			case RestoringData::SQL_DUMP:
				$data->setType(RestoringData::FILE_SQL_DUMP);
				break;
			case RestoringData::CONFIG:
				$data->setType(RestoringData::FILE_CONFIG);
				break;

			default:
				$data->setType(RestoringData::ROOT_DISK);
		}

		$this->filesService->initRestoringData($data);

		return $data;
	}


	/**
	 * @param RestoringData $data
	 * @param Folder $folder
	 * @param bool $isPacked
	 *
	 * @throws InvalidPathException
	 * @throws LockedException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	private function generateChunkPartsFromFolder(
		RestoringData $data,
		Folder $folder,
		bool &$isPacked
	): void {
		foreach ($folder->getDirectoryListing() as $node) {
			/** @var Folder $node */
			if ($node->getType() !== FileInfo::TYPE_FOLDER) {
				continue;
			}

			$chunkName = $node->getName();
			if (strpos($chunkName, '-') === false) {
				continue;
			}

			[$dataName, $t1, $t2, $t3, $t4, $t5] = explode('-', $chunkName);
			if ($dataName !== $data->getName()
				|| strlen($t1) <> 8 // I am sure there is a cleaner way ...
				|| strlen($t2) <> 4
				|| strlen($t3) <> 4
				|| strlen($t4) <> 4
				|| strlen($t5) <> 12) {
				continue;
			}

			$chunk = new RestoringChunk();
			$chunk->setName($chunkName)
				  ->setPath('/' . $folder->getName() . '/' . $chunkName . '/');

			try {
				/** @var File $chunkFile */
				$chunkFile = $node->get($chunkName . '.zip');
				$chunk->setSize($chunkFile->getSize());

				$read = $chunkFile->fopen('rb');
				$chunk->setChecksum($this->getChecksumFromStream($read));
				fclose($read);

				// TODO: generate Checksum, files count ? check if zip is valid ?
			} catch (NotFoundException $e) {
				try {
					/** @var File $chunkFile */
					$chunkFile = $node->get($chunkName . '.zip.gz');
					$chunk->setSize($chunkFile->getSize())
						  ->setCompression(1);

					$read = $chunkFile->fopen('rb');
					$chunk->setChecksum($this->getChecksumFromStream($read));
					fclose($read);
				} catch (NotFoundException $e) {
					$isPacked = true;
					foreach ($node->getDirectoryListing() as $item) {
						/** @var File $item */
						if ($item->getType() !== FileInfo::TYPE_FILE) {
							continue;
						}

						$partName = $item->getName();

						[$order, $token] = explode('-', $partName);
						$order = (int)$order;
						if ($order === 0 || strlen($token) !== 15) {
							continue;
						}

						$part = new RestoringChunkPart($partName, $order);

						$read = $item->fopen('rb');
						$part->setChecksum($this->getChecksumFromStream($read));
						fclose($read);

						$chunk->addPart($part);
					}
				}
			}
			$data->addChunk($chunk);
		}
	}
}
