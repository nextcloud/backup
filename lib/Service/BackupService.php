<?php

declare(strict_types=1);


/**
 * Nextcloud - Backup
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


use ArtificialOwl\MySmallPhpTools\Traits\TArrayTools;
use ArtificialOwl\MySmallPhpTools\Traits\TFileTools;
use ArtificialOwl\MySmallPhpTools\Traits\TStringTools;
use Exception;
use OC;
use OC\Files\AppData\Factory;
use OCA\Backup\AppInfo\Application;
use OCA\Backup\Exceptions\ArchiveCreateException as ArchiveCreateExceptionAlias;
use OCA\Backup\Exceptions\ArchiveDeleteException;
use OCA\Backup\Exceptions\ArchiveNotFoundException;
use OCA\Backup\Exceptions\BackupAppCopyException;
use OCA\Backup\Exceptions\BackupFolderException;
use OCA\Backup\Exceptions\BackupNotFoundException;
use OCA\Backup\Exceptions\BackupScriptNotFoundException as BackupScriptNotFoundExceptionAlias;
use OCA\Backup\Exceptions\ChunkNotFoundException;
use OCA\Backup\Model\Backup;
use OCA\Backup\Model\BackupChunk;
use OCA\Backup\Model\RestoringData;
use OCA\Backup\Model\RestoringPoint;
use OCA\Backup\SqlDump\SqlDumpMySQL;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Util;


/**
 * Class BackupService
 *
 * @package OCA\Backup\Service
 */
class BackupService {


	use TArrayTools;
	use TStringTools;
	use TFileTools;


	const NOBACKUP_FILE = '.nobackup';
	const SUMMARY_FILE = 'metadata.json';
	const SQL_DUMP_FILE = 'backup_sql';

	/** @var IAppData */
	private $appData;

	/** @var ArchiveService */
	private $archiveService;

	/** @var FilesService */
	private $filesService;

	/** @var ConfigService */
	private $configService;

	/** @var MiscService */
	private $miscService;


	/**
	 * BackupService constructor.
	 *
	 * @param ArchiveService $archiveService
	 * @param FilesService $filesService
	 * @param ConfigService $configService
	 * @param MiscService $miscService
	 */
	public function __construct(
		ArchiveService $archiveService, FilesService $filesService, ConfigService $configService,
		MiscService $miscService
	) {
		if (class_exists(OC::class)) {
			/** @var Factory $factory */
			$factory = OC::$server->get(Factory::class);
			$this->appData = $factory->get(Application::APP_ID);
		}

		$this->archiveService = $archiveService;
		$this->filesService = $filesService;
		$this->configService = $configService;
		$this->miscService = $miscService;
	}


	/**
	 * @return Backup
	 * @throws ArchiveNotFoundException
	 * @throws NotPermittedException
	 * @throws ArchiveCreateExceptionAlias
	 * @throws ArchiveDeleteException
	 * @throws BackupAppCopyException
	 * @throws BackupScriptNotFoundExceptionAlias
	 * @throws NotFoundException
	 */
	public function backup(bool $complete = true): Backup {
		$backup = $this->initRestoringPoint($complete);
//		$this->archiveService->copyApp($backup);

//		$backup->setEncryptionKey('12345');
//		$this->archiveService->getArchives($backup);
//		$this->backupSql($backup);
//		$this->endBackup($backup);

		return $backup;
	}


	/**
	 * @return Backup[]
	 * @throws NotPermittedException
	 * @throws NotFoundException
	 */
	public function listing(): array {
		$this->initBackupFS();

		$backups = [];
		$ls = $this->appData->getDirectoryListing();
		foreach ($ls as $entry) {
			if ($ls === 'remote') {
				continue;
			}

			try {
				$backups[] = $this->parseFromFolder($entry->getName());
			} catch (BackupFolderException $e) {
			} catch (NotPermittedException $e) {
			}
		}

		return $backups;
	}


//	/**
//	 * @param string $token
//	 * @param string $pass
//	 * @param string $file
//	 *
//	 * @return Backup
//	 * @throws BackupNotFoundException
//	 * @throws NotFoundException
//	 * @throws NotPermittedException
//	 */
//	public function restore(string $token, string $pass, string $file = ''): Backup {
//		$backup = $this->getBackup($token);
//
//		foreach ($backup->getFiles() as $files) {
//			if ($file === '' || $files->getName() === $file) {
//				$this->restoreBackupChunk($backup, $files, $pass);
//			}
//		}
//
//		return $backup;
//	}


	/**
	 * @param Backup $backup
	 * @param BackupChunk $files
	 *
	 * @return string
	 */
	public function getExtractRoot(Backup $backup, BackupChunk $files): string {
		$absoluteRootLength = strlen($this->getAbsoluteRoot($backup));
		$base = substr($files->getRoot(), $absoluteRootLength);

		$options = $backup->getOptions();
		$root = ($options->getNewRoot() === '') ? $files->getRoot() : $options->getNewRoot();
		$root .= $base . $files->getPath();

		return $root;
	}


	/**
	 * @param Backup $backup
	 */
	public function restoreBackup(Backup $backup): void {
		foreach ($backup->getChunks(true) as $backupChunk) {
			$this->restoreBackupChunk($backup, $backupChunk);
		}
	}


	/**
	 * @param Backup $backup
	 * @param BackupChunk $files
	 */
	public function restoreBackupChunk(Backup $backup, BackupChunk $files): void {
		if ($files->getType() >= BackupChunk::SQL_DUMP) {
			$this->restoreNonFileChunk($backup, $files);

			return;
		}

		$root = $this->getExtractRoot($backup, $files);
		foreach ($files->getArchives() as $archive) {
			try {
				$this->archiveService->extractAll($backup, $archive, $root);
			} catch (Exception $e) {
				//echo $e->getMessage() . "\n";
			}
		}
	}


	/**
	 * @param Backup $backup
	 * @param BackupChunk $files
	 */
	private function restoreNonFileChunk(Backup $backup, BackupChunk $files): void {
		switch ($files->getType()) {
			case BackupChunk::SQL_DUMP:
				$this->restoreSqlDump($backup, $files);
				break;
		}
	}


	/**
	 * @param Backup $backup
	 * @param BackupChunk $files
	 */
	private function restoreSqlDump(Backup $backup, BackupChunk $files): void {

//		$sqlDump = new SqlDumpMySQL();
//		$content = $sqlDump->import();
	}


	/**
	 * @param string $token
	 *
	 * @return Backup
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws BackupNotFoundException
	 */
	public function getBackup(string $token): Backup {
		$backups = $this->listing();

		foreach ($backups as $backup) {
			if ($backup->getId() === $token) {
				return $backup;
			}
		}

		throw new BackupNotFoundException('Backup not found');
	}


	/**
	 * @param Backup $backup
	 *
	 * @return string
	 */
	public function getAbsoluteRoot(Backup $backup) {
		$compared = false;
		$absoluteRoot = '';
		foreach ($backup->getChunks() as $backupChunk) {
			if ($backupChunk->getType() >= BackupChunk::SQL_DUMP) {
				continue;
			}

			if (!$compared) {
				$absoluteRoot = $backupChunk->getRoot();
				$compared = true;
				continue;
			}

			$absoluteRoot = $this->commonPart($absoluteRoot, $backupChunk->getRoot());
		}

		return $absoluteRoot;
	}



	/**
	 * @param string $path
	 *
	 * @return Backup
	 * @throws BackupFolderException
	 * @throws NotPermittedException
	 */
	private function parseFromFolder(string $path): Backup {
		try {
			$folder = $this->appData->getFolder($path);
			$json = $folder->getFile(self::SUMMARY_FILE)
						   ->getContent();

			$summary = json_decode($json, true);
			if (!is_array($summary)) {
				throw new BackupFolderException();
			}

			$backup = new Backup();
			$backup->setBaseFolder($folder);

			return $backup->import($summary);
		} catch (NotFoundException $e) {
			throw new BackupFolderException();
		}
	}


	/**
	 * @param Backup $backup
	 * @param string $name
	 *
	 * @return BackupChunk
	 * @throws ChunkNotFoundException
	 */
	public function getChunk(Backup $backup, string $name): BackupChunk {
		foreach ($backup->getChunks() as $chunk) {
			if ($chunk->getName() === $name) {
				return $chunk;
			}
		}

		throw new ChunkNotFoundException();
	}


}

