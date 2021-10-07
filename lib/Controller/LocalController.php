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


use ArtificialOwl\MySmallPhpTools\Exceptions\InvalidItemException;
use ArtificialOwl\MySmallPhpTools\Traits\Nextcloud\nc23\TNC23Controller;
use ArtificialOwl\MySmallPhpTools\Traits\Nextcloud\nc23\TNC23Deserialize;
use ArtificialOwl\MySmallPhpTools\Traits\Nextcloud\nc23\TNC23Logger;
use Exception;
use OC\AppFramework\Http;
use OC\Files\Node\File;
use OCA\Backup\Db\EventRequest;
use OCA\Backup\Exceptions\RestoringPointNotFoundException;
use OCA\Backup\Model\BackupEvent;
use OCA\Backup\Model\RestoringPoint;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSException;
use OCP\AppFramework\OCSController;
use OCP\Files\FileInfo;
use OCP\Files\IRootFolder;
use OCP\Files\NotPermittedException;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\Lock\LockedException;


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

	/** @var IRootFolder */
	private $rootFolder;

	/** @var EventRequest */
	private $eventRequest;


	/**
	 * LocalController constructor.
	 *
	 * @param string $appName
	 * @param IRequest $request
	 * @param IUserSession $userSession
	 * @param IRootFolder $rootFolder
	 * @param EventRequest $eventRequest
	 */
	public function __construct(
		string $appName,
		IRequest $request,
		IUserSession $userSession,
		IRootFolder $rootFolder,
		EventRequest $eventRequest
	) {
		parent::__construct($appName, $request);

		$this->userSession = $userSession;
		$this->rootFolder = $rootFolder;
		$this->eventRequest = $eventRequest;
	}


	/**
	 * @param int $fileId
	 *
	 * @return DataResponse
	 * @throws OCSException
	 */
	public function scanLocalFolder(int $fileId): DataResponse {
		try {
			$point = $this->getPointFromFileId($fileId);
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

		return new DataResponse(['message' => 'The restoring point have been scheduled for a scan.']);
	}

	/**
	 * @param int $fileId
	 *
	 * @return RestoringPoint
	 * @throws RestoringPointNotFoundException
	 */
	private function getPointFromFileId(int $fileId): RestoringPoint {
		$nodes = $this->rootFolder->getById($fileId);
		foreach ($nodes as $node) {
			if ($node->getType() !== FileInfo::TYPE_FILE) {
				continue;
			}

			/** @var File $node */
			/** @var RestoringPoint $point */
			try {
				$point = $this->deserializeJson($node->getContent(), RestoringPoint::class);

				return $point;
			} catch (InvalidItemException
			| NotPermittedException
			| LockedException $e) {
				continue;
			}

		}

		throw new RestoringPointNotFoundException();
	}

}

