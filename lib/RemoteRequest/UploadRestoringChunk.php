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


namespace OCA\Backup\RemoteRequest;

use ArtificialOwl\MySmallPhpTools\Exceptions\InvalidItemException;
use ArtificialOwl\MySmallPhpTools\IDeserializable;
use ArtificialOwl\MySmallPhpTools\Traits\Nextcloud\nc23\TNC23Deserialize;
use ArtificialOwl\MySmallPhpTools\Traits\Nextcloud\nc23\TNC23Logger;
use OCA\Backup\AppInfo\Application;
use OCA\Backup\Exceptions\RestoringChunkNotFoundException;
use OCA\Backup\Exceptions\RestoringPointNotFoundException;
use OCA\Backup\Exceptions\RestoringPointNotInitiatedException;
use OCA\Backup\IRemoteRequest;
use OCA\Backup\Model\RestoringChunkPart;
use OCA\Backup\Service\ChunkService;
use OCA\Backup\Service\PackService;
use OCA\Backup\Service\PointService;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;

/**
 * Class UploadRestoringChunk
 *
 * @package OCA\Backup\RemoteRequest
 */
class UploadRestoringChunk extends CoreRequest implements IRemoteRequest {
	use TNC23Deserialize;
	use TNC23Logger;


	/** @var PointService */
	private $pointService;

	/** @var ChunkService */
	private $chunkService;

	/** @var PackService */
	private $packService;


	/**
	 * UploadRestoringChunk constructor.
	 *
	 * @param PointService $pointService
	 * @param ChunkService $chunkService
	 * @param PackService $packService
	 */
	public function __construct(
		PointService $pointService,
		ChunkService $chunkService,
		PackService $packService
	) {
		parent::__construct();

		$this->pointService = $pointService;
		$this->chunkService = $chunkService;
		$this->packService = $packService;

		$this->setup('app', Application::APP_ID);
	}


	/**
	 * @throws RestoringChunkNotFoundException
	 * @throws RestoringPointNotInitiatedException
	 */
	public function execute(): void {
		try {
			$signedRequest = $this->getSignedRequest();
			$signatory = $signedRequest->getSignatory();

			$pointId = $signedRequest->getIncomingRequest()->getParam('pointId');
			$chunkName = $signedRequest->getIncomingRequest()->getParam('chunkName');

			$point = $this->pointService->getRestoringPoint($pointId, $signatory->getInstance());

			$chunk = $this->chunkService->getChunkFromRP($point, $chunkName);
			/** @var RestoringChunkPart $part */
			$part = $this->deserializeJson($signedRequest->getBody(), RestoringChunkPart::class);

			$this->pointService->initBaseFolder($point);
			$this->packService->saveChunkPartContent($point, $chunk, $part);

			$this->setOutcome($this->serialize($point));
		} catch (RestoringPointNotFoundException
		| InvalidItemException
		| NotFoundException
		| NotPermittedException $e) {
		}
	}


	/**
	 * @param array $data
	 *
	 * @return IDeserializable
	 */
	public function import(array $data): IDeserializable {
		return $this;
	}
}
