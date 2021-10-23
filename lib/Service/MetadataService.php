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

use OCA\Backup\Db\PointRequest;
use OCA\Backup\Exceptions\RestoringPointLockException;
use OCA\Backup\Exceptions\RestoringPointNotFoundException;
use OCA\Backup\Model\RestoringPoint;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;

/**
 * Class MetadataService
 *
 * @package OCA\Backup\Service
 */
class MetadataService {
	public const METADATA_FILE = 'restoring-point.data';


	/** @var PointRequest */
	private $pointRequest;

	/** @var RemoteService */
	private $remoteService;

	/** @var ExternalFolderService */
	private $externalFolderService;


	/**
	 * MetadataService constructor.
	 *
	 * @param PointRequest $pointRequest
	 * @param RemoteService $remoteService
	 * @param ExternalFolderService $externalFolderService
	 */
	public function __construct(
		PointRequest $pointRequest,
		RemoteService $remoteService,
		ExternalFolderService $externalFolderService
	) {
		$this->pointRequest = $pointRequest;
		$this->remoteService = $remoteService;
		$this->externalFolderService = $externalFolderService;
	}


	/**
	 * @param RestoringPoint $point
	 *
	 * @throws NotPermittedException
	 * @throws NotFoundException
	 */
	public function saveMetadata(RestoringPoint $point) {
		$folder = $point->getBaseFolder();

		try {
			$file = $folder->getFile(self::METADATA_FILE);
		} catch (NotFoundException $e) {
			$file = $folder->newFile(self::METADATA_FILE);
		}

		$file->putContent(json_encode($point, JSON_PRETTY_PRINT));
	}


	/**
	 * @param RestoringPoint $point
	 */
	public function globalUpdate(RestoringPoint $point) {
		$this->externalFolderService->updateMetadata($point);
		$this->remoteService->updateMetadata($point);
	}


	/**
	 * @throws NotPermittedException
	 * @throws NotFoundException
	 */
	public function updateStatus(RestoringPoint $point) {
		$this->pointRequest->updateStatus($point);
		$this->saveMetadata($point);
	}

	/**
	 * @param RestoringPoint $point
	 */
	public function lock(RestoringPoint $point): void {
		$time = time();
		if ($point->getLock() > $time - 60) {
			return;
		}

		$point->setLock($time);
		$this->pointRequest->updateLock($point);
	}

	/**
	 * @param RestoringPoint $point
	 */
	public function unlock(RestoringPoint $point): void {
		if ($point->getLock() === 0) {
			return;
		}

		$point->setLock(0);
		$this->pointRequest->updateLock($point);
	}

	/**
	 * @param RestoringPoint $point
	 *
	 * @throws RestoringPointLockException
	 */
	public function isLock(RestoringPoint $point): void {
		try {
			$stored = $this->pointRequest->getById($point->getId());
		} catch (RestoringPointNotFoundException $e) {
			return;
		}

		$point->setLock($stored->getLock());

		if ($point->isLocked()) {
			throw new RestoringPointLockException('point is locked');
		}
	}
}
