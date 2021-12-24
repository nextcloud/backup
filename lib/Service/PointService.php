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
use ArtificialOwl\MySmallPhpTools\Exceptions\SignatureException;
use ArtificialOwl\MySmallPhpTools\Traits\Nextcloud\nc23\TNC23Deserialize;
use ArtificialOwl\MySmallPhpTools\Traits\Nextcloud\nc23\TNC23Logger;
use ArtificialOwl\MySmallPhpTools\Traits\Nextcloud\nc23\TNC23Signatory;
use ArtificialOwl\MySmallPhpTools\Traits\TStringTools;
use Exception;
use OC;
use OC\Files\AppData\Factory;
use OCA\Backup\AppInfo\Application;
use OCA\Backup\Db\ChangesRequest;
use OCA\Backup\Db\PointRequest;
use OCA\Backup\Exceptions\ArchiveCreateException;
use OCA\Backup\Exceptions\ArchiveNotFoundException;
use OCA\Backup\Exceptions\BackupAppCopyException;
use OCA\Backup\Exceptions\BackupScriptNotFoundException;
use OCA\Backup\Exceptions\ExternalAppdataException;
use OCA\Backup\Exceptions\ExternalFolderNotFoundException;
use OCA\Backup\Exceptions\ParentRestoringPointNotFoundException;
use OCA\Backup\Exceptions\RemoteInstanceException;
use OCA\Backup\Exceptions\RemoteInstanceNotFoundException;
use OCA\Backup\Exceptions\RemoteResourceNotFoundException;
use OCA\Backup\Exceptions\RestoringChunkNotFoundException;
use OCA\Backup\Exceptions\RestoringPointNotFoundException;
use OCA\Backup\Exceptions\SqlDumpException;
use OCA\Backup\ISqlDump;
use OCA\Backup\Model\ChunkPartHealth;
use OCA\Backup\Model\ExternalFolder;
use OCA\Backup\Model\RemoteInstance;
use OCA\Backup\Model\RestoringChunk;
use OCA\Backup\Model\RestoringChunkPart;
use OCA\Backup\Model\RestoringData;
use OCA\Backup\Model\RestoringHealth;
use OCA\Backup\Model\RestoringPoint;
use OCA\Backup\SqlDump\SqlDumpMySQL;
use OCA\Backup\SqlDump\SqlDumpPgSQL;
use OCA\Backup\Wrappers\AppDataRootWrapper;
use OCA\Files_External\Lib\InsufficientDataForMeaningfulAnswerException;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Files\StorageNotAvailableException;
use OCP\Lock\LockedException;
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


	public const NOBACKUP_FILE = '.nobackup';
	public const SQL_DUMP_FILE = 'backup.sql';


	/** @var PointRequest */
	private $pointRequest;

	/** @var ChangesRequest */
	private $changesRequest;

	/** @var RemoteService */
	private $remoteService;

	/** @var RemoteStreamService */
	private $remoteStreamService;

	/** @var ExternalFolderService */
	private $externalFolderService;

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

	/** @var AppDataRootWrapper */
	private $appDataRoot;

	/** @var bool */
	private $backupFSInitiated = false;


	/**
	 * PointService constructor.
	 *
	 * @param PointRequest $pointRequest
	 * @param ChangesRequest $changesRequest
	 * @param RemoteService $remoteService
	 * @param RemoteStreamService $remoteStreamService
	 * @param ExternalFolderService $externalFolderService
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
		RemoteService $remoteService,
		RemoteStreamService $remoteStreamService,
		ExternalFolderService $externalFolderService,
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
		$this->remoteService = $remoteService;
		$this->remoteStreamService = $remoteStreamService;
		$this->externalFolderService = $externalFolderService;
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
	 * @param bool $asc
	 *
	 * @return RestoringPoint[]
	 */
	public function getLocalRestoringPoints(int $since = 0, int $until = 0, bool $asc = true): array {
		return $this->pointRequest->getLocal($since, $until, $asc);
	}


	/**
	 * @param bool $complete
	 * @param string $comment
	 * @param string $log - description used during the opening session; if empty, no logs are generated
	 *
	 * @return RestoringPoint
	 * @throws ArchiveCreateException
	 * @throws ArchiveNotFoundException
	 * @throws BackupAppCopyException
	 * @throws BackupScriptNotFoundException
	 * @throws ExternalFolderNotFoundException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws ParentRestoringPointNotFoundException
	 * @throws SignatoryException
	 * @throws SqlDumpException
	 * @throws Throwable
	 * @throws LockedException
	 */
	public function create(bool $complete, string $comment = '', string $log = ''): RestoringPoint {
		// maintenance mode on
		$initTime = time();
		$maintenance = $this->configService->getSystemValueBool(ConfigService::MAINTENANCE);
		$this->o('> maintenance mode: <info>on</info>');
		$this->configService->maintenanceMode(true);

		try {
			$point = $this->initRestoringPoint($complete);
			$point->setComment($comment);

			// because we had no $point before, the log from crontab will be initiated here
			if ($log !== '') {
				$this->outputService->openFile($point, $log);
			}
			$this->chunkService->createChunks($point);
			$this->backupSql($point);
		} catch (Throwable $t) {
			if (!$maintenance) {
				$this->o('> maintenance mode: <info>off</info>');
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
			$this->o('> maintenance mode: <info>off</info>');
			$this->configService->maintenanceMode();
		}

		$point->setDuration(time() - $initTime);
		$this->o('> maintenance mode was active for ' . $this->getDateDiff($point->getDuration()));

		$this->o('> signing and storing metadata');
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
	 * @throws ExternalFolderNotFoundException
	 */
	public function delete(RestoringPoint $point): void {
		$this->initBackupFS();
		$this->initBaseFolder($point);

		$point->getBaseFolder()->delete();
		$this->pointRequest->deletePoint($point->getId());
	}


	/**
	 * @param bool $complete
	 *
	 * @return RestoringPoint
	 * @throws ArchiveNotFoundException
	 * @throws BackupAppCopyException
	 * @throws BackupScriptNotFoundException
	 * @throws ExternalFolderNotFoundException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws ParentRestoringPointNotFoundException
	 */
	private function initRestoringPoint(bool $complete): RestoringPoint {
		$this->o('> initialization of the AppData');
		$this->initBackupFS();

		$this->o('> initialization of the RestoringPoint: ', false);
		$point = new RestoringPoint();
		$point->setDate(time());
		$separator = ($complete) ? '-full-' : '-differential-';
		$point->setId(date('YmdHis', $point->getDate()) . $separator . $this->token());
		$point->setNC(Util::getVersion());

		$this->o('<info>' . $point->getId() . '</info> based on NC' . $point->getNCVersion());

		$this->o('> initialization of the storage');
		$this->initBaseFolder($point);

		$this->o('> preparation of the data to be stored in the restoring point');
		$this->addingRestoringData($point, $complete);

		$this->o('> preparation of internal data');
		$this->chunkService->copyApp($point);
		$this->chunkService->generateInternalData($point);

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
			$this->addDifferentialData($point);
		}

		$point->addRestoringData(
			new RestoringData(
				RestoringData::ROOT_NEXTCLOUD,
				'',
				RestoringData::NEXTCLOUD
			)
		);

		$point->addRestoringData(
			new RestoringData(
				RestoringData::ROOT_APPS,
				'',
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
				'cannot create differential. parent cannot be found'
			);
		}

		$point->setParent($parentId);
	}


	/**
	 * @param RestoringPoint $point
	 */
	private function addCustomApps(RestoringPoint $point): void {
		$customApps = $this->configService->getSystemValueArray('apps_paths');
		if (!is_array($customApps)) {
			return;
		}

		foreach ($customApps as $app) {
			if (!is_array($app) || !array_key_exists('path', $app)) {
				continue;
			}

			$customUrl = str_replace('/', '', $this->get('url', $app));
			if ($this->get('path', $app) === OC::$SERVERROOT . '/apps' && $customUrl === 'apps') {
				continue;
			}

			$name = 'apps-' . $customUrl . '-' . $this->uuid(8);
			$path = ltrim($this->get('path', $app), '/');
			$point->addRestoringData(new RestoringData(RestoringData::ROOT_DISK, $path, $name));
		}
	}


	/**
	 * @param RestoringPoint $point
	 */
	private function addDifferentialData(RestoringPoint $point): void {
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

		$this->o('  * <info>SqlDump</info>: ', false);
		$sqlDump = $this->getSqlDump();
		$this->o(get_class($sqlDump));
		$tmp = $this->configService->getTempFileName();
		try {
			$this->o('    - exporting sql to <info>' . $tmp . '</info>');
			$sqlDump->export($this->getSqlParams(), $tmp);
			$this->o('    - generating single file chunk');
			$data = new RestoringData(RestoringData::FILE_SQL_DUMP, '', RestoringData::SQL_DUMP);
			$this->chunkService->createSingleFileChunk(
				$point,
				$data,
				self::SQL_DUMP_FILE,
				$tmp,
				$this->configService->getSystemValue(ISqlDump::DB_TYPE)
			);
			unlink($tmp);
		} catch (Throwable $t) {
			unlink($tmp);
			throw $t;
		}

		$point->addRestoringData($data);
	}


	/** // TODO: add a way to get sql params from current config/config.php directly
	 *
	 * @return array
	 */
	public function getSqlParams(): array {
		$host = $this->configService->getSystemValue(ISqlDump::DB_HOST);
		$port = $this->configService->getSystemValue(ISqlDump::DB_PORT);

		if (str_contains($host, ':')) {
			[$host, $port] = explode(':', $host);
		}

		return [
			ISqlDump::DB_TYPE => $this->configService->getSystemValue(ISqlDump::DB_TYPE),
			ISqlDump::DB_NAME => $this->configService->getSystemValue(ISqlDump::DB_NAME),
			ISqlDump::DB_HOST => $host,
			ISqlDump::DB_PORT => $port,
			ISqlDump::DB_USER => $this->configService->getSystemValue(ISqlDump::DB_USER),
			ISqlDump::DB_PASS => $this->configService->getSystemValue(ISqlDump::DB_PASS)
		];
	}

	/**
	 * return temp file name/path
	 *
	 * @param array $params
	 *
	 * @return ISqlDump
	 * @throws SqlDumpException
	 */
	public function getSqlDump(array $params = []): ISqlDump {
		if (empty($params)) {
			$dbType = $this->configService->getSystemValue(ISqlDump::DB_TYPE);
		} else {
			$dbType = $this->get(ISqlDump::DB_TYPE, $params);
		}
		switch ($dbType) {
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


	public function generatePointFromFolder(int $fileId, string $owner): RestoringPoint {
		$point = $this->filesService->getPointFromFileId($fileId, $owner, $folder);
		$this->initBaseFolder($point);

		$this->metadataService->saveMetadata($point);

		foreach ($point->getRestoringData() as $data) {
			foreach ($data->getChunks() as $chunk) {
				$path = $sub = '';
				$dest = $this->packService->getPackFolder($point, $chunk, $path, $sub);
				$orig = $this->filesService->getPackFolder($folder, $sub);
				if ($chunk->hasParts()) {
					foreach ($chunk->getParts() as $part) {
						$this->filesService->copyFileToAppData($orig, $dest, $part->getName());
					}
				} else {
					$this->filesService->copyFileToAppData($orig, $dest, $chunk->getFilename());
				}
			}
		}

		$this->generateHealth($point);
		$this->pointRequest->save($point);
		$this->saveMetadata($point);

		return $point;
	}


	/**
	 * @return RestoringPoint[]
	 * @throws ExternalFolderNotFoundException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function scanFoldersFromAppData(): array {
		$this->initBackupFS();

		foreach ($this->appDataRoot->getFolders() as $pointId) {
			try {
				$this->generatePointFromAppData($pointId);
			} catch (Exception $e) {
			}
		}

		return [];
	}


	/**
	 * @param string $pointId
	 *
	 * @return RestoringPoint
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws RestoringPointNotFoundException
	 * @throws SignatoryException
	 */
	public function generatePointFromAppData(string $pointId): RestoringPoint {
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

		$this->generateHealth($point);
		$this->pointRequest->save($point);

		return $point;
	}


	/**
	 * @return ExternalFolder
	 * @throws ExternalAppdataException
	 * @throws ExternalFolderNotFoundException
	 */
	public function getExternalAppData(): ExternalFolder {
		$externalAppdata = $this->configService->getAppValueArray(ConfigService::EXTERNAL_APPDATA);

		if (empty($externalAppdata)) {
			throw new ExternalAppdataException();
		}

		$external = new ExternalFolder();
		try {
			$external->import($externalAppdata);
		} catch (InvalidItemException $e) {
			throw new ExternalAppdataException('invalid ExternalFolder');
		}

		$this->externalFolderService->initRootFolder($external);

		return $external;
	}


	/**
	 * @param int $storageId
	 * @param string $root
	 *
	 * @throws ExternalFolderNotFoundException
	 * @throws InsufficientDataForMeaningfulAnswerException
	 * @throws StorageNotAvailableException
	 */
	public function setExternalAppData(int $storageId, string $root = ''): void {
		if ($storageId === 0) {
			try {
				$this->destroyBackupFS();
			} catch (ExternalFolderNotFoundException | NotFoundException | NotPermittedException $e) {
				$this->deleteAllPoints();
			}

			$this->configService->unsetAppValue(ConfigService::EXTERNAL_APPDATA);

			return;
		}

		if ($root === '') {
			throw new ExternalFolderNotFoundException('empty root');
		}

		$external = $this->externalFolderService->getStorageById($storageId);
		$external->setRoot($root);

		try {
			$this->destroyBackupFS();
		} catch (ExternalFolderNotFoundException | NotFoundException | NotPermittedException $e) {
			$this->deleteAllPoints();
		}

		$this->configService->setAppValueArray(ConfigService::EXTERNAL_APPDATA, $this->serialize($external));
	}


	/**
	 * @throws NotPermittedException
	 * @throws NotFoundException
	 * @throws ExternalFolderNotFoundException
	 */
	private function initBackupFS(bool $force = false): void {
		if (!is_null($this->appDataRoot)) {
			return;
		}

		if (!class_exists(OC::class)) {
			return;
		}

		$this->appDataRoot = new AppDataRootWrapper();

		try {
			$externalAppData = $this->getExternalAppData();
			$this->appDataRoot->setExternalFolder($externalAppData);
		} catch (ExternalAppdataException $e) {
			/** @var Factory $factory */
			$factory = OC::$server->get(Factory::class);
			$this->appDataRoot->setSimpleRoot($factory->get(Application::APP_ID));
		}

		if ($force) {
			return;
		}

		$path = '/';

		try {
			// avoid strange behavior post-restoration
			$this->appDataRoot->newFolder($path);
		} catch (NotPermittedException $e) {
		}

		$folder = $this->appDataRoot->getFolder($path);
		$folder->newFile(self::NOBACKUP_FILE, '');

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
	 * @throws ExternalFolderNotFoundException
	 */
	public function destroyBackupFS(): void {
		$this->initBackupFS(true);
		try {
			$folder = $this->appDataRoot->getFolder('/');
			$folder->delete();
			$this->pointRequest->deleteAll();
		} catch (NotFoundException $e) {
		}
	}


	public function deleteAllPoints(): void {
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
	 * TODO: explode the method as some part of the process is external folder related...
	 *
	 * @param bool $local
	 * @param string $remote
	 * @param string $external
	 *
	 * @return array
	 * @throws SignatureException
	 */
	public function getRPFromInstances(
		bool $local = false,
		string $remote = '',
		string $external = ''
	): array {
		if ($local) {
			$instances = [RemoteInstance::LOCAL];
		} elseif ($remote !== '') {
			$instances = ['remote:' . $remote];
		} elseif ($external !== '') {
			$instances = ['external:' . $external];
		} else {
			$instances = array_merge(
				[RemoteInstance::LOCAL],
				array_map(
					function (RemoteInstance $remoteInstance): string {
						return 'remote:' . $remoteInstance->getInstance();
					}, $this->remoteService->getOutgoing()
				),
				array_map(
					function (ExternalFolder $externalFolder): string {
						return 'external:' . $externalFolder->getStorageId();
					}, $this->externalFolderService->getAll()
				)
			);
		}

		$points = $dates = [];
		foreach ($instances as $instance) {
			$this->o('- retreiving data from <info>' . $instance . '</info>');

			$list = [];
			try {
				if ($instance === RemoteInstance::LOCAL) {
					$list = $this->getLocalRestoringPoints();
				} else {
					[$source, $id] = explode(':', $instance, 2);
					if ($source === 'remote') {
						$list = $this->remoteService->getRestoringPoints($id);
					} elseif ($source === 'external') {
						try {
							$external = $this->externalFolderService->getByStorageId((int)$id);
							$list = $this->externalFolderService->getRestoringPoints($external);
						} catch (ExternalFolderNotFoundException $e) {
						}
					}
				}
			} catch (RemoteInstanceException
			| RemoteInstanceNotFoundException
			| RemoteResourceNotFoundException $e) {
				continue;
			}

			foreach ($list as $item) {
				$this->o(' > found RestoringPoint <info>' . $item->getId() . '</info>');
				if (!array_key_exists($item->getId(), $points)) {
					$points[$item->getId()] = [];
				}

				$issue = '';
				if ($instance !== RemoteInstance::LOCAL) {
					$storedDate = $this->getInt($item->getId(), $dates);
					if ($storedDate > 0 && $storedDate !== $item->getDate()) {
						$this->o('  <error>! different date</error>');
						$issue = 'different date';
					}

					try {
						$this->remoteStreamService->verifyPoint($item);
					} catch (SignatoryException | SignatureException $e) {
						$this->o('  <error>! cannot confirm integrity</error>');
						$issue = 'cannot confirm integrity';
					}
				}

				$points[$item->getId()][$instance] = [
					'point' => $item,
					'issue' => $issue
				];

				$dates[$item->getId()] = $item->getDate();
			}
		}

		return $this->orderByDate($points, $dates);
	}


	/**
	 * @param array $points
	 * @param array $dates
	 *
	 * @return array
	 */
	private function orderByDate(array $points, array $dates): array {
		asort($dates);

		$result = [];
		foreach ($dates as $pointId => $date) {
			$result[$pointId] = $points[$pointId];
		}

		return $result;
	}

	/**
	 *
	 */
	public function purgeRestoringPoints(): void {
		$c = $this->configService->getAppValue(ConfigService::STORE_ITEMS);
		$i = 0;
		foreach ($this->getLocalRestoringPoints(0, 0, false) as $point) {
			if ($point->isArchive()) {
				continue;
			}
			$i++;
			if ($i > $c) {
				try {
					$this->delete($point);
				} catch (Throwable $e) {
				}
			}
		}
	}

	/**
	 *
	 */
	public function purgeRemoteRestoringPoints(): void {
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
			$folder = $this->appDataRoot->newFolder('/' . $point->getId());
		} catch (NotPermittedException $e) {
			$folder = $this->appDataRoot->getFolder('/' . $point->getId());
		}

		$folder->newFile(PointService::NOBACKUP_FILE, '');

		$point->setAppDataRootWrapper($this->appDataRoot);
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
				if ($chunk->hasParts()) {
					$this->generateHealthPacked($health, $point, $data, $chunk, $globalStatus);
					continue;
				}

				$chunkHealth = new ChunkPartHealth();

				$status = $this->generateChunkHealthStatus($point, $chunk);
				if ($status !== ChunkPartHealth::STATUS_OK) {
					$globalStatus = 0;
				}

				$chunkHealth->setDataName($data->getName())
							->setChunkName($chunk->getName())
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

		$health->setStatus($globalStatus)
			   ->setChecked(time());
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


	/**
	 * @param string $line
	 * @param bool $ln
	 */
	private function o(string $line, bool $ln = true): void {
		$this->outputService->o($line, $ln);
	}
}
