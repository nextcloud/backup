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


use ArtificialOwl\MySmallPhpTools\Traits\TArrayTools;
use ArtificialOwl\MySmallPhpTools\Traits\TFileTools;
use ArtificialOwl\MySmallPhpTools\Traits\TStringTools;
use Exception;
use OCA\Backup\Exceptions\ArchiveCreateException;
use OCA\Backup\Exceptions\ArchiveDeleteException;
use OCA\Backup\Exceptions\ArchiveNotFoundException;
use OCA\Backup\Exceptions\BackupAppCopyException;
use OCA\Backup\Exceptions\BackupScriptNotFoundException;
use OCA\Backup\Exceptions\ChunkNotFoundException;
use OCA\Backup\Exceptions\EncryptionKeyException;
use OCA\Backup\Model\ArchiveFile;
use OCA\Backup\Model\Backup;
use OCA\Backup\Model\RestoringChunk;
use OCA\Backup\Model\RestoringData;
use OCA\Backup\Model\RestoringPoint;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use ZipArchive;
use ZipStreamer\COMPR;
use ZipStreamer\ZipStreamer;


/**
 * Class ArchiveService
 *
 * @package OCA\Backup\Service
 */
class ArchiveService {


	use TArrayTools;
	use TStringTools;
	use TFileTools;


	const MAX_ZIP_SIZE = 100000000;
	const BACKUP_SCRIPT = 'restore.php';
	const APP_ZIP = 'app.zip';

	/** @var FilesService */
	private $filesService;

	/** @var EncryptService */
	private $encryptService;

	/** @var MiscService */
	private $miscService;


	/**
	 * ArchiveService constructor.
	 *
	 * @param FilesService $filesService
	 * @param EncryptService $encryptService
	 * @param MiscService $miscService
	 */
	public function __construct(
		FilesService $filesService, EncryptService $encryptService, MiscService $miscService
	) {
		$this->filesService = $filesService;
		$this->encryptService = $encryptService;
		$this->miscService = $miscService;
	}


	/**
	 * @param RestoringPoint $point
	 *
	 * @return void
	 * @throws ArchiveCreateException
	 * @throws ArchiveNotFoundException
	 */
	public function createChunks(RestoringPoint $point): void {
		foreach ($point->getRestoringData() as $data) {
			if ($data->getType() === RestoringData::INTERNAL_DATA) {
				continue;
			}

			$this->filesService->initRestoringData($data);
			$this->filesService->fillRestoringData($data, $data->getUniqueFile());

			$this->fillChunks($point, $data);
		}
	}

//
//	/**
//	 * @param Backup $backup
//	 * @param RestoringChunk $archive
//	 * @param string $root
//	 *
//	 * @throws ArchiveDeleteException
//	 * @throws ArchiveNotFoundException
//	 * @throws EncryptionKeyException
//	 * @throws BackupFolderException
//	 */
//	public function extractAll(Backup $backup, RestoringChunk $archive, string $root): void {
//		if (!is_dir($root)) {
//			if (!@mkdir($root, 0755, true)) {
//				throw new BackupFolderException('could not create ' . $root);
//			}
//		}
//
//		$this->decryptArchive($backup, $archive);
//		$this->extractAllFromArchive($archive, $root);
//		$this->deleteArchive($backup, $archive, 'zip');
//	}


//	/**
//	 * @param RestoringChunk $archive
//	 * @param string $root
//	 *
//	 * @throws ArchiveNotFoundException
//	 * @throws Exception
//	 */
//	public function extractAllFromArchive(RestoringChunk $archive, string $root): void {
//		$zip = $this->openZipArchive($archive);
//		$zip->extractTo($root);
//		$this->closeZipArchive($zip);
//
//		unlink($root . '.backup.' . $archive->getName() . '.json');
//	}


	/**
	 * @param RestoringChunk $archive
	 *
	 * @return ZipArchive
	 * @throws ArchiveNotFoundException
	 */
	public function openZipArchive(RestoringChunk $archive): ZipArchive {
		$zip = new ZipArchive();
		if (($err = $zip->open($archive->getName('zip'))) !== true) {
			throw new ArchiveNotFoundException('Could not open Zip Archive (' . $err . ')');
		}

		return $zip;
	}


	/**
	 * @param ZipArchive $zip
	 */
	public function closeZipArchive(ZipArchive $zip): void {
		$zip->close();
	}


	/**
	 * @param RestoringChunk $archive
	 * @param ZipArchive $zip
	 */
	public function listFilesFromZip(RestoringChunk $archive, ZipArchive $zip): void {
		$json = $zip->getFromName('.backup.' . $archive->getName() . '.json');
		$data = json_decode($json, true);
		$files = $this->getList('files', $data, [ArchiveFile::class, 'import'], []);

		$archive->setFiles($files);
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


	/**
	 * @param ZipArchive $zip
	 * @param string $file
	 *
	 * @return resource
	 */
	public function extractContentFromZip(ZipArchive $zip, string $file) {
		return $zip->getStream($file);
	}


	/**
	 * @param Backup $backup
	 * @param RestoringChunk $archive
	 * @param bool $encrypted
	 *
	 * @return bool
	 * @throws ArchiveNotFoundException
	 */
	public function verifyChecksum(Backup $backup, RestoringChunk $archive, bool $encrypted): bool {
		$sum = $this->getChecksum($backup, $archive);

		if (!$encrypted && $sum === $archive->getChecksum()) {
			return true;
		}

		if ($encrypted && $sum === $archive->getEncryptedChecksum()) {
			return true;
		}

		return false;
	}


	/**
	 * @param RestoringPoint $point
	 * @param RestoringData $data
	 *
	 * @throws ArchiveCreateException
	 * @throws ArchiveNotFoundException
	 */
	private function fillChunks(RestoringPoint $point, RestoringData $data) {
		$files = $data->getFiles();
		while (!empty($files)) {
			$archive = $this->generateChunk($point, $data, $files);
			$this->updateChecksum($point, $archive, false);
//			$this->encryptArchive($backup, $archive, true);
//			$this->updateChecksum($point, $archive, true);

			$data->addChunk($archive);
		}
	}


	/**
	 * @param RestoringPoint $point
	 * @param RestoringData $data
	 * @param string $filename
	 * @param string $content
	 *
	 * @throws ArchiveCreateException
	 * @throws ArchiveNotFoundException
	 */
	public function createContentChunk(
		RestoringPoint $point,
		RestoringData $data,
		string $filename,
		string $content
	): void {
		$chunk = new RestoringChunk();
		$chunk->setCount(1);
		$chunk->addFile(new ArchiveFile($filename));
		$chunk->setSize(strlen($content));
		$data->addChunk($chunk);

		$this->createContentZip($point, $chunk, $filename, $content);

		$this->updateChecksum($point, $chunk);
//		$this->encryptArchive($backup, $archive, true);
//		$this->updateChecksum($backup, $archive, true);
	}


	/**
	 * @param RestoringPoint $point
	 * @param RestoringChunk $chunk
	 * @param string $filename
	 * @param string $content
	 *
	 * @throws ArchiveCreateException
	 */
	private function createContentZip(
		RestoringPoint $point,
		RestoringChunk $chunk,
		string $filename,
		string $content
	): void {
		$zip = $this->generateZip($point, $chunk->getName('zip'));
		$read = fopen('data://text/plain,' . $content, 'r');
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
	 */
	private function generateChunk(
		RestoringPoint $point,
		RestoringData $data,
		array &$files
	): RestoringChunk {
		$chunk = new RestoringChunk();

		$zip = $this->generateZip($point, $chunk->getName('zip'));
		$zipSize = 0;
		while (($filename = array_shift($files)) !== null) {
			$fileSize = filesize($data->getAbsolutePath() . $filename);
			if ($zipSize > 0 && ($zipSize + $fileSize) > self::MAX_ZIP_SIZE) {
				$this->finalizeZip($zip, $chunk->setSize($zipSize));

				return $chunk;
			}

			$zipSize += $fileSize;
			$in = fopen($data->getAbsolutePath() . $filename, 'r');

			$zip->addFileFromStream($in, $filename);
			$archiveFile = new ArchiveFile($filename);
			$chunk->addFile($archiveFile);
			$chunk->setCount();
		}

		$this->finalizeZip($zip, $chunk->setSize($zipSize));

		return $chunk;
	}


	/**
	 * @param RestoringPoint $point
	 * @param string $filename
	 *
	 * @return ZipStreamer
	 * @throws ArchiveCreateException
	 */
	public function generateZip(RestoringPoint $point, string $filename): ZipStreamer {
		if (!$point->hasBaseFolder()) {
			throw new ArchiveCreateException('Backup has no Base Folder');
		}

		$folder = $point->getBaseFolder();
		try {
			$file = $folder->newFile($filename);
			$zip = new ZipStreamer(
				[
					'outstream' => $file->write(),
					'zip64' => false,
					'compress' => COMPR::DEFLATE,
					'level' => $this->assignCompressionLevel()
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
		$read = fopen('data://text/plain,' . $str, 'r');
		$zip->addFileFromStream($read, '.backup.' . $archive->getName() . '.json');

		$zip->finalize();
	}


	/**
	 * @param RestoringPoint $point
	 * @param RestoringChunk $chunk
	 *
	 * @throws ArchiveNotFoundException
	 */
	private function updateChecksum(RestoringPoint $point, RestoringChunk $chunk): void {
		$sum = $this->getChecksum($point, $chunk);
		if ($chunk->isEncrypted()) {
			$chunk->setEncryptedChecksum($sum);
		} else {
			$chunk->setChecksum($sum);
		}
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
				$stream = fopen('./' . $chunk->getFilename(), 'r');
			} else {
				$folder = $point->getBaseFolder();
				$file = $folder->getFile($chunk->getFilename());
				$stream = $file->read();
			}
		} catch (Exception $e) {
			throw new ArchiveNotFoundException('Chunk ' . $chunk->getFilename() . ' not found');
		}

		if (is_bool($stream)) {
			throw new ArchiveNotFoundException('Chunk ' . $chunk->getFilename() . ' not valid');
		}

		return $this->getChecksumFromStream($stream);
	}


	/**
	 * @param Backup $backup
	 * @param RestoringChunk $archive
	 * @param string $ext
	 *
	 * @throws ArchiveDeleteException
	 */
	public function deleteArchive(Backup $backup, RestoringChunk $archive, $ext = '') {
		if ($backup->isLocal()) {
			unlink('./' . $archive->getName($ext));
		} else {
			$folder = $backup->getBaseFolder();
			try {
				$file = $folder->getFile($archive->getName($ext));
				$file->delete();
			} catch (Exception $e) {
				throw new ArchiveDeleteException('Could not delete Archive');
			}
		}
	}


	/**
	 * @param Backup $backup
	 * @param RestoringChunk $archive
	 * @param bool $delete
	 *
	 * @throws ArchiveNotFoundException
	 * @throws ArchiveDeleteException
	 */
	public function encryptArchive(Backup $backup, RestoringChunk $archive, bool $delete): void {
		$folder = $backup->getBaseFolder();
		try {
			$file = $folder->getFile($archive->getName('zip'));
		} catch (Exception $e) {
			throw new ArchiveNotFoundException('Could not read Archive to encrypt');
		}

		try {
			$encrypted = $folder->newFile($archive->getName());
		} catch (Exception $e) {
			throw new ArchiveNotFoundException('Could not write to encrypted Archive');
		}

		$key = substr(sha1($backup->getEncryptionKey(), true), 0, 16);
		try {
			$this->encryptService->encryptFile($file->read(), $encrypted->write(), $key);
		} catch (Exception $e) {
			throw new ArchiveNotFoundException('Could not encrypt Archive');
		}

		if ($delete) {
			try {
				$file->delete();
			} catch (Exception $e) {
				throw new ArchiveDeleteException('Could not delete non-encrypted Archive !');
			}
		}
	}


	/**
	 * @param Backup $backup
	 * @param RestoringChunk $archive
	 *
	 * @throws EncryptionKeyException
	 * @throws ArchiveNotFoundException
	 * @throws ArchiveNotFoundException
	 */
	public function decryptArchive(Backup $backup, RestoringChunk $archive) {
		if ($backup->isLocal()) {
			if (!file_exists('./' . $archive->getName())) {
				throw new ArchiveNotFoundException('Archive not found');
			}
			$stream = fopen('./' . $archive->getName(''), 'r');
			$write = fopen('./' . $archive->getName('zip'), 'w');
		} else {
			$folder = $backup->getBaseFolder();

			try {
				$encrypted = $folder->getFile($archive->getName());
				$stream = $encrypted->read();
			} catch (Exception $e) {
				throw new ArchiveNotFoundException('Archive not found');
			}

			try {
				$file = $folder->newFile($archive->getName('zip'));
				$write = $file->write();
			} catch (Exception $e) {
				throw new ArchiveNotFoundException('Zip file not created');
			}
		}

		$key = substr(sha1($backup->getEncryptionKey(), true), 0, 16);
		$this->encryptService->decryptFile($stream, $write, $key);
	}


	/**
	 * @param RestoringPoint $point
	 *
	 * @throws BackupAppCopyException
	 * @throws BackupScriptNotFoundException
	 * @throws ArchiveNotFoundException
	 */
	public function copyApp(RestoringPoint $point): void {
		$folder = $point->getBaseFolder();
		try {
			$file = $folder->newFile(self::APP_ZIP);
			$zip = new ZipStreamer(
				[
					'outstream' => $file->write(),
					'zip64' => false,
					'compress' => COMPR::DEFLATE,
					'level' => $this->assignCompressionLevel()
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

		$chunk = new RestoringChunk(self::APP_ZIP);
		$this->updateChecksum($point, $chunk);
		$data->addChunk($chunk);

		$chunk = new RestoringChunk(self::BACKUP_SCRIPT);
		$this->updateChecksum($point, $chunk);
		$data->addChunk($chunk);

		$point->addRestoringData($data);
	}


	/**
	 * @return int
	 */
	private function assignCompressionLevel(): int {
		try {
			$extension = new \ReflectionExtension('http');
		} catch (\ReflectionException $e) {
			return COMPR::NONE;
		}

		if (version_compare($extension->getVersion(), '0.10.0', '>=')) {
			return COMPR::MAXIMUM;
		}

		return COMPR::NONE;
	}


	/**
	 * @param RestoringPoint $point
	 * @param string $data
	 * @param string $chunk
	 *
	 * @return RestoringChunk
	 * @throws ChunkNotFoundException
	 */
	public function extractChunkFromRP(RestoringPoint $point, string $data, string $chunk): RestoringChunk {
		foreach ($point->getRestoringData() as $restoringData) {
			if ($restoringData->getName() !== $data) {
				continue;
			}

			foreach ($restoringData->getChunks() as $restoringChunk) {
				if ($restoringChunk->getName() === $chunk) {
					return $restoringChunk;
				}
			}
		}

		throw new ChunkNotFoundException();
	}


	/**
	 * @param RestoringPoint $point
	 * @param RestoringChunk $restoringChunk
	 */
	public function getChunkContent(RestoringPoint $point, RestoringChunk $restoringChunk): void {
		$folder = $point->getBaseFolder();
		try {
			$file = $folder->getFile($restoringChunk->getFilename());
			$restoringChunk->setContent(base64_encode($file->getContent()));
		} catch (NotFoundException | NotPermittedException $e) {
		}
	}


	/**
	 * @param RestoringPoint $point
	 * @param RestoringChunk $chunk
	 */
	public function saveChunkContent(RestoringPoint $point, RestoringChunk $chunk) {
		$folder = $point->getBaseFolder();
		try {
			try {
				$file = $folder->getFile($chunk->getFilename());
			} catch (NotFoundException $e) {
				$file = $folder->newFile($chunk->getFilename());
			}

			$file->putContent(base64_decode($chunk->getContent()));
		} catch (NotPermittedException | NotFoundException $e) {
		}
	}

}
