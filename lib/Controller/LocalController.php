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

use ArtificialOwl\MySmallPhpTools\Exceptions\SignatureException;
use ArtificialOwl\MySmallPhpTools\Traits\Nextcloud\nc23\TNC23Controller;
use ArtificialOwl\MySmallPhpTools\Traits\Nextcloud\nc23\TNC23Deserialize;
use ArtificialOwl\MySmallPhpTools\Traits\Nextcloud\nc23\TNC23Logger;
use Exception;
use OC\AppFramework\Http;
use OC\Files\Node\File;
use OC\Files\Node\Folder;
use OC\User\NoUserException;
use OCA\Backup\Db\EventRequest;
use OCA\Backup\Exceptions\ExternalAppdataException;
use OCA\Backup\Exceptions\RestoringPointException;
use OCA\Backup\Exceptions\RestoringPointNotFoundException;
use OCA\Backup\Model\BackupEvent;
use OCA\Backup\Model\ExternalFolder;
use OCA\Backup\Model\RestoringPoint;
use OCA\Backup\Service\ConfigService;
use OCA\Backup\Service\CronService;
use OCA\Backup\Service\ExportService;
use OCA\Backup\Service\ExternalFolderService;
use OCA\Backup\Service\FilesService;
use OCA\Backup\Service\PointService;
use OCA\Backup\Service\RestoreService;
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

	/** @var PointService */
	private $pointService;

	/** @var FilesService */
	private $filesService;

	/** @var CronService */
	private $cronService;

	/** @var ExternalFolderService */
	private $externalFolderService;

	/** @var ExportService */
	private $exportService;

	/** @var RestoreService */
	private $restoreService;

	/** @var ConfigService */
	private $configService;


	/**
	 * LocalController constructor.
	 *
	 * @param string $appName
	 * @param IRequest $request
	 * @param IUserSession $userSession
	 * @param IRootFolder $rootFolder
	 * @param EventRequest $eventRequest
	 * @param PointService $pointService
	 * @param FilesService $filesService
	 * @param CronService $cronService
	 * @param ExternalFolderService $externalFolderService
	 * @param ExportService $exportService
	 * @param RestoreService $restoreService
	 * @param ConfigService $configService
	 */
	public function __construct(
		string $appName,
		IRequest $request,
		IUserSession $userSession,
		IRootFolder $rootFolder,
		EventRequest $eventRequest,
		PointService $pointService,
		FilesService $filesService,
		CronService $cronService,
		ExternalFolderService $externalFolderService,
		ExportService $exportService,
		RestoreService $restoreService,
		ConfigService $configService
	) {
		parent::__construct($appName, $request);

		$this->userSession = $userSession;
		$this->rootFolder = $rootFolder;
		$this->eventRequest = $eventRequest;
		$this->pointService = $pointService;
		$this->filesService = $filesService;
		$this->cronService = $cronService;
		$this->externalFolderService = $externalFolderService;
		$this->exportService = $exportService;
		$this->restoreService = $restoreService;
		$this->configService = $configService;
	}


	/**
	 * @param string $type
	 * @param string $param
	 *
	 * @return DataResponse
	 * @throws OCSException
	 */
	public function initAction(string $type, string $param = ''): DataResponse {
		switch ($type) {
			case 'scan':
				return $this->initActionScanLocalFolder((int)$param);

			case 'backup':
				if ($param === 'full') {
					return $this->initActionForceFullBackup();
				} elseif ($param === 'partial') {
					return $this->initActionForceDifferentialBackup();
				}
		}

		throw new OCSException('unknown action', Http::STATUS_BAD_REQUEST);
	}


	/**
	 * @return DataResponse
	 * @throws SignatureException
	 */
	public function getRestoringPoints(): DataResponse {
		$points = $this->pointService->getRPFromInstances();

		return new DataResponse($points);
	}


	/**
	 * @return DataResponse
	 */
	public function getSettings(): DataResponse {
		$settings = $this->configService->getSettings();
		$settings = array_merge($settings, $this->cronService->nextBackups());

		return new DataResponse($settings);
	}


	/**
	 * @param array $settings
	 *
	 * @return DataResponse
	 */
	public function setSettings(array $settings): DataResponse {
		$settings = $this->configService->setSettings($settings);

		// refresh mockup_date based on new settings
		if ($this->configService->getAppValueInt(ConfigService::MOCKUP_DATE) > 0) {
			$next = $this->cronService->nextBackups();
			if ($this->getInt('full', $next) > -1) {
				$this->configService->setAppValueInt(
					ConfigService::MOCKUP_DATE, $this->getInt('full', $next)
				);
			}
		}

		return new DataResponse(array_merge($settings, $this->cronService->nextBackups()));
	}


	/**
	 * @param string $encrypted
	 *
	 * @return DataResponse
	 * @throws OCSException
	 */
	public function setupExport(string $encrypted): DataResponse {
		try {
			$key = '';
			$content = $this->exportService->export(($encrypted === 'encrypted'), $key);

			return new DataResponse(
				[
					'key' => $key,
					'content' => $content
				]
			);
		} catch (Exception $e) {
			throw new OcsException($e->getMessage(), Http::STATUS_BAD_REQUEST);
		}
	}


	/**
	 * @return DataResponse
	 * @throws OCSException
	 */
	public function getAppData(): DataResponse {
		try {
			return new DataResponse($this->pointService->getExternalAppData());
		} catch (ExternalAppdataException $e) {
			return new DataResponse(new ExternalFolder());
		} catch (Exception $e) {
			throw new OcsException($e->getMessage(), Http::STATUS_BAD_REQUEST);
		}
	}


	/**
	 * @param int $storageId
	 * @param string $root
	 *
	 * @return DataResponse
	 * @throws OCSException
	 */
	public function setAppData(int $storageId, string $root): DataResponse {
		try {
			if ($root === '') {
				throw new OcsException('empty root');
			}

			$this->pointService->setExternalAppData($storageId, $root);

			return $this->getAppData();
		} catch (Exception $e) {
			throw new OcsException($e->getMessage(), Http::STATUS_BAD_REQUEST);
		}
	}


	/**
	 * @return DataResponse
	 * @throws OCSException
	 */
	public function getExternalFolder(): DataResponse {
		try {
			return new DataResponse($this->externalFolderService->getStorages());
		} catch (Exception $e) {
			throw new OcsException($e->getMessage(), Http::STATUS_BAD_REQUEST);
		}
	}


	/**
	 * @param int $storageId
	 * @param string $root
	 *
	 * @return DataResponse
	 * @throws OCSException
	 */
	public function setExternalFolder(int $storageId, string $root): DataResponse {
		try {
			if ($root === '') {
				throw new OcsException('empty root');
			}

			$storages = $this->externalFolderService->getStorages();
			foreach ($storages as $storage) {
				if ($storage->getStorageId() === $storageId) {
					if ($storage->getRoot() !== '') {
						throw new OcsException('storage is already configured');
					}
					$storage->setRoot($root);
					$this->externalFolderService->save($storage);

					return new DataResponse($storage);
				}
			}

			throw new OcsException('Unknown storage id');
		} catch (Exception $e) {
			throw new OcsException($e->getMessage(), Http::STATUS_BAD_REQUEST);
		}
	}


	/**
	 * @param int $storageId
	 *
	 * @return DataResponse
	 * @throws OCSException
	 */
	public function unsetExternalFolder(int $storageId): DataResponse {
		try {
			$this->externalFolderService->remove($storageId);

			return new DataResponse();
		} catch (Exception $e) {
			throw new OcsException($e->getMessage(), Http::STATUS_BAD_REQUEST);
		}
	}


	/**
	 * @param int $fileId
	 *
	 * @return DataResponse
	 * @throws OCSException
	 */
	private function initActionScanLocalFolder(int $fileId): DataResponse {
		$userId = $this->userSession->getUser()->getUID();

		try {
			$point = $this->filesService->getPointFromFileId($fileId, $userId);
			$event = new BackupEvent();
			$event->setAuthor($userId);
			$event->setData(['fileId' => $fileId]);
			$event->setType('ScanLocalFolder');

			$this->eventRequest->save($event);

			return new DataResponse(
				[
					'message' => 'The restoring point have been scheduled for a scan. (id: ' . $point->getId()
								 . ')'
				]
			);
		} catch (RestoringPointNotFoundException $e) {
			try {
				$point = $this->actionFromFileId($fileId, $userId);

				return new DataResponse(
					[
						'message' => 'The restoring point\'s metadata have been generated. (id: '
									 . $point->getId() . ')'
					]
				);
			} catch (RestoringPointException $e) {
			} catch (Exception $e) {
				throw new OcsException($e->getMessage(), Http::STATUS_BAD_REQUEST);
			}

			throw new OcsException(
				'file does not seems to be a valid restoring point',
				Http::STATUS_BAD_REQUEST
			);
		} catch (Exception $e) {
			throw new OcsException($e->getMessage(), Http::STATUS_BAD_REQUEST);
		}
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
				return $this->restoreService->restoreMetadataFromFolder($folder, $this->get('id', $content));
			}
		}

		throw new RestoringPointException();
	}


	/**
	 * @return DataResponse
	 * @throws OCSException
	 */
	private function initActionForceFullBackup(): DataResponse {
		$next = $this->cronService->nextBackups();
		if ($this->getInt('full', $next) === -1) {
			throw new OcsException('cannot emulate time for next backup');
		}
		$this->configService->setAppValueInt(ConfigService::MOCKUP_DATE, $this->getInt('full', $next));

		return new DataResponse(['message' => 'full backup should be initiated in the next few minutes']);
	}


	/**
	 * @return DataResponse
	 */
	private function initActionForceDifferentialBackup(): DataResponse {
		return new DataResponse(['message' => 'action partial backup not yet implemented)']);
	}
}
