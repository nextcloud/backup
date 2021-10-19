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

use ArtificialOwl\MySmallPhpTools\IDeserializable;
use ArtificialOwl\MySmallPhpTools\Traits\Nextcloud\nc23\TNC23Deserialize;
use ArtificialOwl\MySmallPhpTools\Traits\Nextcloud\nc23\TNC23Logger;
use OCA\Backup\AppInfo\Application;
use OCA\Backup\Db\PointRequest;
use OCA\Backup\Exceptions\RestoringPointNotFoundException;
use OCA\Backup\IRemoteRequest;
use OCA\Backup\Model\RemoteInstance;
use OCA\Backup\Service\PointService;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;

/**
 * Class DeleteRestoringPoint
 *
 * @package OCA\Backup\RemoteRequest
 */
class DeleteRestoringPoint extends CoreRequest implements IRemoteRequest {
	use TNC23Logger;
	use TNC23Deserialize;


	/** @var PointRequest */
	private $pointRequest;

	/** @var PointService */
	private $pointService;


	/**
	 * DeleteRestoringPoint constructor.
	 *
	 * @param PointRequest $pointRequest
	 * @param PointService $pointService
	 */
	public function __construct(PointRequest $pointRequest, PointService $pointService) {
		parent::__construct();
		$this->pointRequest = $pointRequest;
		$this->pointService = $pointService;

		$this->setup('app', Application::APP_ID);
	}


	/**
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function execute(): void {
		/** @var RemoteInstance $signatory */
		$signatory = $this->getSignedRequest()->getSignatory();
		$pointId = $this->getSignedRequest()->getIncomingRequest()->getParam('pointId');

		try {
			$point = $this->pointRequest->getById($pointId, $signatory->getInstance());
			$this->pointService->delete($point);
		} catch (RestoringPointNotFoundException $e) {
		}
//		$this->setOutcome($this->serialize($point));
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
