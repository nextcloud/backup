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


use ArtificialOwl\MySmallPhpTools\Traits\Nextcloud\nc23\TNC23Logger;
use ArtificialOwl\MySmallPhpTools\Traits\Nextcloud\nc23\TNC23Signatory;
use ArtificialOwl\MySmallPhpTools\Traits\TStringTools;
use OC;
use OC\Files\AppData\Factory;
use OCA\Backup\AppInfo\Application;
use OCA\Backup\Db\ChangesRequest;
use OCA\Backup\Db\PointRequest;
use OCA\Backup\Exceptions\ArchiveCreateException;
use OCA\Backup\Exceptions\ArchiveNotFoundException;
use OCA\Backup\Exceptions\BackupAppCopyException;
use OCA\Backup\Exceptions\BackupScriptNotFoundException;
use OCA\Backup\Exceptions\ChunkNotFoundException;
use OCA\Backup\Exceptions\RestoringPointException;
use OCA\Backup\Exceptions\RestoringPointNotFoundException;
use OCA\Backup\Exceptions\SqlDumpException;
use OCA\Backup\Model\RestoringChunk;
use OCA\Backup\Model\RestoringChunkHealth;
use OCA\Backup\Model\RestoringData;
use OCA\Backup\Model\RestoringHealth;
use OCA\Backup\Model\RestoringPoint;
use OCA\Backup\SqlDump\SqlDumpMySQL;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Util;


/**
 * Class PointService
 *
 * @package OCA\Backup\Service
 */
class PointService {


	use TNC23Signatory;
	use TNC23Logger;
	use TStringTools;


	const NOBACKUP_FILE = '.nobackup';
	const METADATA_FILE = 'metadata.json';
	const SQL_DUMP_FILE = 'backup_sql';


	/** @var PointRequest */
	private $pointRequest;

	/** @var ChangesRequest */
	private $changesRequest;

	/** @var RemoteStreamService */
	private $remoteStreamService;

	/** @var ArchiveService */
	private $chunkService;

	/** @var FilesService */
	private $filesService;

	/** @var OutputService */
	private $outputService;

	/** @var ConfigService */
	private $configService;

	/** @var IAppData */
	private $appData;

	/** @var bool */
	private $backupFSInitiated = false;


	/**
	 * PointService constructor.
	 *
	 * @param PointRequest $pointRequest
	 * @param ChangesRequest $changesRequest
	 * @param RemoteStreamService $remoteStreamService
	 * @param ArchiveService $chunkService
	 * @param FilesService $fileService
	 * @param ConfigService $configService
	 */
	public function __construct(
		PointRequest $pointRequest,
		ChangesRequest $changesRequest,
		RemoteStreamService $remoteStreamService,
		ArchiveService $chunkService,
		FilesService $filesService,
		OutputService $outputService,
		ConfigService $configService
	) {
		$this->pointRequest = $pointRequest;
		$this->changesRequest = $changesRequest;
		$this->chunkService = $chunkService;
		$this->remoteStreamService = $remoteStreamService;
		$this->filesService = $filesService;
		$this->outputService = $outputService;
		$this->configService = $configService;

		$this->setup('app', 'backup');
	}


	/**
	 * If $instance is empty, will returns RestoringPoint without checking the origin.
	 * Use getLocalRestoringPoint() to limit the search to local RestoringPoint
	 *
	 * @param string $pointId
	 * @param string $instance
	 *
	 * @return RestoringPoint
	 * @throws RestoringPointNotFoundException
	 */
	public function getRestoringPoint(string $pointId, string $instance = ''): RestoringPoint {
		return $this->pointRequest->getById($pointId, $instance);
	}


	/**
	 * @param string $pointId
	 *
	 * @return RestoringPoint
	 * @throws RestoringPointNotFoundException
	 */
	public function getLocalRestoringPoint(string $pointId): RestoringPoint {
		return $this->pointRequest->getLocalById($pointId);
	}


	/**
	 * @param int $since
	 *
	 * @return RestoringPoint[]
	 */
	public function getRPLocal(int $since = 0, int $until = 0): array {
		return $this->pointRequest->getLocal($since, $until);
	}


	/**
	 * @param string $instance
	 *
	 * @return RestoringPoint[]
	 */
	public function getRPByInstance(string $instance): array {
		return $this->pointRequest->getByInstance($instance);
	}


	/**
	 * @param bool $complete
	 *
	 * @return RestoringPoint
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws ArchiveCreateException
	 * @throws ArchiveNotFoundException
	 * @throws BackupAppCopyException
	 * @throws BackupScriptNotFoundException
	 * @throws SqlDumpException
	 * @throws RestoringPointException
	 */
	public function create(bool $complete): RestoringPoint {
		$point = $this->initRestoringPoint($complete);
		$this->chunkService->copyApp($point);
		$this->chunkService->generateInternalData($point);

		$this->chunkService->createChunks($point);

		$this->backupSql($point);
		$this->saveMetadata($point);

		$this->changesRequest->reset();
		$this->pointRequest->save($point);

		return $point;
	}


	/**
	 * @param bool $complete
	 *
	 * @return RestoringPoint
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	private function initRestoringPoint(bool $complete): RestoringPoint {
		$this->initBackupFS();

		$point = new RestoringPoint();
		$point->setDate(time());
		$separator = ($complete) ? '-' : '_';
		$point->setId(date('YmdHis', $point->getDate()) . $separator . $this->token());
		$point->setNC(Util::getVersion());

		$this->initBaseFolder($point);
		$this->addingRestoringData($point, $complete);

		return $point;
	}


	/**
	 * @param RestoringPoint $point
	 * @param bool $complete
	 *
	 * @throws RestoringPointNotFoundException
	 */
	private function addingRestoringData(RestoringPoint $point, bool $complete): void {
		if ($complete) {
			$point->addRestoringData(new RestoringData(RestoringData::ROOT_DATA, '', RestoringData::DATA));
		} else {
			// TODO: might be interesting to store the ID of the last parent somewhere, in case the parent have been uploaded
			$this->initParent($point);
			$this->addIncrementalData($point);
		}

		$point->addRestoringData(
			new RestoringData(
				RestoringData::ROOT_NEXTCLOUD,
				'apps/',
				RestoringData::APPS
			)
		);
		$point->addRestoringData(new RestoringData(RestoringData::FILE_CONFIG, '', RestoringData::CONFIG));

		$this->addCustomApps($point);
	}


	/**
	 * @throws RestoringPointNotFoundException
	 */
	private function initParent(RestoringPoint $point): void {
		$parent = $this->pointRequest->getLastFullRP();
		$point->setParent($parent->getId());
	}


	/**
	 * @param RestoringPoint $point
	 */
	private function addCustomApps(RestoringPoint $point) {
		$customApps = $this->configService->getSystemValue('apps_paths');
		if (!is_array($customApps)) {
			return;
		}

		foreach ($customApps as $app) {
			if (!is_array($app) || !array_key_exists('path', $app)) {
				continue;
			}

			$name = 'apps_' . $this->uuid(8);
			$point->addRestoringData(new RestoringData(RestoringData::ROOT_DISK, $app['path'], $name));
		}
	}


	/**
	 * @param RestoringPoint $point
	 */
	private function addIncrementalData(RestoringPoint $point): void {
		$changedFiles = $this->changesRequest->getAll();

		$data = new RestoringData(RestoringData::ROOT_DATA, '', RestoringData::DATA);
		foreach ($changedFiles as $file) {
			$data->addFile(ltrim($file->getPath(), '/'));
		}

		$data->setLocked(true);
		$point->addRestoringData($data);

		// TODO:
		// - get last complete RestoringPoint
		// - generate metadata
		// - get list of user files to add to the backup
		// - get list of non-user files to add to the backup
	}


	/**
	 * @param RestoringPoint $point
	 *
	 * @throws ArchiveCreateException
	 * @throws ArchiveNotFoundException
	 * @throws SqlDumpException
	 */
	private function backupSql(RestoringPoint $point) {
		$content = $this->generateSqlDump();

		$data = new RestoringData(RestoringData::SQL_DUMP, '', 'sqldump');
		$this->chunkService->createContentChunk(
			$point,
			$data,
			self::SQL_DUMP_FILE,
			$content
		);

		$point->addRestoringData($data);
	}


	/**
	 * @return string
	 * @throws SqlDumpException
	 */
	private function generateSqlDump(): string {
		$data = [
			'dbname' => $this->configService->getSystemValue('dbname'),
			'dbhost' => $this->configService->getSystemValue('dbhost'),
			'dbport' => $this->configService->getSystemValue('dbport'),
			'dbuser' => $this->configService->getSystemValue('dbuser'),
			'dbpassword' => $this->configService->getSystemValue('dbpassword')
		];

		$sqlDump = new SqlDumpMySQL();

		return $sqlDump->export($data);
	}


	/**
	 * @param RestoringPoint $point
	 *
	 * @throws NotPermittedException
	 * @throws NotFoundException
	 */
	public function saveMetadata(RestoringPoint $point) {
		$this->initBaseFolder($point);

		$folder = $point->getBaseFolder();

		try {
			$file = $folder->getFile(self::METADATA_FILE);
		} catch (NotFoundException $e) {
			$file = $folder->newFile(self::METADATA_FILE);
		}

		$file->putContent(json_encode($point, JSON_PRETTY_PRINT));
	}


	/**
	 * @throws NotPermittedException
	 * @throws NotFoundException
	 */
	private function initBackupFS(bool $force = false): void {
		if ($this->backupFSInitiated) {
			return;
		}

		$path = '/';

		if (!class_exists(OC::class)) {
			return;
		}

		/** @var Factory $factory */
		$factory = OC::$server->get(Factory::class);
		$this->appData = $factory->get(Application::APP_ID);

		if ($force) {
			return;
		}

		try {
			$folder = $this->appData->getFolder($path);
		} catch (NotFoundException $e) {
			$folder = $this->appData->newFolder($path);
		}

		$temp = $folder->newFile(self::NOBACKUP_FILE);
		$temp->putContent('');

		$this->backupFSInitiated = true;
	}


	/**
	 * This will destroy all backup stored locally
	 * (from this instance and from remote instance using this instance as storage)
	 *
	 * This method is only called when the app is reset/uninstall using ./occ backup:reset
	 *
	 * @throws NotPermittedException
	 * @throws NotFoundException
	 */
	public function destroyBackupFS(): void {
		$this->initBackupFS(true);
		try {
			$folder = $this->appData->getFolder('/');
			$folder->delete();
		} catch (NotFoundException $e) {
		}
	}


	/**
	 * @param RestoringPoint $point
	 *
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function initBaseFolder(RestoringPoint $point): void {
		if ($point->hasBaseFolder()) {
			return;
		}

		$this->initBackupFS();

		try {
			$folder = $this->appData->newFolder('/' . $point->getId());
		} catch (NotPermittedException $e) {
			$folder = $this->appData->getFolder('/' . $point->getId());
		}

		$point->setBaseFolder($folder);
	}


	/**
	 * Update $point with it, but also returns the generated RestoringHealth
	 *
	 * @param RestoringPoint $point
	 * @param bool $updateDb
	 *
	 * @return RestoringHealth
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function generateHealth(RestoringPoint $point, bool $updateDb = false): RestoringHealth {
		$this->initBackupFS();
		$this->initBaseFolder($point);

		$health = new RestoringHealth();
		$globalStatus = RestoringHealth::STATUS_OK;
		foreach ($point->getRestoringData() as $restoringData) {
			foreach ($restoringData->getChunks() as $chunk) {
				$chunkHealth = new RestoringChunkHealth();

				$status = $this->generateChunkHealthStatus($point, $chunk);
				if ($status !== RestoringChunkHealth::STATUS_OK) {
					$globalStatus = 0;
				}

				$chunkHealth->setDataName($restoringData->getName())
							->setChunkName($chunk->getName())
							->setStatus($status);
				$health->addChunk($chunkHealth);
			}
		}

		if ($globalStatus === RestoringHealth::STATUS_OK && $point->getParent() !== '') {
			try {
				$this->pointRequest->getById($point->getParent());
			} catch (RestoringPointNotFoundException $e) {
				$globalStatus = RestoringHealth::STATUS_ORPHAN;
			}
		}

		$health->setStatus($globalStatus);
		$point->setHealth($health);

		if ($updateDb) {
			$this->pointRequest->update($point);
		}

		return $health;
	}


	/**
	 * @param RestoringPoint $point
	 * @param RestoringChunk $chunk
	 *
	 * @return int
	 */
	private function generateChunkHealthStatus(RestoringPoint $point, RestoringChunk $chunk): int {
		try {
			$checksum = $this->chunkService->getChecksum($point, $chunk);
			if ($checksum !== $chunk->getChecksum()) {
				return RestoringChunkHealth::STATUS_CHECKSUM;
			}

			return RestoringChunkHealth::STATUS_OK;
		} catch (ArchiveNotFoundException $e) {
			return RestoringChunkHealth::STATUS_MISSING;
		}
	}


	/**
	 * @param RestoringPoint $point
	 * @param string $data
	 * @param string $chunk
	 *
	 * @return RestoringChunk
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws ChunkNotFoundException
	 */
	public function getChunkContent(RestoringPoint $point, string $data, string $chunk): RestoringChunk {
		$this->initBaseFolder($point);

		$restoringChunk = clone $this->chunkService->getChunkFromRP($point, $chunk, $data);
		$this->chunkService->getChunkContent($point, $restoringChunk);

		return $restoringChunk;
	}


	/**
	 * @param RestoringPoint $point
	 * @param string $data
	 * @param string $chunk
	 *
	 * @return ISimpleFile
	 * @throws ChunkNotFoundException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function getChunkResource(RestoringPoint $point, string $data, string $chunk): ISimpleFile {
		$this->initBaseFolder($point);

		$restoringChunk = clone $this->chunkService->getChunkFromRP($point, $chunk, $data);

		return $this->chunkService->getChunkResource($point, $restoringChunk);
	}


	/**
	 * @param string $output
	 */
	private function o(string $output): void {
		$this->outputService->o($output);
	}
}
