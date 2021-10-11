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


namespace OCA\Backup\Controller;


use ArtificialOwl\MySmallPhpTools\Traits\Nextcloud\nc23\TNC23Controller;
use ArtificialOwl\MySmallPhpTools\Traits\Nextcloud\nc23\TNC23Deserialize;
use ArtificialOwl\MySmallPhpTools\Traits\Nextcloud\nc23\TNC23Logger;
use Exception;
use OC\AppFramework\Http;
use OCA\Backup\Db\EventRequest;
use OCA\Backup\Exceptions\RestoringPointNotFoundException;
use OCA\Backup\Model\BackupEvent;
use OCA\Backup\Service\ConfigService;
use OCA\Backup\Service\FilesService;
use OCA\Backup\Service\PointService;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSException;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;


/**
 * Class LocalController
 *
 * @package OCA\Backup\Controller
 */
class LocalController extends OcsController {


	use TNC23Controller;
	use TNC23Logger;
	use TNC23Deserialize;


	/** @var IUserSession */
	private $userSession;

	/** @var EventRequest */
	private $eventRequest;

	/** @var PointService */
	private $pointService;

	/** @var FilesService */
	private $filesService;

	/** @var ConfigService */
	private $configService;


	/**
	 * LocalController constructor.
	 *
	 * @param string $appName
	 * @param IRequest $request
	 * @param IUserSession $userSession
	 * @param EventRequest $eventRequest
	 * @param PointService $pointService
	 * @param FilesService $filesService
	 * @param ConfigService $configService
	 */
	public function __construct(
		string $appName,
		IRequest $request,
		IUserSession $userSession,
		EventRequest $eventRequest,
		PointService $pointService,
		FilesService $filesService,
		ConfigService $configService
	) {
		parent::__construct($appName, $request);

		$this->userSession = $userSession;
		$this->eventRequest = $eventRequest;
		$this->pointService = $pointService;
		$this->filesService = $filesService;
		$this->configService = $configService;
	}


	/**
	 * @param int $fileId
	 *
	 * @return DataResponse
	 * @throws OCSException
	 */
	public function scanLocalFolder(int $fileId): DataResponse {
		try {
			$point = $this->filesService->getPointFromFileId($fileId);
			$event = new BackupEvent();
			$event->setAuthor($this->userSession->getUser()->getUID());
			$event->setData(['fileId' => $fileId]);
			$event->setType('ScanLocalFolder');

			$this->eventRequest->save($event);
		} catch (RestoringPointNotFoundException $e) {
			throw new OcsException(
				'file does not seems to be a valid restoring point',
				Http::STATUS_BAD_REQUEST
			);
		} catch (Exception $e) {
			throw new OcsException($e->getMessage(), Http::STATUS_BAD_REQUEST);
		}

		return new DataResponse(
			['message' => 'The restoring point have been scheduled for a scan. (id: ' . $point->getId() . ')']
		);
	}


	/**
	 * @return DataResponse
	 */
	public function getRestoringPoint(): DataResponse {
		$points = $this->pointService->getLocalRestoringPoints();

		return new DataResponse($points);
	}

	/**
	 * @return DataResponse
	 */
	public function getSettings(): DataResponse {
		$settings = $this->configService->getSettings();

		return new DataResponse($settings);
	}


	/**
	 * @param array $settings
	 *
	 * @return DataResponse
	 */
	public function setSettings(array $settings): DataResponse {
		$settings = $this->configService->setSettings($settings);

		return new DataResponse($settings);
	}

}

