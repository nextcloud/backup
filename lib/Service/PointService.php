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
use ArtificialOwl\MySmallPhpTools\Traits\Nextcloud\nc23\TNC23Deserialize;
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
use OCA\Backup\Exceptions\ParentRestoringPointNotFoundException;
use OCA\Backup\Exceptions\RestoringChunkNotFoundException;
use OCA\Backup\Exceptions\RestoringPointNotFoundException;
use OCA\Backup\Exceptions\SqlDumpException;
use OCA\Backup\ISqlDump;
use OCA\Backup\Model\ChunkPartHealth;
use OCA\Backup\Model\RestoringChunk;
use OCA\Backup\Model\RestoringChunkPart;
use OCA\Backup\Model\RestoringData;
use OCA\Backup\Model\RestoringHealth;
use OCA\Backup\Model\RestoringPoint;
use OCA\Backup\SqlDump\SqlDumpMySQL;
use OCA\Backup\SqlDump\SqlDumpPgSQL;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Util;
use Throwable;


/**
 * Class PointService
 *
 * @package OCA\Backup\Service
 */
class PointService {


	use TNC23Signatory;
	use TNC23Logger;
	use TStringTools;
	use TNC23Deserialize;


	const NOBACKUP_FILE = '.nobackup';
	const SQL_DUMP_FILE = 'backup.sql';


	/** @var PointRequest */
	private $pointRequest;

	/** @var ChangesRequest */
	private $changesRequest;

	/** @var RemoteStreamService */
	private $remoteStreamService;

	/** @var ChunkService */
	private $chunkService;

	/** @var PackService */
	private $packService;

	/** @var MetadataService */
	private $metadataService;

	/** @var FilesService */
	private $filesService;

	/** @var OutputService */
	private $outputService;

	/** @var ActivityService */
	private $activityService;

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
	 * @param ChunkService $chunkService
	 * @param PackService $packService
	 * @param MetadataService $metadataService
	 * @param FilesService $filesService
	 * @param OutputService $outputService
	 * @param ActivityService $activityService
	 * @param ConfigService $configService
	 */
	public function __construct(
		PointRequest $pointRequest,
		ChangesRequest $changesRequest,
		RemoteStreamService $remoteStreamService,
		ChunkService $chunkService,
		PackService $packService,
		MetadataService $metadataService,
		FilesService $filesService,
		OutputService $outputService,
		ActivityService $activityService,
		ConfigService $configService
	) {
		$this->pointRequest = $pointRequest;
		$this->changesRequest = $changesRequest;
		$this->remoteStreamService = $remoteStreamService;
		$this->chunkService = $chunkService;
		$this->packService = $packService;
		$this->metadataService = $metadataService;
		$this->filesService = $filesService;
		$this->outputService = $outputService;
		$this->activityService = $activityService;
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
	 * @param int $until
	 *
	 * @return RestoringPoint[]
	 */
	public function getLocalRestoringPoints(int $since = 0, int $until = 0): array {
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
	 * @throws ArchiveCreateException
	 * @throws ArchiveNotFoundException
	 * @throws BackupAppCopyException
	 * @throws BackupScriptNotFoundException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws SqlDumpException
	 * @throws Throwable
	 */
	public function create(bool $complete): RestoringPoint {
		$point = $this->initRestoringPoint($complete);
		$this->chunkService->copyApp($point);
		$this->chunkService->generateInternalData($point);

		// maintenance mode on
		$initTime = time();
		$maintenance = $this->configService->getSystemValueBool(ConfigService::MAINTENANCE);
		$this->configService->maintenanceMode(true);

		try {
			$this->chunkService->createChunks($point);
			$this->backupSql($point);
		} catch (Throwable $t) {
			if (!$maintenance) {
				$this->configService->maintenanceMode();
			}
			throw $t;
		}

		if ($complete) {
			$this->changesRequest->reset();
			$this->configService->setAppValue(ConfigService::LAST_FULL_RP, $point->getId());
			$this->configService->setAppValueInt(ConfigService::DATE_FULL_RP, $point->getDate());
		} else {
			$this->configService->setAppValue(ConfigService::LAST_PARTIAL_RP, $point->getId());
			$this->configService->setAppValueInt(ConfigService::DATE_PARTIAL_RP, $point->getDate());
		}

		// maintenance mode off
		if (!$maintenance) {
			$this->configService->maintenanceMode();
		}

		$point->setDuration(time() - $initTime);
		$this->remoteStreamService->signPoint($point);
		$this->metadataService->saveMetadata($point);
		$this->pointRequest->save($point);

		$this->activityService->newActivity(
			'backup_create', [
							   'id' => $point->getId(),
							   'duration' => $point->getDuration(),
							   'status' => $point->getStatus(),
							   'complete' => $complete
						   ]
		);

		return $point;
	}


	/**
	 * @param RestoringPoint $point
	 * @param bool $updateMetadata
	 *
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws SignatoryException
	 */
	public function update(RestoringPoint $point, bool $updateMetadata = false): void {
		$this->initBaseFolder($point);
		if ($point->getInstance() === '') {
			$this->remoteStreamService->signPoint($point);
			$this->remoteStreamService->subSignPoint($point);
		}

		$this->pointRequest->update($point, $updateMetadata);
		if ($updateMetadata) {
			$this->metadataService->saveMetadata($point);
		}
	}


	/**
	 * update small complementary infos like Commend and Archive flag
	 *
	 * @param RestoringPoint $point
	 *
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws SignatoryException
	 */
	public function updateSubInfos(RestoringPoint $point): void {
		$this->initBaseFolder($point);
		$this->remoteStreamService->subSignPoint($point);

		try {
			$stored = $this->getLocalRestoringPoint($point->getId());
		} catch (RestoringPointNotFoundException $e) {
			return;
		}

		$this->initBaseFolder($stored);
		$stored->setComment($point->getComment())
			   ->setArchive($point->isArchive())
			   ->setSubSignature($point->getSubSignature());

		$this->pointRequest->update($stored, true);
		$this->metadataService->saveMetadata($stored);
	}


	/**
	 * @param RestoringPoint $point
	 *
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function delete(RestoringPoint $point): void {
		$this->pointRequest->deletePoint($point->getId());

		$this->initBackupFS();
		$this->initBaseFolder($point);

		$point->getBaseFolder()->delete();
	}


	/**
	 * @param bool $complete
	 *
	 * @return RestoringPoint
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws ParentRestoringPointNotFoundException
	 */
	private function initRestoringPoint(bool $complete): RestoringPoint {
		$this->initBackupFS();

		$point = new RestoringPoint();
		$point->setDate(time());
		$separator = ($complete) ? '-full-' : '-incremental-';
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
	 * @throws ParentRestoringPointNotFoundException
	 */
	private function addingRestoringData(RestoringPoint $point, bool $complete): void {
		if ($complete) {
			$point->addRestoringData(new RestoringData(RestoringData::ROOT_DATA, '', RestoringData::DATA));
		} else {
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
	 * @param RestoringPoint $point
	 *
	 * @throws ParentRestoringPointNotFoundException
	 */
	private function initParent(RestoringPoint $point): void {
		$parentId = $this->configService->getAppValue(ConfigService::LAST_FULL_RP);

		if ($parentId === '') {
			throw new ParentRestoringPointNotFoundException(
				'cannot create incremental. parent cannot be found'
			);
		}

		$point->setParent($parentId);
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
	}


	/**
	 * @param RestoringPoint $point
	 *
	 * @throws ArchiveCreateException
	 * @throws ArchiveNotFoundException
	 * @throws SqlDumpException
	 * @throws Throwable
	 */
	private function backupSql(RestoringPoint $point) {
		if ($this->configService->getSystemValue(ISqlDump::DB_TYPE) !== ISqlDump::MYSQL
			&& $this->configService->getSystemValue(ISqlDump::DB_TYPE) !== ISqlDump::PGSQL
		) {
			return;
		}

		$sqlDump = $this->getSqlDump();
		$tmp = $this->configService->getTempFileName();
		try {
			$sqlDump->export($this->getSqlData(), $tmp);
			$data = new RestoringData(RestoringData::SQL_DUMP, '', 'sqldump');
			$this->chunkService->createSingleFileChunk(
				$point,
				$data,
				self::SQL_DUMP_FILE,
				$tmp
			);
			unlink($tmp);
		} catch (Throwable $t) {
			unlink($tmp);
			throw $t;
		}

		$point->addRestoringData($data);
	}


	/**
	 * @return array
	 */
	public function getSqlData(): array {
		return [
			'dbname' => $this->configService->getSystemValue(ISqlDump::DB_NAME),
			'dbhost' => $this->configService->getSystemValue(ISqlDump::DB_HOST),
			'dbport' => $this->configService->getSystemValue(ISqlDump::DB_PORT),
			'dbuser' => $this->configService->getSystemValue(ISqlDump::DB_USER),
			'dbpassword' => $this->configService->getSystemValue(ISqlDump::DB_PASS)
		];
	}

	/**
	 * return temp file name/path
	 *
	 * @return ISqlDump
	 * @throws SqlDumpException
	 */
	public function getSqlDump(): ISqlDump {
		switch ($this->configService->getSystemValue(ISqlDump::DB_TYPE)) {
			case ISqlDump::MYSQL:
				$sqlDump = new SqlDumpMySQL();
				break;
			case ISqlDump::PGSQL:
				$sqlDump = new SqlDumpPgSQL();
				break;

			default:
				throw new SqlDumpException('unknown database type');
		}

		return $sqlDump;
	}


	public function loadSqlDump() {
		new SqlDumpMySQL();
		new SqlDumpPgSQL();
	}


	/**
	 * @param string $pointId
	 *
	 * @return RestoringPoint
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws RestoringPointNotFoundException
	 */
	public function generatePointFromBackupFS(string $pointId): RestoringPoint {
		$tmp = new RestoringPoint();
		$tmp->setId($pointId);
		$this->initBaseFolder($tmp);

		$folder = $tmp->getBaseFolder();

		try {
			$file = $folder->getFile(MetadataService::METADATA_FILE);
		} catch (NotFoundException $e) {
			throw new RestoringPointNotFoundException('could not find restoring point in appdata');
		}

		/** @var RestoringPoint $point */
		try {
			$point = $this->deserializeJson($file->getContent(), RestoringPoint::class);
		} catch (InvalidItemException $e) {
			throw new RestoringPointNotFoundException('invalid metadata');
		} catch (NotFoundException $e) {
			throw new RestoringPointNotFoundException('cannot access ' . MetadataService::METADATA_FILE);
		} catch (NotPermittedException $e) {
			throw new RestoringPointNotFoundException('cannot read ' . MetadataService::METADATA_FILE);
		}

		return $point;
	}


	/**
	 * @throws NotPermittedException
	 * @throws NotFoundException
	 */
	private function initBackupFS(bool $force = false): void {
		if ($this->backupFSInitiated) {
			return;
		}

		if (!class_exists(OC::class)) {
			return;
		}

		/** @var Factory $factory */
		$factory = OC::$server->get(Factory::class);
		$this->appData = $factory->get(Application::APP_ID);

		if ($force) {
			return;
		}

		$path = '/';
		$folder = $this->appData->getFolder($path);

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

		$point->setRootFolder($this->appData);
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
	 * @throws SignatoryException
	 */
	public function generateHealth(RestoringPoint $point, bool $updateDb = false): RestoringHealth {
		$this->initBackupFS();
		$this->initBaseFolder($point);

		$health = new RestoringHealth();
		$globalStatus = RestoringHealth::STATUS_OK;
		foreach ($point->getRestoringData() as $data) {
			foreach ($data->getChunks() as $chunk) {
				if ($point->isStatus(RestoringPoint::STATUS_PACKED)) {
					$this->generateHealthPacked($health, $point, $data, $chunk, $globalStatus);
					continue;
				}

				$chunkHealth = new ChunkPartHealth();

				$status = $this->generateChunkHealthStatus($point, $chunk);
				if ($status !== ChunkPartHealth::STATUS_OK) {
					$globalStatus = 0;
				}

				$chunkHealth->setDataName($data->getName())
							->setPartName($chunk->getName())
							->setStatus($status);
				$health->addPart($chunkHealth);
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
			$this->update($point, true);
		}

		return $health;
	}


	private function generateHealthPacked(
		RestoringHealth $health,
		RestoringPoint $point,
		RestoringData $data,
		RestoringChunk $chunk,
		int &$globalStatus
	): void {

		foreach ($chunk->getParts() as $part) {
			$partHealth = new ChunkPartHealth(true);
			$status = $this->generatePartHealthStatus($point, $chunk, $part);
			if ($status !== ChunkPartHealth::STATUS_OK) {
				$globalStatus = 0;
			}

			$partHealth->setDataName($data->getName())
					   ->setChunkName($chunk->getName())
					   ->setPartName($part->getName())
					   ->setStatus($status);
			$health->addPart($partHealth);
		}
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
				return ChunkPartHealth::STATUS_CHECKSUM;
			}

			return ChunkPartHealth::STATUS_OK;
		} catch (ArchiveNotFoundException $e) {
			return ChunkPartHealth::STATUS_MISSING;
		}
	}


	/**
	 * @param RestoringPoint $point
	 * @param RestoringChunk $chunk
	 * @param RestoringChunkPart $part
	 *
	 * @return int
	 */
	private function generatePartHealthStatus(
		RestoringPoint $point,
		RestoringChunk $chunk,
		RestoringChunkPart $part
	): int {
		try {
			$checksum = $this->packService->getChecksum($point, $chunk, $part);
			if ($checksum !== $part->getCurrentChecksum()) {
				return ChunkPartHealth::STATUS_CHECKSUM;
			}

			return ChunkPartHealth::STATUS_OK;
		} catch (ArchiveNotFoundException $e) {
			return ChunkPartHealth::STATUS_MISSING;
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
	 * @throws RestoringChunkNotFoundException
	 */
	public function getChunkContent(
		RestoringPoint $point, string $data, string $chunk
	): RestoringChunk {
		$this->initBaseFolder($point);

		$restoringChunk = clone $this->chunkService->getChunkFromRP($point, $chunk, $data);
		$this->chunkService->getChunkContent($point, $restoringChunk);

		return $restoringChunk;
	}


	/**
	 * @param RestoringPoint $point
	 *
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function saveMetadata(RestoringPoint $point): void {
		$this->initBaseFolder($point);
		$this->metadataService->saveMetadata($point);
	}

}
