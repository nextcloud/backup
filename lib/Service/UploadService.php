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

use ArtificialOwl\MySmallPhpTools\Traits\Nextcloud\nc23\TNC23Logger;
use ArtificialOwl\MySmallPhpTools\Traits\TStringTools;
use Exception;
use OCA\Backup\Exceptions\ExternalFolderNotFoundException;
use OCA\Backup\Exceptions\JobsTimeSlotException;
use OCA\Backup\Exceptions\RemoteInstanceException;
use OCA\Backup\Exceptions\RemoteInstanceNotFoundException;
use OCA\Backup\Exceptions\RemoteResourceNotFoundException;
use OCA\Backup\Exceptions\RestoringChunkNotFoundException;
use OCA\Backup\Exceptions\RestoringChunkPartNotFoundException;
use OCA\Backup\Exceptions\RestoringPointException;
use OCA\Backup\Exceptions\RestoringPointLockException;
use OCA\Backup\Exceptions\RestoringPointNotFoundException;
use OCA\Backup\Exceptions\RestoringPointNotInitiatedException;
use OCA\Backup\Exceptions\RestoringPointPackException;
use OCA\Backup\Model\ChunkPartHealth;
use OCA\Backup\Model\ExternalFolder;
use OCA\Backup\Model\RestoringHealth;
use OCA\Backup\Model\RestoringPoint;
use OCP\Files\GenericFileException;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Lock\LockedException;

/**
 * Class UploadService
 *
 * @package OCA\Backup\Service
 */
class UploadService {
	use TStringTools;
	use TNC23Logger;


	public const PACK_SIZE = 1000000;
	public const CHUNK_ENTRY = 'pack';


	/** @var PointService */
	private $pointService;

	/** @var ChunkService */
	private $chunkService;

	/** @var PackService */
	private $packService;

	/** @var RemoteService */
	private $remoteService;

	/** @var ExternalFolderService */
	private $externalFolderService;

	/** @var MetadataService */
	private $metadataService;

	/** @var CronService */
	private $cronService;

	/** @var OutputService */
	private $outputService;

	/** @var ConfigService */
	private $configService;


	/**
	 * UploadService constructor.
	 *
	 * @param PointService $pointService
	 * @param ChunkService $chunkService
	 * @param PackService $packService
	 * @param RemoteService $remoteService
	 * @param ExternalFolderService $externalFolderService
	 * @param MetadataService $metadataService
	 * @param CronService $cronService
	 * @param OutputService $outputService
	 * @param ConfigService $configService
	 */
	public function __construct(
		PointService $pointService,
		ChunkService $chunkService,
		PackService $packService,
		RemoteService $remoteService,
		ExternalFolderService $externalFolderService,
		MetadataService $metadataService,
		CronService $cronService,
		OutputService $outputService,
		ConfigService $configService
	) {
		$this->pointService = $pointService;
		$this->chunkService = $chunkService;
		$this->packService = $packService;
		$this->remoteService = $remoteService;
		$this->externalFolderService = $externalFolderService;
		$this->metadataService = $metadataService;
		$this->cronService = $cronService;
		$this->outputService = $outputService;
		$this->configService = $configService;
	}


	/**
	 * @param RestoringPoint $point
	 *
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws RestoringPointLockException
	 * @throws RestoringPointPackException
	 */
	public function initUpload(RestoringPoint $point): void {
		if (!$point->isStatus(RestoringPoint::STATUS_PACKED)
			|| $point->isStatus(RestoringPoint::STATUS_PACKING)) {
			throw new RestoringPointPackException('restoring point is not packed');
		}

		$this->metadataService->isLock($point);
		$this->metadataService->lock($point);
		$this->pointService->initBaseFolder($point);
	}


	/**
	 * @param RestoringPoint $point
	 */
	public function closeUpload(RestoringPoint $point): void {
		$this->metadataService->unlock($point);
	}


	/**
	 * @param RestoringPoint $point
	 *
	 * @throws ExternalFolderNotFoundException
	 * @throws JobsTimeSlotException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws RemoteInstanceNotFoundException
	 * @throws RestoringPointLockException
	 * @throws RestoringPointPackException
	 */
	public function uploadPoint(RestoringPoint $point): void {
		$this->initUpload($point);
		$this->uploadToRemoteInstances($point);
		$this->uploadToExternalFolder($point);
		$this->closeUpload($point);
	}


	/**
	 * @param RestoringPoint $point
	 * @param string $instance
	 *
	 * @throws RemoteInstanceNotFoundException
	 * @throws JobsTimeSlotException
	 */
	public function uploadToRemoteInstances(RestoringPoint $point, string $instance = ''): void {
		if (!$this->configService->isRemoteEnabled()) {
			return;
		}

		$this->o('- uploading ' . $point->getId() . ' to remote instances');
		if ($instance !== '') {
			$remotes = [$this->remoteService->getByInstance($instance)];
		} else {
			$remotes = $this->remoteService->getOutgoing();
		}

		foreach ($remotes as $remote) {
			try {
				$this->o(' - checking remote instance <info>' . $remote->getInstance() . '</info>');

				$stored = $this->remoteService->confirmPoint($remote, $point);

				// TODO: get fresh health
				if (!$stored->hasHealth()) {
					$this->o('no health status available');
					continue;
				}

				$health = $stored->getHealth();
				$this->o('  > Health status: ' . $this->outputService->displayHealth($health));
				if ($health->getStatus() === RestoringHealth::STATUS_OK) {
					continue;
				}

				$this->uploadMissingFilesToRemoteInstance($remote->getInstance(), $point, $health);
				$this->remoteService->getRestoringPoint($remote->getInstance(), $point->getId(), true);
			} catch (RestoringChunkPartNotFoundException $e) {
				try {
//					$this->remoteService->deletePointExternal($remote, $point->getId());
					$this->o('<error>package is out of sync</error>');
				} catch (Exception $e) {
					$this->o('<error>cannot delete out of sync package</error>');
				}
			} catch (JobsTimeSlotException $e) {
				throw $e;
			} catch (Exception $e) {
			}
		}
	}


	/**
	 * @param string $instance
	 * @param RestoringPoint $point
	 * @param RestoringHealth $health
	 *
	 * @throws JobsTimeSlotException
	 */
	private function uploadMissingFilesToRemoteInstance(
		string $instance,
		RestoringPoint $point,
		RestoringHealth $health
	): void {
		foreach ($health->getParts() as $partHealth) {
			if ($partHealth->getStatus() === ChunkPartHealth::STATUS_OK) {
				continue;
			}

			$this->cronService->lockCron();

			if ($partHealth->getChunkName() === $partHealth->getPartName()) {
				$this->o(
					'  * Uploading <info>' . $partHealth->getDataName() . '</info>/<info>'
					. $partHealth->getChunkName() . '</info>: ',
					false
				);
			} else {
				$this->o(
					'  * Uploading <info>' . $partHealth->getDataName() . '</info>/<info>'
					. $partHealth->getChunkName() . '</info>/<info>' . $partHealth->getPartName()
					. '</info>: ',
					false
				);
			}

			try {
				$chunk = $this->chunkService->getChunkFromRP(
					$point,
					$partHealth->getChunkName(),
					$partHealth->getDataName()
				);
				$part = clone $this->packService->getPartFromChunk($chunk, $partHealth->getPartName());
				$this->packService->getChunkPartContent($point, $chunk, $part);

				$this->remoteService->uploadPart($instance, $point, $chunk, $part);
				$this->o('<info>ok</info>');
			} catch (
			RestoringChunkNotFoundException
			| RemoteInstanceException
			| RemoteInstanceNotFoundException
			| RestoringChunkPartNotFoundException
			| RestoringPointNotInitiatedException
			| RemoteResourceNotFoundException $e) {
				$this->o('<error>' . get_class($e) . $e->getMessage() . '</error>');
			}
		}
	}


	/**
	 * @param RestoringPoint $point
	 * @param int $storageId
	 *
	 * @throws ExternalFolderNotFoundException
	 * @throws JobsTimeSlotException
	 */
	public function uploadToExternalFolder(RestoringPoint $point, int $storageId = 0): void {
		$this->o('- uploading ' . $point->getId() . ' to external folders');

		if ($storageId > 0) {
			$externals = [$this->externalFolderService->getByStorageId($storageId)];
		} else {
			$externals = $this->externalFolderService->getAll();
		}

		foreach ($externals as $external) {
			try {
				$this->o(
					' - checking external folder <info>' . $external->getStorageId() .
					'</info>:<info>' . $external->getRoot() . '</info>'
				);

				$stored = $this->externalFolderService->confirmPoint($external, $point);

				// TODO: get fresh health
				if (!$stored->hasHealth()) {
					$this->o('no health status available');
					continue;
				}

				$health = $stored->getHealth();
//			} else {
//					try {
//						$this->o('  > <comment>no health status attached</comment>');
//						$this->o('  * Requesting detailed Health status: ', false);
//						try {
//							$health = $this->externalFolderService->getCurrentHealth($external, $point);
//							$this->o('<info>ok</info>');
//						} catch (Exception $e) {
//							$this->o('<error>' . $e->getMessage() . '</error>');
//							continue;
//						}
//					} catch (Exception $e) {
//						continue;
//					}
//				}

				$this->o('  > Health status: ' . $this->outputService->displayHealth($health));
				if ($health->getStatus() === RestoringHealth::STATUS_OK) {
					continue;
				}

				$this->uploadMissingFilesToExternalFolder($external, $point, $health);
				$this->externalFolderService->getRestoringPoint($external, $point->getId(), true);
			} catch (RestoringChunkPartNotFoundException $e) {
				try {
					$this->externalFolderService->deletePointExternal($external, $point->getId());
					$this->o('<error>package is out of sync</error>');
				} catch (Exception $e) {
					$this->o('<error>cannot delete out of sync package</error>');
				}
			} catch (JobsTimeSlotException $e) {
				throw $e;
			} catch (Exception $e) {
				$this->o(
					' ! issue while checking external folder: <error>' . get_class($e) .
					' ' . $e->getMessage() . '</error>'
				);
				continue;
			}
		}
	}


	/**
	 * @param ExternalFolder $external
	 * @param RestoringPoint $point
	 * @param RestoringHealth $health
	 *
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws RestoringChunkPartNotFoundException
	 * @throws JobsTimeSlotException
	 */
	private function uploadMissingFilesToExternalFolder(
		ExternalFolder $external,
		RestoringPoint $point,
		RestoringHealth $health
	): void {
		$this->pointService->initBaseFolder($point);
		foreach ($health->getParts() as $partHealth) {
			if ($partHealth->getStatus() === ChunkPartHealth::STATUS_OK) {
				continue;
			}

			$this->cronService->lockCron();

			if ($partHealth->getChunkName() === $partHealth->getPartName()) {
				$this->o(
					'  * Uploading <info>' . $partHealth->getDataName() . '</info>/<info>'
					. $partHealth->getChunkName() . '</info>: ',
					false
				);
			} else {
				$this->o(
					'  * Uploading <info>' . $partHealth->getDataName() . '</info>/<info>'
					. $partHealth->getChunkName() . '</info>/<info>' . $partHealth->getPartName()
					. '</info>: ',
					false
				);
			}

			try {
				$chunk = $this->chunkService->getChunkFromRP(
					$point,
					$partHealth->getChunkName(),
					$partHealth->getDataName()
				);
				$part = clone $this->packService->getPartFromChunk($chunk, $partHealth->getPartName());
				$this->packService->getChunkPartContent($point, $chunk, $part);

				$this->externalFolderService->uploadPart($external, $point, $health, $chunk, $part);
				$this->o('<info>ok</info>');
			} catch (
			RestoringChunkNotFoundException |
			RestoringPointNotInitiatedException |
			RestoringPointException |
			RestoringPointNotFoundException |
			ExternalFolderNotFoundException |
			GenericFileException |
			NotPermittedException |
			LockedException $e) {
				$this->o('<error>' . get_class($e) . $e->getMessage() . '</error>');
			}
		}
	}


	/**
	 * @param string $line
	 * @param bool $ln
	 */
	private function o(string $line, bool $ln = true): void {
		$this->outputService->o($line, $ln);
	}
}
