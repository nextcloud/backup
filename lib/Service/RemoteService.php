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


use ArtificialOwl\MySmallPhpTools\Exceptions\InvalidItemException;
use ArtificialOwl\MySmallPhpTools\Model\Request;
use ArtificialOwl\MySmallPhpTools\Traits\Nextcloud\nc22\TNC22Logger;
use ArtificialOwl\MySmallPhpTools\Traits\Nextcloud\nc23\TNC23Deserialize;
use OCA\Backup\AppInfo\Application;
use OCA\Backup\Db\RemoteRequest;
use OCA\Backup\Exceptions\ChunkNotFoundException;
use OCA\Backup\Exceptions\RemoteInstanceException;
use OCA\Backup\Exceptions\RemoteInstanceNotFoundException;
use OCA\Backup\Exceptions\RemoteResourceNotFoundException;
use OCA\Backup\Model\RemoteInstance;
use OCA\Backup\Model\RestoringChunk;
use OCA\Backup\Model\RestoringPoint;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;


/**
 * Class RemoteService
 *
 * @package OCA\Backup\Service
 */
class RemoteService {


	use TNC22Logger;
	use TNC23Deserialize;


	/** @var RemoteRequest */
	private $remoteRequest;

	/** @var RemoteStreamService */
	private $remoteStreamService;

	/** @var ArchiveService */
	private $archiveService;


	/**
	 * RemoteService constructor.
	 *
	 * @param RemoteRequest $remoteRequest
	 * @param RemoteStreamService $remoteStreamService
	 * @param ArchiveService $archiveService
	 */
	public function __construct(
		RemoteRequest $remoteRequest,
		RemoteStreamService $remoteStreamService,
		ArchiveService $archiveService
	) {
		$this->remoteRequest = $remoteRequest;
		$this->remoteStreamService = $remoteStreamService;
		$this->archiveService = $archiveService;

		$this->setup('app', Application::APP_ID);
	}


	/**
	 * @return RemoteInstance[]
	 */
	public function getOutgoing(): array {
		return $this->remoteRequest->getOutgoing();
	}


	/**
	 * @param RestoringPoint $point
	 *
	 * @return array
	 */
	public function verifyPoint(RestoringPoint $point): array {
		$checks = [];
		foreach ($this->getOutgoing() as $remoteInstance) {
			try {
				$remote = $this->verifyRemotePoint($remoteInstance->getInstance(), $point);
				$checks[$remoteInstance->getInstance()] = $remote;
			} catch (RemoteInstanceException
			| RemoteInstanceNotFoundException
			| RemoteResourceNotFoundException $e) {
				continue;
			}
		}

		return $checks;
	}


	/**
	 * @param string $instance
	 * @param RestoringPoint $point
	 * @param bool $current
	 *
	 * @return RestoringPoint|null
	 * @throws RemoteInstanceException
	 * @throws RemoteInstanceNotFoundException
	 * @throws RemoteResourceNotFoundException
	 */
	public function verifyRemotePoint(
		string $instance,
		RestoringPoint $point,
		bool $current = false
	): ?RestoringPoint {
		$result = $this->remoteStreamService->resultRequestRemoteInstance(
			$instance,
			($current) ? RemoteInstance::RP_HEALTH : RemoteInstance::RP_GET,
			Request::TYPE_GET,
			null,
			['pointId' => $point->getId()]
		);

		try {
			/** @var RestoringPoint $remote */
			$remote = $this->deserialize($this->getArray($point->getId(), $result), RestoringPoint::class);

			return $remote;
		} catch (InvalidItemException $e) {
		}

		return null;
	}

	/**
	 * @param string $instance
	 * @param RestoringPoint $point
	 *
	 * @throws RemoteInstanceException
	 * @throws RemoteInstanceNotFoundException
	 * @throws RemoteResourceNotFoundException
	 * @throws InvalidItemException
	 */
	public function createPoint(string $instance, RestoringPoint $point): RestoringPoint {
		$remoteInstance = $this->remoteRequest->getFromInstance($instance);
		if (!$remoteInstance->isOutgoing()) {
			throw new RemoteInstanceException('instance not configured as outgoing');
		}

		$result = $this->remoteStreamService->resultRequestRemoteInstance(
			$remoteInstance->getInstance(),
			RemoteInstance::RP_CREATE,
			Request::TYPE_PUT,
			$point
		);

		/** @var RestoringPoint $remote */
		$remote = $this->deserialize($this->getArray($point->getId(), $result), RestoringPoint::class);

		return $remote;
	}


	/**
	 * @param string $instance
	 * @param RestoringPoint $point
	 * @param string $chunk
	 *
	 * @return bool
	 * @throws RemoteInstanceException
	 * @throws RemoteInstanceNotFoundException
	 * @throws RemoteResourceNotFoundException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function uploadChunk(
		string $instance,
		RestoringPoint $point,
		RestoringChunk $chunk
	): bool {
		$result = $this->remoteStreamService->resultRequestRemoteInstance(
			$instance,
			RemoteInstance::RP_UPLOAD,
			Request::TYPE_POST,
			$chunk,
			['pointId' => $point->getId()]
		);

//		echo '****** ' . json_Encode($result);

		return true;
	}


}

