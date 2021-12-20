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

use ArtificialOwl\MySmallPhpTools\Traits\Nextcloud\nc23\TNC23Deserialize;
use ArtificialOwl\MySmallPhpTools\Traits\TArrayTools;
use ArtificialOwl\MySmallPhpTools\Traits\TFileTools;
use ArtificialOwl\MySmallPhpTools\Traits\TStringTools;
use Exception;
use OCA\Backup\Exceptions\ArchiveCreateException;
use OCA\Backup\Exceptions\ArchiveFileNotFoundException;
use OCA\Backup\Exceptions\ArchiveNotFoundException;
use OCA\Backup\Exceptions\BackupAppCopyException;
use OCA\Backup\Exceptions\BackupScriptNotFoundException;
use OCA\Backup\Exceptions\JobsTimeSlotException;
use OCA\Backup\Exceptions\RestoreChunkException;
use OCA\Backup\Exceptions\RestoringChunkNotFoundException;
use OCA\Backup\Exceptions\RestoringDataNotFoundException;
use OCA\Backup\Exceptions\RestoringPointNotInitiatedException;
use OCA\Backup\Model\ArchiveFile;
use OCA\Backup\Model\RestoringChunk;
use OCA\Backup\Model\RestoringData;
use OCA\Backup\Model\RestoringPoint;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;
use ZipArchive;
use ZipStreamer\COMPR;
use ZipStreamer\ZipStreamer;

/**
 * Class ChunkService
 *
 * @package OCA\Backup\Service
 */
class ChunkService {
	use TArrayTools;
	use TStringTools;
	use TFileTools;
	use TNC23Deserialize;


	public const BACKUP_SCRIPT = 'restore.php';
	public const APP_ZIP = 'app.zip';
	public const PREFIX = '.backup.';


	/** @var FilesService */
	private $filesService;

	/** @var EncryptService */
	private $encryptService;

	/** @var CronService */
	private $cronService;

	/** @var OutputService */
	private $outputService;

	/** @var ConfigService */
	private $configService;


	/**
	 * ChunkService constructor.
	 *
	 * @param FilesService $filesService
	 * @param EncryptService $encryptService
	 * @param OutputService $outputService
	 * @param ConfigService $configService
	 */
	public function __construct(
		FilesService $filesService,
		EncryptService $encryptService,
		CronService $cronService,
		OutputService $outputService,
		ConfigService $configService
	) {
		$this->filesService = $filesService;
		$this->encryptService = $encryptService;
		$this->cronService = $cronService;
		$this->outputService = $outputService;
		$this->configService = $configService;
	}


	/**
	 * @param RestoringPoint $point
	 *
	 * @return void
	 * @throws ArchiveCreateException
	 * @throws ArchiveNotFoundException
	 * @throws NotPermittedException
	 * @throws RestoringPointNotInitiatedException
	 */
	public function createChunks(RestoringPoint $point): void {
		$this->o('> creating chunks');

		foreach ($point->getRestoringData() as $data) {
			if ($data->getType() === RestoringData::INTERNAL_DATA) {
				continue;
			}

			// now would be a good place to refresh tick on lock from cronjob
			try {
				$this->cronService->lockCron(false);
			} catch (JobsTimeSlotException $e) {
			}

			$this->o('  * <info>' . $data->getName() . '</info>: ', false);
			$this->filesService->initRestoringData($data);
			if (!$data->isLocked()) {
				$this->filesService->fillRestoringData($data, $data->getUniqueFile());
			}
			$this->o($data->getRoot() . $data->getPath() . ', ' . count($data->getFiles()) . ' files');
			$data->setLocked(true);

			$this->fillChunks($point, $data);
		}
	}


	/**
	 * @param RestoringPoint $point
	 * @param RestoringChunk $chunk
	 * @param string $root
	 * @param string $filename
	 *
	 * @throws ArchiveCreateException
	 * @throws ArchiveFileNotFoundException
	 * @throws ArchiveNotFoundException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws RestoreChunkException
	 */
	public function restoreUniqueFile(
		RestoringPoint $point,
		RestoringChunk $chunk,
		string $root,
		string $filename
	): void {
		if ($filename === '') {
			throw new ArchiveFileNotFoundException();
		}

		$this->restoreChunk($point, $chunk, $root, $filename);
	}


	/**
	 * @param RestoringPoint $point
	 * @param RestoringChunk $chunk
	 * @param string $root
	 * @param string $filename
	 *
	 * @throws ArchiveCreateException
	 * @throws ArchiveNotFoundException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws RestoreChunkException
	 * @throws RestoringPointNotInitiatedException
	 */
	public function restoreChunk(
		RestoringPoint $point,
		RestoringChunk $chunk,
		string $root,
		string $filename = ''
	): void {
		if (!is_dir($root)) {
			if (!@mkdir($root, 0755, true)) {
				throw new RestoreChunkException('could not create ' . $root);
			}
		}

		$zip = $this->openZipArchive($point, $chunk);
		$zip->extractTo($root, ($filename === '') ? null : $filename);
		$this->closeZipArchive($zip);

		unlink($root . self::PREFIX . $chunk->getName());
	}


	/**
	 * Set $stream to true if the returned ZipArchive will have its method getStream() called
	 * In this case, the generated temporary file need to be removed manually: unlink($zip->filename);
	 *
	 * @param RestoringPoint $point
	 * @param RestoringChunk $chunk
	 * @param bool $stream
	 *
	 * @return ZipArchive
	 * @throws ArchiveNotFoundException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws RestoringPointNotInitiatedException
	 */
	public function openZipArchive(
		RestoringPoint $point,
		RestoringChunk $chunk,
		bool $stream = false
	): ZipArchive {
		$folder = $this->getChunkFolder($point, $chunk);
		$file = $folder->getFile($chunk->getFilename());

		$tmp = tmpfile();
		$tmpPath = stream_get_meta_data($tmp)['uri'];
		if ($stream) {
			// ZipArchive::getStream() will not works on temporary files.
			// we create a real file based on the temporary filename.
			fclose($tmp);
			$tmp = fopen($tmpPath, 'a');
		}

		$read = $file->read();
		while (!feof($read)) {
			fwrite($tmp, fread($read, 8192));
		}

		$zip = new ZipArchive();
		if (($err = $zip->open($tmpPath)) !== true) {
			unlink($tmpPath);
			throw new ArchiveNotFoundException('Could not open Zip Archive (' . $err . ')');
		}

		/** this will delete the temp file if it is a true temp file */
		fclose($tmp);

		return $zip;
	}

	/**
	 * @param ZipArchive $zip
	 */
	public function closeZipArchive(ZipArchive $zip): void {
		$zip->close();
	}


	/**
	 * @param RestoringPoint $point
	 * @param RestoringChunk $chunk
	 *
	 * @throws ArchiveNotFoundException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws RestoringPointNotInitiatedException
	 */
	public function listFilesFromChunk(RestoringPoint $point, RestoringChunk $chunk): void {
		$zip = $this->openZipArchive($point, $chunk);
		$this->listFilesFromZip($chunk, $zip);
	}


	/**
	 * @param RestoringChunk $chunk
	 * @param ZipArchive $zip
	 */
	public function listFilesFromZip(RestoringChunk $chunk, ZipArchive $zip): void {
		$json = $zip->getFromName(self::PREFIX . $chunk->getName());
		if (!$json) {
			return;
		}

		$data = json_decode($json, true);
		/** @var ArchiveFile[] $files */
		$files = $this->deserializeArray($this->getArray('files', $data), ArchiveFile::class);

		$chunk->setFiles($files);
	}


	/**
	 * @param RestoringPoint $point
	 * @param RestoringChunk $chunk
	 * @param string $filename
	 *
	 * @return resource
	 * @throws ArchiveCreateException
	 * @throws ArchiveNotFoundException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws RestoreChunkException
	 */
	public function getStreamFromChunk(RestoringPoint $point, RestoringChunk $chunk, string $filename) {
		$zip = $this->openZipArchive($point, $chunk, true);
		$stream = $zip->getStream($filename);
		unlink($zip->filename);

		if ($stream === false) {
			throw new RestoreChunkException('cannot open stream');
		}

		return $stream;
	}


	/**
	 * @param ZipArchive $zip
	 * @param string $root
	 * @param ArchiveFile[] $archiveFiles
	 */
	public function extractFilesFromZip(ZipArchive $zip, string $root, array $archiveFiles): void {
		$files = array_map(
			function (ArchiveFile $entry) {
				return $entry->getName();
			}, $archiveFiles
		);

		$zip->extractTo($root, $files);
	}


//	/**
//	 * @param Backup $backup
//	 * @param RestoringChunk $archive
//	 * @param bool $encrypted
//	 *
//	 * @return bool
//	 * @throws ArchiveNotFoundException
//	 */
//	public function verifyChecksum(Backup $backup, RestoringChunk $archive, bool $encrypted): bool {
//		$sum = $this->getChecksum($backup, $archive);
//
//		if (!$encrypted && $sum === $archive->getChecksum()) {
//			return true;
//		}
//
//		if ($encrypted && $sum === $archive->getEncryptedChecksum()) {
//			return true;
//		}
//
//		return false;
//	}
//

	/**
	 * @param RestoringPoint $point
	 * @param RestoringData $data
	 *
	 * @throws ArchiveCreateException
	 * @throws ArchiveNotFoundException
	 * @throws NotPermittedException
	 * @throws RestoringPointNotInitiatedException
	 */
	private function fillChunks(RestoringPoint $point, RestoringData $data) {
		$files = $data->getFiles();
		while (!empty($files)) {
			$archive = $this->generateChunk($point, $data, $files);
			$this->o('    - ' . $archive->getName());
			$this->updateChecksum($point, $archive);

			$data->addChunk($archive);
		}
	}


	/**
	 * @param RestoringPoint $point
	 * @param RestoringData $data
	 * @param string $filename
	 * @param string $path
	 * @param string $type
	 *
	 * @throws ArchiveCreateException
	 * @throws ArchiveNotFoundException
	 * @throws NotPermittedException
	 * @throws RestoringPointNotInitiatedException
	 */
	public function createSingleFileChunk(
		RestoringPoint $point,
		RestoringData $data,
		string $filename,
		string $path,
		string $type = ''
	): void {
		$chunk = new RestoringChunk($data->getName());
		$chunk->setCount(1);
		$chunk->addFile(new ArchiveFile($filename));
		$chunk->setSize(filesize($path));
		$chunk->setType($type);
		$data->addChunk($chunk);

		$this->createSingleFileZip($point, $chunk, $filename, $path);

		$this->updateChecksum($point, $chunk);
	}


	/**
	 * @param RestoringPoint $point
	 * @param RestoringChunk $chunk
	 * @param string $filename
	 * @param string $path
	 *
	 * @throws ArchiveCreateException
	 * @throws NotPermittedException
	 * @throws RestoringPointNotInitiatedException
	 */
	private function createSingleFileZip(
		RestoringPoint $point,
		RestoringChunk $chunk,
		string $filename,
		string $path
	): void {
		$zip = $this->generateZip($point, $chunk);
		$read = fopen($path, 'rb');
		$zip->addFileFromStream($read, $filename);
		$zip->finalize();
	}


	/**
	 * @param RestoringPoint $point
	 * @param RestoringData $data
	 * @param array $files
	 *
	 * @return RestoringChunk
	 * @throws ArchiveCreateException
	 * @throws NotPermittedException
	 * @throws RestoringPointNotInitiatedException
	 */
	private function generateChunk(
		RestoringPoint $point,
		RestoringData $data,
		array &$files
	): RestoringChunk {
		$chunk = new RestoringChunk($data->getName());
		$chunkSize = $this->configService->getAppValueInt(ConfigService::CHUNK_SIZE) * 1024 * 1024;

		// now would be a good place to refresh tick on lock from cronjob
		try {
			$this->cronService->lockCron(false);
		} catch (JobsTimeSlotException $e) {
		}

		$zip = $this->generateZip($point, $chunk);
		$zipSize = 0;
		while (($filename = array_shift($files)) !== null) {
			$fileSize = filesize($data->getAbsolutePath() . $filename);
			if (is_bool($fileSize)) {
				$fileSize = 1;
			}

			if ($zipSize > 0 && ($zipSize + $fileSize) > $chunkSize) {
				$this->finalizeZip($zip, $chunk->setCount()->setSize((int)$zipSize));
				$this->finalizeChunk($point, $chunk);
				array_unshift($files, $filename);

				return $chunk;
			}

			$zipSize += $fileSize;
			$in = fopen($data->getAbsolutePath() . $filename, 'rb');

			$zip->addFileFromStream($in, $filename);
			$archiveFile = new ArchiveFile($filename, $fileSize);
			$chunk->addFile($archiveFile);
		}

		$this->finalizeZip($zip, $chunk->setCount()->setSize((int)$zipSize));
		$this->finalizeChunk($point, $chunk);

		return $chunk;
	}


	/**
	 * @param RestoringPoint $point
	 * @param RestoringChunk $chunk
	 *
	 * @return ZipStreamer
	 * @throws ArchiveCreateException
	 * @throws NotPermittedException
	 * @throws RestoringPointNotInitiatedException
	 */
	public function generateZip(RestoringPoint $point, RestoringChunk $chunk): ZipStreamer {
		$folder = $this->getChunkFolder($point, $chunk);

		try {
			$file = $folder->newFile($chunk->getFilename());
			$zip = new ZipStreamer(
				[
					'outstream' => $file->write(),
					'zip64' => true,
					'compress' => COMPR::STORE,
					'level' => COMPR::NONE
				]
			);
		} catch (Exception $e) {
			throw new ArchiveCreateException(
				'could not create Zip archive (' . $e->getMessage() . ')'
			);
		}

		return $zip;
	}

	/**
	 * @param ZipStreamer $zip
	 * @param RestoringChunk $archive
	 */
	public function finalizeZip(ZipStreamer $zip, RestoringChunk $archive): void {
		$str = json_encode($archive->getResume(), JSON_PRETTY_PRINT);
		$read = fopen('data://text/plain,' . $str, 'rb');
		$zip->addFileFromStream($read, self::PREFIX . $archive->getName());

		$zip->finalize();
	}


	/**
	 * @param RestoringPoint $point
	 * @param RestoringChunk $chunk
	 */
	private function finalizeChunk(RestoringPoint $point, RestoringChunk $chunk) {
		try {
			$folder = $this->getChunkFolder($point, $chunk);
			if ($this->configService->getAppValueBool(ConfigService::PACK_INDEX)) {
				$folder->newFile(
					self::PREFIX . $chunk->getName(),
					json_encode($chunk->getResume(), JSON_PRETTY_PRINT)
				);
			}
		} catch (Exception $e) {
		}
	}


	/**
	 * @param RestoringPoint $point
	 * @param RestoringChunk $chunk
	 *
	 * @throws ArchiveNotFoundException
	 */
	private function updateChecksum(RestoringPoint $point, RestoringChunk $chunk): void {
		$sum = $this->getChecksum($point, $chunk);
		$chunk->setChecksum($sum);
	}


	/**
	 * @param RestoringPoint $point
	 * @param RestoringChunk $chunk
	 *
	 * @return string
	 * @throws ArchiveNotFoundException
	 */
	public function getChecksum(RestoringPoint $point, RestoringChunk $chunk): string {
		try {
			if ($point->isPackage()) {
				if (!file_exists('./' . $chunk->getFilename())) {
					throw new ArchiveNotFoundException('Archive not found');
				}
				$stream = fopen('./' . $chunk->getFilename(), 'rb');
			} else {
				$folder = $this->getChunkFolder($point, $chunk);
				$file = $folder->getFile($chunk->getFilename());
				$stream = $file->read();
			}
		} catch (Exception $e) {
			throw new ArchiveNotFoundException(
				'Chunk ' . $chunk->getPath() . $chunk->getFilename() . ' not found'
			);
		}

		if (is_bool($stream)) {
			throw new ArchiveNotFoundException('Chunk ' . $chunk->getFilename() . ' not valid');
		}

		return $this->getChecksumFromStream($stream);
	}


//	/**
//	 * @param Backup $backup
//	 * @param RestoringChunk $archive
//	 * @param string $ext
//	 *
//	 * @throws ArchiveDeleteException
//	 */
//	public function deleteArchive(Backup $backup, RestoringChunk $archive, $ext = '') {
//		if ($backup->isLocal()) {
//			unlink('./' . $archive->getName($ext));
//		} else {
//			$folder = $backup->getBaseFolder();
//			try {
//				$file = $folder->getFile($archive->getName($ext));
//				$file->delete();
//			} catch (Exception $e) {
//				throw new ArchiveDeleteException('Could not delete Archive');
//			}
//		}
//	}

//
//	/**
//	 * @param Backup $backup
//	 * @param RestoringChunk $archive
//	 * @param bool $delete
//	 *
//	 * @throws ArchiveNotFoundException
//	 * @throws ArchiveDeleteException
//	 */
//	public function encryptArchive(Backup $backup, RestoringChunk $archive, bool $delete): void {
//		$folder = $backup->getBaseFolder();
//		try {
//			$file = $folder->getFile($archive->getName('zip'));
//		} catch (Exception $e) {
//			throw new ArchiveNotFoundException('Could not read Archive to encrypt');
//		}
//
//		try {
//			$encrypted = $folder->newFile($archive->getName());
//		} catch (Exception $e) {
//			throw new ArchiveNotFoundException('Could not write to encrypted Archive');
//		}
//
//		$key = substr(sha1($backup->getEncryptionKey(), true), 0, 16);
//		try {
//			$this->encryptService->encryptFile($file->read(), $encrypted->write(), $key);
//		} catch (Exception $e) {
//			throw new ArchiveNotFoundException('Could not encrypt Archive');
//		}
//
//		if ($delete) {
//			try {
//				$file->delete();
//			} catch (Exception $e) {
//				throw new ArchiveDeleteException('Could not delete non-encrypted Archive !');
//			}
//		}
//	}
//
//
//	/**
//	 * @param Backup $backup
//	 * @param RestoringChunk $archive
//	 *
//	 * @throws EncryptionKeyException
//	 * @throws ArchiveNotFoundException
//	 * @throws ArchiveNotFoundException
//	 */
//	public function decryptArchive(Backup $backup, RestoringChunk $archive) {
//		if ($backup->isLocal()) {
//			if (!file_exists('./' . $archive->getName())) {
//				throw new ArchiveNotFoundException('Archive not found');
//			}
//			$stream = fopen('./' . $archive->getName(''), 'rb');
//			$write = fopen('./' . $archive->getName('zip'), 'wb');
//		} else {
//			$folder = $backup->getBaseFolder();
//
//			try {
//				$encrypted = $folder->getFile($archive->getName());
//				$stream = $encrypted->read();
//			} catch (Exception $e) {
//				throw new ArchiveNotFoundException('Archive not found');
//			}
//
//			try {
//				$file = $folder->newFile($archive->getName('zip'));
//				$write = $file->write();
//			} catch (Exception $e) {
//				throw new ArchiveNotFoundException('Zip file not created');
//			}
//		}
//
//		$key = substr(sha1($backup->getEncryptionKey(), true), 0, 16);
//		$this->encryptService->decryptFile($stream, $write, $key);
//	}


	/**
	 * @param RestoringPoint $point
	 *
	 * @throws BackupAppCopyException
	 * @throws BackupScriptNotFoundException
	 */
	public function copyApp(RestoringPoint $point): void {
		$folder = $point->getBaseFolder();
		try {
			$file = $folder->newFile(self::APP_ZIP);
			$zip = new ZipStreamer(
				[
					'outstream' => $file->write(),
					'zip64' => false,
					'compress' => COMPR::STORE,
					'level' => COMPR::NONE
				]
			);
		} catch (Exception $e) {
			throw new BackupAppCopyException('Could not generate ' . self::APP_ZIP);
		}

		$appFiles = $this->filesService->getFilesFromApp();
		foreach ($appFiles as $file) {
			if ($file === self::BACKUP_SCRIPT) {
				continue;
			}

			$in = fopen(FilesService::APP_ROOT . $file, 'rb');
			$zip->addFileFromStream($in, './app/' . $file);
		}

		$zip->finalize();

		$script = file_get_contents(FilesService::APP_ROOT . self::BACKUP_SCRIPT);
		try {
			$scriptFile = $folder->newFile(self::BACKUP_SCRIPT);
			$scriptFile->putContent($script);
		} catch (Exception $e) {
			throw new BackupScriptNotFoundException('Could not create ' . self::BACKUP_SCRIPT);
		}
	}


	/**
	 * @param RestoringPoint $point
	 *
	 * @throws ArchiveNotFoundException
	 */
	public function generateInternalData(RestoringPoint $point): void {
		$data = new RestoringData(
			RestoringData::INTERNAL_DATA,
			'',
			RestoringData::INTERNAL
		);

		$chunk = new RestoringChunk(self::APP_ZIP, true);
		$this->updateChecksum($point, $chunk);
		$data->addChunk($chunk);

		$chunk = new RestoringChunk(self::BACKUP_SCRIPT, true);
		$this->updateChecksum($point, $chunk);
		$data->addChunk($chunk);

		$point->addRestoringData($data);
	}


	/**
	 * @param RestoringPoint $point
	 * @param string $dataName
	 *
	 * @return RestoringData
	 * @throws RestoringDataNotFoundException
	 */
	public function getDataFromRP(RestoringPoint $point, string $dataName): RestoringData {
		foreach ($point->getRestoringData() as $data) {
			if ($data->getName() === $dataName) {
				return $data;
			}
		}

		throw new RestoringDataNotFoundException();
	}


	/**
	 * @param RestoringPoint $point
	 * @param string $chunk
	 *
	 * @return RestoringData
	 * @throws RestoringChunkNotFoundException
	 */
	public function getDataWithChunk(RestoringPoint $point, string $chunk): RestoringData {
		foreach ($point->getRestoringData() as $restoringData) {
			try {
				$this->getChunkFromRP($point, $chunk, $restoringData->getName());

				return $restoringData;
			} catch (RestoringChunkNotFoundException $e) {
			}
		}

		throw new RestoringChunkNotFoundException();
	}


	/**
	 * @param RestoringPoint $point
	 * @param string $chunk
	 * @param string $dataName
	 *
	 * @return RestoringChunk
	 * @throws RestoringChunkNotFoundException
	 */
	public function getChunkFromRP(
		RestoringPoint $point,
		string $chunk,
		string $dataName = ''
	): RestoringChunk {
		foreach ($point->getRestoringData() as $restoringData) {
			if ($dataName !== '' && $restoringData->getName() !== $dataName) {
				continue;
			}

			foreach ($restoringData->getChunks() as $restoringChunk) {
				if ($restoringChunk->getName() === $chunk) {
					return $restoringChunk;
				}
			}
		}

		throw new RestoringChunkNotFoundException();
	}


	/**
	 * @param RestoringPoint $point
	 * @param RestoringChunk $restoringChunk
	 */
	public function getChunkContent(RestoringPoint $point, RestoringChunk $restoringChunk): void {
		try {
			$file = $this->getChunkResource($point, $restoringChunk);
			$restoringChunk->setContent(base64_encode($file->getContent()));
		} catch (NotFoundException | NotPermittedException $e) {
		}
	}


	/**
	 * @param RestoringPoint $point
	 * @param RestoringChunk $chunk
	 *
	 * @return ISimpleFile
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws RestoringPointNotInitiatedException
	 */
	public function getChunkResource(RestoringPoint $point, RestoringChunk $chunk): ISimpleFile {
		$folder = $this->getChunkFolder($point, $chunk);

		return $folder->getFile($chunk->getFilename());
	}


	/**
	 * @param RestoringPoint $point
	 * @param RestoringChunk $chunk
	 * @param string $search
	 *
	 * @return ArchiveFile[]
	 * @throws ArchiveCreateException
	 * @throws ArchiveNotFoundException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function searchFilesInChunk(RestoringPoint $point, RestoringChunk $chunk, string $search): array {
		if (empty($chunk->getFiles())) {
			$this->listFilesFromChunk($point, $chunk);
		}

		$search = strtolower($search);

		return array_filter(
			array_map(
				function (ArchiveFile $file) use ($search): ?ArchiveFile {
					if (strpos(strtolower($file->getName()), $search) !== false) {
						return $file;
					}

					return null;
				}, $chunk->getFiles()
			)
		);
	}


	/**
	 * @param RestoringPoint $point
	 * @param RestoringData $data
	 * @param string $filename
	 *
	 * @return ArchiveFile
	 * @throws ArchiveFileNotFoundException
	 */
	public function getArchiveFileFromData(
		RestoringPoint $point,
		RestoringData $data,
		string $filename
	): ArchiveFile {
		foreach ($data->getChunks() as $chunk) {
			try {
				return $this->getArchiveFileFromChunk($point, $chunk, $filename);
			} catch (
			ArchiveCreateException
			| ArchiveNotFoundException
			| ArchiveFileNotFoundException
			| NotFoundException
			| NotPermittedException $e) {
			}
		}

		throw new ArchiveFileNotFoundException();
	}


	/**
	 * @param RestoringPoint $point
	 * @param RestoringChunk $chunk
	 * @param string $filename
	 *
	 * @return ArchiveFile
	 * @throws ArchiveCreateException
	 * @throws ArchiveNotFoundException
	 * @throws ArchiveFileNotFoundException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function getArchiveFileFromChunk(
		RestoringPoint $point,
		RestoringChunk $chunk,
		string $filename
	): ArchiveFile {
		if (empty($chunk->getFiles())) {
			$this->listFilesFromChunk($point, $chunk);
		}

		$result = array_filter(
			array_map(
				function (ArchiveFile $file) use ($filename): ?ArchiveFile {
					if ($file->getName() === $filename) {
						return $file;
					}

					return null;
				}, $chunk->getFiles()
			)
		);

		if (empty($result)) {
			throw new ArchiveFileNotFoundException();
		}

		/** @var ArchiveFile $file */
		$file = array_shift($result);
		$file->setRestoringChunk($chunk);

		return $file;
	}


	/**
	 * @throws RestoringPointNotInitiatedException
	 * @throws NotPermittedException
	 * @throws NotFoundException
	 */
	public function removeChunkFile(RestoringPoint $point, RestoringChunk $chunk) {
		$folder = $this->getChunkFolder($point, $chunk);
		$file = $folder->getFile($chunk->getFilename());
		$file->delete();
	}


	/**
	 * @param RestoringPoint $point
	 * @param RestoringChunk $chunk
	 *
	 * @return ISimpleFolder
	 * @throws NotPermittedException
	 * @throws RestoringPointNotInitiatedException
	 */
	public function getChunkFolder(
		RestoringPoint $point,
		RestoringChunk $chunk,
		string &$path = ''
	): ISimpleFolder {
		if (!$point->hasBaseFolder() || !$point->hasAppDataRootWrapper()) {
			throw new RestoringPointNotInitiatedException('Restoring Point is not initiated');
		}

		$folder = $point->getBaseFolder();
		if ($chunk->getPath() !== '') {
			$path = '/' . $folder->getName() . '/' . $chunk->getPath();
			$root = $point->getAppDataRootWrapper();
			try {
				$folder = $root->getFolder($path);
			} catch (NotFoundException $e) {
				$folder = $root->newFolder($path);
			}
		}

		return $folder;
	}


	/**
	 * @param string $line
	 * @param bool $ln
	 */
	private function o(string $line, bool $ln = true): void {
		$this->outputService->o($line, $ln);
	}
}
