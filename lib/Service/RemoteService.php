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
use ArtificialOwl\MySmallPhpTools\Exceptions\SignatoryException;
use ArtificialOwl\MySmallPhpTools\Model\Request;
use ArtificialOwl\MySmallPhpTools\Traits\Nextcloud\nc22\TNC22Logger;
use ArtificialOwl\MySmallPhpTools\Traits\Nextcloud\nc23\TNC23Deserialize;
use Exception;
use OCA\Backup\AppInfo\Application;
use OCA\Backup\Db\RemoteRequest;
use OCA\Backup\Exceptions\RemoteInstanceException;
use OCA\Backup\Exceptions\RemoteInstanceNotFoundException;
use OCA\Backup\Exceptions\RemoteResourceNotFoundException;
use OCA\Backup\Exceptions\RestoringChunkPartNotFoundException;
use OCA\Backup\Exceptions\RestoringPointNotFoundException;
use OCA\Backup\Exceptions\RestoringPointUploadException;
use OCA\Backup\Model\RemoteInstance;
use OCA\Backup\Model\RestoringChunk;
use OCA\Backup\Model\RestoringChunkPart;
use OCA\Backup\Model\RestoringPoint;


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

	/** @var ChunkService */
	private $chunkService;

	/** @var OutputService */
	private $outputService;


	/**
	 * RemoteService constructor.
	 *
	 * @param RemoteRequest $remoteRequest
	 * @param RemoteStreamService $remoteStreamService
	 * @param ChunkService $chunkService
	 * @param OutputService $outputService
	 */
	public function __construct(
		RemoteRequest $remoteRequest,
		RemoteStreamService $remoteStreamService,
		ChunkService $chunkService,
		OutputService $outputService
	) {
		$this->remoteRequest = $remoteRequest;
		$this->remoteStreamService = $remoteStreamService;
		$this->chunkService = $chunkService;
		$this->outputService = $outputService;

		$this->setup('app', Application::APP_ID);
	}


	/**
	 * @return RemoteInstance[]
	 */
	public function getOutgoing(): array {
		return $this->remoteRequest->getOutgoing();
	}

	/**
	 * @param bool $includeExtraDataOnSerialize
	 *
	 * @return array
	 */
	public function getAll(bool $includeExtraDataOnSerialize = false): array {
		return $this->remoteRequest->getAll($includeExtraDataOnSerialize);
	}


	/**
	 * @param string $instance
	 *
	 * @return RemoteInstance
	 * @throws RemoteInstanceNotFoundException
	 */
	public function getByInstance(string $instance): RemoteInstance {
		return $this->remoteRequest->getByInstance($instance);
	}

	/**
	 * @param string $instance
	 * @param string $pointId
	 * @param bool $current
	 *
	 * @return RestoringPoint
	 * @throws RemoteInstanceException
	 * @throws RemoteInstanceNotFoundException
	 * @throws RemoteResourceNotFoundException
	 * @throws RestoringPointNotFoundException
	 */
	public function getRestoringPoint(
		string $instance,
		string $pointId,
		bool $current = false
	): RestoringPoint {
		$result = $this->remoteStreamService->resultRequestRemoteInstance(
			$instance,
			($current) ? RemoteInstance::RP_HEALTH : RemoteInstance::RP_GET,
			Request::TYPE_GET,
			null,
			['pointId' => $pointId]
		);

		try {
			/** @var RestoringPoint $remote */
			$remote = $this->deserialize($result, RestoringPoint::class);

			return $remote;
		} catch (InvalidItemException $e) {
		}

		throw new RestoringPointNotFoundException();
	}


	/**
	 * @param string $instance
	 *
	 * @return RestoringPoint[]
	 * @throws RemoteInstanceException
	 * @throws RemoteInstanceNotFoundException
	 * @throws RemoteResourceNotFoundException
	 */
	public function getRestoringPoints(string $instance): array {
		$result = $this->remoteStreamService->resultRequestRemoteInstance(
			$instance,
			RemoteInstance::RP_LIST
		);

		return $this->deserializeArray($result, RestoringPoint::class);
	}


	/**
	 * @param RemoteInstance $remote
	 * @param RestoringPoint $point
	 *
	 * @return RestoringPoint
	 * @throws RemoteInstanceException
	 * @throws RestoringPointNotFoundException
	 */
	public function createPoint(RemoteInstance $remote, RestoringPoint $point): RestoringPoint {
		$this->o('  * Creating Restoring Point on remote instance: ', false);

		if (!$remote->isOutgoing()) {
			throw new RemoteInstanceException('instance not configured as outgoing');
		}

		try {
			$result = $this->remoteStreamService->resultRequestRemoteInstance(
				$remote->getInstance(),
				RemoteInstance::RP_CREATE,
				Request::TYPE_PUT,
				$point
			);

			/** @var RestoringPoint $stored */
			try {
				$stored = $this->deserialize($result, RestoringPoint::class);
			} catch (InvalidItemException $e) {
				throw new RestoringPointNotFoundException('restoring point not created');
			}

		} catch (RemoteInstanceException
		| RemoteResourceNotFoundException
		| RestoringPointNotFoundException
		| SignatoryException
		| RemoteInstanceNotFoundException $e) {
			$this->o('<error>' . $e->getMessage() . '</error>');
			throw $e;
		}

		$this->o('<info>ok</info>');

		return $stored;
	}


	/**
	 * @param string $instance
	 * @param RestoringPoint $point
	 * @param RestoringChunk $chunk
	 * @param RestoringChunkPart $part
	 *
	 * @return bool
	 * @throws RemoteInstanceException
	 * @throws RemoteInstanceNotFoundException
	 * @throws RemoteResourceNotFoundException
	 */
	public function uploadPart(
		string $instance,
		RestoringPoint $point,
		RestoringChunk $chunk,
		RestoringChunkPart $part
	): bool {
		$result = $this->remoteStreamService->resultRequestRemoteInstance(
			$instance,
			RemoteInstance::RP_UPLOAD,
			Request::TYPE_POST,
			$part,
			[
				'pointId' => $point->getId(),
				'chunkName' => $chunk->getName()
			],
			true
		);

//		echo '****** ' . json_Encode($result);

		return true;
	}


	/**
	 * @param string $instance
	 * @param RestoringPoint $point
	 * @param RestoringChunk $chunk
	 * @param RestoringChunkPart $part
	 *
	 * @return RestoringChunkPart
	 * @throws RemoteInstanceException
	 * @throws RemoteInstanceNotFoundException
	 * @throws RemoteResourceNotFoundException
	 * @throws RestoringChunkPartNotFoundException
	 */
	public function downloadPart(
		string $instance,
		RestoringPoint $point,
		RestoringChunk $chunk,
		RestoringChunkPart $part
	): void {
		$result = $this->remoteStreamService->resultRequestRemoteInstance(
			$instance,
			RemoteInstance::RP_DOWNLOAD,
			Request::TYPE_GET,
			$part,
			['pointId' => $point->getId(), 'chunkName' => $chunk->getName()],
			true
		);

		try {
			/** @var RestoringChunkPart $downloaded */
			$downloaded = $this->deserialize($result, RestoringChunkPart::class);
			$part->setContent($downloaded->getContent());
		} catch (InvalidItemException $e) {
			throw new RestoringChunkPartNotFoundException();
		}
	}


	/**
	 * @param RestoringPoint $point
	 * @param RemoteInstance $remote
	 *
	 * @return RestoringPoint
	 * @throws InvalidItemException
	 * @throws RemoteInstanceException
	 * @throws RemoteInstanceNotFoundException
	 * @throws RemoteResourceNotFoundException
	 * @throws RestoringPointNotFoundException
	 * @throws SignatoryException
	 */
	public function confirmPoint(
		RemoteInstance $remote,
		RestoringPoint $point
	): RestoringPoint {
		try {
			$stored = $this->getRestoringPoint($remote->getInstance(), $point->getId());
			$this->o('  > restoring point found');
		} catch (RemoteInstanceException $e) {
			$this->o('  ! <error>check configuration on remote instance</error>');
			throw $e;
		} catch (
		RemoteInstanceNotFoundException
		| RemoteResourceNotFoundException $e) {
			$this->o('  ! <error>cannot communicate with remote instance</error>');
			throw $e;
		} catch (RestoringPointNotFoundException $e) {
			$this->o('  > <comment>restoring point not found</comment>');
			try {
				$stored = $this->createPoint($remote, $point);
				$this->o('  > restoring point created');
			} catch (Exception $e) {
				$this->o('  ! <error>cannot create restoring point</error>');
				throw $e;
			}
		}

		return $stored;
	}


	/**
	 * @param RemoteInstance $remote
	 * @param RestoringPoint $point
	 *
	 * @return RestoringPoint
	 * @throws RemoteInstanceException
	 * @throws RemoteInstanceNotFoundException
	 * @throws RemoteResourceNotFoundException
	 * @throws RestoringPointUploadException
	 * @throws RestoringPointNotFoundException
	 */
	public function getCurrentHealth(
		RemoteInstance $remote,
		RestoringPoint $point
	): RestoringPoint {
		$stored = $this->getRestoringPoint($remote->getInstance(), $point->getId(), true);
		if (!$stored->hasHealth()) {
			throw new RestoringPointUploadException('no health status attached');
		}

		return $stored;
	}


	/**
	 * @param string $line
	 * @param bool $ln
	 */
	private function o(string $line, bool $ln = true): void {
		$this->outputService->o($line, $ln);
	}

}

