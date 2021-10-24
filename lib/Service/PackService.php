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

use ArtificialOwl\MySmallPhpTools\Exceptions\SignatoryException;
use ArtificialOwl\MySmallPhpTools\Traits\Nextcloud\nc23\TNC23Logger;
use ArtificialOwl\MySmallPhpTools\Traits\TFileTools;
use ArtificialOwl\MySmallPhpTools\Traits\TStringTools;
use Exception;
use OCA\Backup\Db\PointRequest;
use OCA\Backup\Exceptions\ArchiveNotFoundException;
use OCA\Backup\Exceptions\EncryptionKeyException;
use OCA\Backup\Exceptions\PackDecryptException;
use OCA\Backup\Exceptions\RestoringChunkNotFoundException;
use OCA\Backup\Exceptions\RestoringChunkPartNotFoundException;
use OCA\Backup\Exceptions\RestoringPointLockException;
use OCA\Backup\Exceptions\RestoringPointNotInitiatedException;
use OCA\Backup\Exceptions\RestoringPointPackException;
use OCA\Backup\Model\RestoringChunk;
use OCA\Backup\Model\RestoringChunkPart;
use OCA\Backup\Model\RestoringData;
use OCA\Backup\Model\RestoringPoint;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;
use SodiumException;
use Throwable;
use ZipArchive;

/**
 * Class PackService
 *
 * @package OCA\Backup\Service
 */
class PackService {
	use TStringTools;
	use TNC23Logger;
	use TFileTools;


	public const CHUNK_ENTRY = 'pack';


	/** @var PointRequest */
	private $pointRequest;

	/** @var MetadataService */
	private $metadataService;

	/** @var RemoteStreamService */
	private $remoteStreamService;

	/** @var ChunkService */
	private $chunkService;

	/** @var EncryptService */
	private $encryptService;

	/** @var OutputService */
	private $outputService;

	/** @var ConfigService */
	private $configService;


	/**
	 * PackService constructor.
	 *
	 * @param PointRequest $pointRequest
	 * @param MetadataService $metadataService
	 * @param RemoteStreamService $remoteStreamService
	 * @param ChunkService $chunkService
	 * @param EncryptService $encryptService
	 * @param OutputService $outputService
	 * @param ConfigService $configService
	 */
	public function __construct(
		PointRequest $pointRequest,
		MetadataService $metadataService,
		RemoteStreamService $remoteStreamService,
		ChunkService $chunkService,
		EncryptService $encryptService,
		OutputService $outputService,
		ConfigService $configService
	) {
		$this->pointRequest = $pointRequest;
		$this->metadataService = $metadataService;
		$this->remoteStreamService = $remoteStreamService;
		$this->chunkService = $chunkService;
		$this->encryptService = $encryptService;
		$this->outputService = $outputService;
		$this->configService = $configService;
	}


	/**
	 * @param RestoringPoint $point
	 * @param bool $force
	 *
	 * @throws RestoringPointPackException
	 * @throws RestoringPointLockException
	 */
	public function packPoint(RestoringPoint $point, bool $force = false): void {
		if ($point->isStatus(RestoringPoint::STATUS_PACKED)
			&& !$point->isStatus(RestoringPoint::STATUS_PACKING)) {
			throw new RestoringPointPackException('restoring point is already packed');
		}

		if ($point->isStatus(RestoringPoint::STATUS_ISSUE)) {
			if (!$force && $point->getNotes()->gInt('pack_date') > time() - 3600 * 6) {
				throw new RestoringPointPackException('restoring point already failed few hours ago');
			}
		}

		$this->metadataService->isLock($point);
		$this->metadataService->lock($point);

		$point->addStatus(RestoringPoint::STATUS_PACKING);
		$this->metadataService->updateStatus($point);

		foreach ($point->getRestoringData() as $data) {
			foreach ($data->getChunks() as $chunk) {
				if ($chunk->hasParts()) {
					continue;
				}

				try {
					$oldChunk = null;
					if ($data->getType() === RestoringData::INTERNAL_DATA) {
						$chunkPart = new RestoringChunkPart($chunk->getFilename());
						$chunkPart->setChecksum($this->chunkService->getChecksum($point, $chunk));
						$chunk->addPart($chunkPart);
					} else {
						$oldChunk = clone $chunk;
						$this->packChunk($point, $chunk);
					}

					$this->pointRequest->update($point, true);
					$this->metadataService->saveMetadata($point);
					if (!is_null($oldChunk)) {
						$this->chunkService->removeChunkFile($point, $oldChunk);
					}
				} catch (Throwable $t) {
					$point->setStatus(RestoringPoint::STATUS_ISSUE)
						  ->getNotes()
						  ->s('pack_error', $t->getMessage())
						  ->sInt('pack_date', time());

//					$this->pointRequest->update($point);
					$this->metadataService->unlock($point);
					throw new RestoringPointPackException(
						'issue on chunk ' . $chunk->getName() . ' - ' . $t->getMessage()
					);
				}
			}
		}

//		$this->removeOldChunkFiles($point, $oldChunks);

		$point->removeStatus(RestoringPoint::STATUS_PACKING)
			  ->addStatus(RestoringPoint::STATUS_PACKED)
			  ->getNotes()
			  ->u('pack_error')
			  ->u('pack_date');

		try {
			$this->remoteStreamService->signPoint($point);
		} catch (SignatoryException $e) {
		}

		$this->pointRequest->update($point, true);
		try {
			$this->metadataService->saveMetadata($point);
		} catch (NotFoundException | NotPermittedException $e) {
			$this->e(
				$e,
				[
					'point' => $point,
					'message' => 'Were not able to store data to local metadata file'
				]
			);
		}

		$this->metadataService->unlock($point);
	}


	/**
	 * @param RestoringPoint $point
	 * @param RestoringChunk $chunk
	 *
	 * @throws EncryptionKeyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws RestoringPointNotInitiatedException
	 * @throws SodiumException
	 * @throws Throwable
	 */
	public function packChunk(RestoringPoint $point, RestoringChunk $chunk): void {
		$temp = $this->packChunkTempFile($point, $chunk);
		$temp = $this->wrapPackChunkCompress($point, $temp);
		$parts = $this->wrapPackExplode($temp);
		$parts = $this->wrapPackEncrypt($point, $chunk, $parts);

		$this->wrapStoreParts($point, $chunk, $parts);
	}


	/**
	 * @param RestoringPoint $point
	 * @param RestoringChunk $chunk
	 *
	 * @return string
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws RestoringPointNotInitiatedException
	 * @throws Throwable
	 */
	private function packChunkTempFile(RestoringPoint $point, RestoringChunk $chunk): string {
		$tmpPath = '';
		try {
			$orig = $this->chunkService->getChunkResource($point, $chunk);
			$tmpPath = $this->configService->getTempFileName();

			$read = $orig->read();
			$write = fopen($tmpPath, 'wb');
			while (($r = fgets($read, 4096)) !== false) {
				fputs($write, $r);
			}
			fclose($write);
		} catch (Throwable $t) {
			if ($tmpPath !== '') {
				unlink($tmpPath);
			}

			throw $t;
		}

		return $tmpPath;
	}


	/**
	 * @param RestoringPoint $point
	 * @param string $filename
	 *
	 * @return string
	 * @throws Throwable
	 */
	private function wrapPackChunkCompress(RestoringPoint $point, string $filename): string {
		if ($this->configService->getAppValueBool(ConfigService::PACK_COMPRESS)) {
			try {
				$zip = $this->packChunkCompress($filename);
				unlink($filename);
				$filename = $zip;
			} catch (Throwable $t) { // in case of crash from the ZipArchive
				unlink($filename);
				throw $t;
			}

			$point->addStatus(RestoringPoint::STATUS_COMPRESSED);
		}

		return $filename;
	}

	/**
	 * @param string $filename
	 *
	 * @return string
	 */
	private function packChunkCompress(string $filename): string {
		$tmpPath = $this->configService->getTempFileName();

		$zip = new ZipArchive();
		$zip->open($tmpPath, ZipArchive::CREATE);

		$zip->addFile($filename, self::CHUNK_ENTRY);
		$zip->setCompressionIndex(0, ZipArchive::CM_DEFLATE);
		$zip->close();

		return $tmpPath;
	}


	/**
	 * @param string $filename
	 *
	 * @return RestoringChunkPart[]
	 * @throws Throwable
	 */
	private function wrapPackExplode(string $filename): array {
		try {
			$parts = $this->packExplode($filename);
		} catch (Throwable $t) { // in case of issue during the exploding of the file
			unlink($filename);
			throw $t;
		}

		unlink($filename);

		return $parts;
	}

	/**
	 * @param string $filename
	 *
	 * @return RestoringChunkPart[]
	 * @throws Throwable
	 */
	private function packExplode(string $filename): array {
		$read = fopen($filename, 'rb');
		$maxSize = $this->configService->getAppValueInt(ConfigService::CHUNK_PART_SIZE) * 1024 * 1024;

		$i = 1;
		$parts = [];
		while (true) {
			try {
				$tmpPath = $this->configService->getTempFileName();

				$write = fopen($tmpPath, 'wb');
				$chunkPart = new RestoringChunkPart($tmpPath, $i++);
				$size = 0;
				while (true) {
					$r = fgets($read, 4096);
					if ($r === false) {
						fclose($write);
						$parts[] = $chunkPart->setChecksum($this->getTempChecksum($tmpPath));

						return $parts;
					}

					$size += strlen($r);
					fputs($write, $r);

					if ($size >= $maxSize) {
						fclose($write);
						$parts[] = $chunkPart->setChecksum($this->getTempChecksum($tmpPath));

						break;
					}
				}
			} catch (Throwable $t) {
				foreach ($parts as $item) {
					unlink($item->getName());
				}
				throw $t;
			}
		}
	}


	/**
	 * @param RestoringPoint $point
	 * @param RestoringChunk $chunk
	 * @param RestoringChunkPart[] $parts
	 *
	 * @return array
	 * @throws ArchiveNotFoundException
	 * @throws EncryptionKeyException
	 * @throws SodiumException
	 */
	private function wrapPackEncrypt(RestoringPoint $point, RestoringChunk $chunk, array $parts): array {
		if ($this->configService->getAppValueBool(ConfigService::PACK_ENCRYPT)) {
			try {
				$encrypted = $this->packEncrypt($parts, $chunk->getName());
				foreach ($parts as $item) {
					unlink($item->getName());
				}
				$parts = $encrypted;
			} catch (Throwable $t) { // in case of crash from the ZipArchive
				foreach ($parts as $item) {
					try {
						unlink($item->getName());
					} catch (Throwable $t) {
					}
				}
				throw $t;
			}

			$point->addStatus(RestoringPoint::STATUS_ENCRYPTED);
		}

		return $parts;
	}

	/**
	 * @param RestoringChunkPart[] $parts
	 * @param string $chunkName
	 *
	 * @return array
	 * @throws ArchiveNotFoundException
	 * @throws EncryptionKeyException
	 * @throws SodiumException
	 */
	private function packEncrypt(array $parts, string $chunkName): array {
		$encrypted = [];

		foreach ($parts as $item) {
			$new = clone $item;
			$new->setName($this->configService->getTempFileName());
			$algorithm = $this->encryptService->encryptFile($item->getName(), $new->getName(), $chunkName);
			$new->setEncrypted(true, $algorithm)
				->setEncryptedChecksum($this->getTempChecksum($new->getName()));

			$encrypted[] = $new;
		}

		return $encrypted;
	}


	/**
	 * @param RestoringPoint $point
	 * @param RestoringChunk $chunk
	 * @param RestoringChunkPart[] $parts
	 *
	 * @throws NotPermittedException
	 * @throws RestoringPointNotInitiatedException
	 */
	private function wrapStoreParts(RestoringPoint $point, RestoringChunk $chunk, array $parts): void {
		try {
			$this->storeParts($point, $chunk, $parts);
		} catch (Throwable $t) {
			foreach ($parts as $item) {
				try {
					unlink($item->getName());
				} catch (Throwable $t) {
				}
			}
			throw $t;
		}

		foreach ($parts as $item) {
			try {
				unlink($item->getName());
			} catch (Throwable $t) {
			}
		}
	}

	/**
	 * @param RestoringPoint $point
	 * @param RestoringChunk $chunk
	 * @param array $parts
	 *
	 * @throws NotPermittedException
	 * @throws RestoringPointNotInitiatedException
	 */
	private function storeParts(RestoringPoint $point, RestoringChunk $chunk, array $parts): void {
		$folder = $this->getPackFolder($point, $chunk);
		foreach ($parts as $temp) {
			$item = clone $temp;
			$prefix = str_pad((string)$item->getOrder(), 5, '0', STR_PAD_LEFT) . '-';
			$filename = $prefix . $this->token();
			$file = $folder->newFile($filename);
			$read = fopen($item->getName(), 'rb');
			$write = $file->write();
			while (($r = fgets($read, 4096)) !== false) {
				fputs($write, $r);
			}
			fclose($write);
			fclose($read);

			$item->setName($filename);
			$chunk->addPart($item);
		}
	}


	/**
	 * @param RestoringPoint $point
	 * @param string $partName
	 * @param string $chunkName
	 * @param string $dataName
	 *
	 * @return RestoringChunkPart
	 * @throws RestoringChunkPartNotFoundException
	 * @throws RestoringChunkNotFoundException
	 */
	public function getPartFromPoint(
		RestoringPoint $point,
		string $partName,
		string $chunkName,
		string $dataName
	): RestoringChunkPart {
		$chunk = $this->chunkService->getChunkFromRP($point, $chunkName, $dataName);

		return $this->getPartFromChunk($chunk, $partName);
	}


	/**
	 * @param RestoringChunk $chunk
	 * @param $partName
	 *
	 * @return RestoringChunkPart
	 * @throws RestoringChunkPartNotFoundException
	 */
	public function getPartFromChunk(RestoringChunk $chunk, $partName): RestoringChunkPart {
		foreach ($chunk->getParts() as $part) {
			if ($part->getName() === $partName) {
				return $part;
			}
		}

		throw new RestoringChunkPartNotFoundException();
	}


	/**
	 * @param RestoringPoint $point
	 * @param RestoringChunk $chunk
	 * @param RestoringChunkPart $part
	 *
	 * @throws RestoringPointNotInitiatedException
	 */
	public function getChunkPartContent(
		RestoringPoint $point,
		RestoringChunk $chunk,
		RestoringChunkPart $part
	): void {
		try {
			$file = $this->getPartResource($point, $chunk, $part);
			$part->setContent(base64_encode($file->getContent()));
		} catch (NotFoundException | NotPermittedException $e) {
		}
	}


	/**
	 * @param RestoringPoint $point
	 * @param RestoringChunk $chunk
	 * @param RestoringChunkPart $part
	 *
	 * @return ISimpleFile
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws RestoringPointNotInitiatedException
	 */
	public function getPartResource(
		RestoringPoint $point,
		RestoringChunk $chunk,
		RestoringChunkPart $part
	): ISimpleFile {
		$folder = $this->getPackFolder($point, $chunk);

		return $folder->getFile($part->getName());
	}


	/**
	 * @param RestoringPoint $point
	 *
	 * @throws ArchiveNotFoundException
	 * @throws EncryptionKeyException
	 * @throws NotPermittedException
	 * @throws RestoringPointLockException
	 * @throws RestoringPointNotInitiatedException
	 * @throws RestoringPointPackException
	 * @throws SodiumException
	 * @throws Throwable
	 */
	public function unpackPoint(RestoringPoint $point): void {
		if (!$point->isStatus(RestoringPoint::STATUS_PACKED)
			&& !$point->isStatus(RestoringPoint::STATUS_PACKING)) {
			throw new RestoringPointPackException('restoring point is not packed');
		}

		$this->metadataService->isLock($point);
		$this->metadataService->lock($point);

		$point->addStatus(RestoringPoint::STATUS_PACKING);
		$this->metadataService->updateStatus($point);

		$completed = true;
		foreach ($point->getRestoringData() as $data) {
			if ($data->getType() === RestoringData::INTERNAL_DATA) {
				continue;
			}

			foreach ($data->getChunks() as $chunk) {
				try {
					$oldChunk = clone $chunk;
					$this->unpackChunk($point, $chunk);

					$this->pointRequest->update($point, true);
					$this->metadataService->saveMetadata($point);
					try {
						$this->removeOldChunkPartFiles($point, $oldChunk);
					} catch (RestoringPointNotInitiatedException | NotPermittedException $e) {
					}
				} catch (Throwable $t) {
					$completed = false;
				}
			}
		}

		if ($completed) {
			$point->setStatus(RestoringPoint::STATUS_UNPACKED)
				  ->removeStatus(RestoringPoint::STATUS_PACKING)
				  ->unsetNotes();

			try {
				$this->remoteStreamService->signPoint($point);
				$this->pointRequest->update($point, true);
				$this->metadataService->saveMetadata($point);
			} catch (SignatoryException | NotFoundException | NotPermittedException $e) {
			}
		}

		$this->metadataService->unlock($point);
	}


	/**
	 * @param RestoringPoint $point
	 * @param RestoringChunk $chunk
	 *
	 * @return void
	 * @throws ArchiveNotFoundException
	 * @throws EncryptionKeyException
	 * @throws NotPermittedException
	 * @throws RestoringPointNotInitiatedException
	 * @throws SodiumException
	 * @throws Throwable
	 */
	public function unpackChunk(RestoringPoint $point, RestoringChunk $chunk): void {
		$parts = $this->putOutParts($point, $chunk);
		$parts = $this->wrapPackDecrypt($point, $chunk, $parts);
		$temp = $this->wrapPackImplode($parts);
		$temp = $this->wrapPackChunkExtract($point, $temp);
		$this->wrapRecreateChunk($point, $chunk, $temp);

		$chunk->setParts([]);
	}


	/**
	 * @param RestoringPoint $point
	 * @param RestoringChunk $chunk
	 *
	 * @return RestoringChunkPart[]
	 * @throws NotPermittedException
	 * @throws RestoringPointNotInitiatedException
	 */
	private function putOutParts(RestoringPoint $point, RestoringChunk $chunk): array {
		$temp = [];
		$folder = $this->getPackFolder($point, $chunk);
		foreach ($chunk->getParts() as $part) {
			try {
				$file = $folder->getFile($part->getName());
			} catch (NotFoundException $e) {
				echo 'NotFound' . "\n";
				continue;
			}

			$read = $file->read();
			$new = clone $part;
			$new->setName($this->configService->getTempFileName());
			$write = fopen($new->getName(), 'wb');
			while (($r = fgets($read, 4096)) !== false) {
				fputs($write, $r);
			}

			fclose($write);
			fclose($read);

			$temp[] = $new;
		}

		return $temp;
	}


	/**
	 * @param RestoringPoint $point
	 * @param RestoringChunk $chunk
	 * @param RestoringChunkPart[] $parts
	 *
	 * @return RestoringChunkPart[]
	 * @throws Throwable
	 */
	private function wrapPackDecrypt(RestoringPoint $point, RestoringChunk $chunk, array $parts): array {
		if ($this->configService->getAppValueBool(ConfigService::PACK_ENCRYPT)) {
			try {
				$encrypted = $this->packDecrypt($parts, $chunk);
				foreach ($parts as $item) {
					unlink($item->getName());
				}
				$parts = $encrypted;
			} catch (Throwable $t) { // in case of crash from the ZipArchive
				foreach ($parts as $item) {
					try {
						unlink($item->getName());
					} catch (Throwable $t) {
					}
				}
				throw $t;
			}

			$point->removeStatus(RestoringPoint::STATUS_ENCRYPTED);
		}

		return $parts;
	}


	/**
	 * @param array $parts
	 * @param RestoringChunk $chunk
	 *
	 * @return RestoringChunkPart[]
	 */
	private function packDecrypt(array $parts, RestoringChunk $chunk): array {
		$decrypted = [];
		foreach ($parts as $part) {
			$new = clone $part;
			$new->setName($this->configService->getTempFileName())
				->setAlgorithm('');
			try {
				$this->encryptService->decryptFile(
					$part->getName(),
					$new->getName(),
					$chunk->getName(),
					$part->getAlgorithm()
				);
				// TODO checksums
//				echo '-checksum: ' . $this->getTempChecksum($new->getName()) . "\n";
			} catch (PackDecryptException
			| SodiumException
			| EncryptionKeyException $e) {
				echo '### ' . $e->getMessage();
			}

			$decrypted[] = $new;
		}

		return $decrypted;
	}

	/**
	 * @param RestoringChunkPart[] $parts
	 *
	 * @return string
	 * @throws Throwable
	 */
	private function wrapPackImplode(array $parts): string {
		try {
			$filename = $this->packImplode($parts);
			foreach ($parts as $item) {
				try {
					unlink($item->getName());
				} catch (Throwable $t) {
				}
			}

			return $filename;
		} catch (Throwable $t) { // in case of issue during the exploding of the file
			foreach ($parts as $item) {
				try {
					unlink($item->getName());
				} catch (Throwable $t) {
				}
			}
			throw $t;
		}
	}


	/**
	 * @param RestoringChunkPart[] $parts
	 *
	 * @return string
	 */
	private function packImplode(array $parts): string {
		$tmpPath = $this->configService->getTempFileName();
		$write = fopen($tmpPath, 'wb');

		foreach ($parts as $part) {
			$read = fopen($part->getName(), 'rb');
			while (($r = fgets($read, 4096)) !== false) {
				fputs($write, $r);
			}
			fclose($read);
		}
		fclose($write);

		return $tmpPath;
	}


	/**
	 * @param RestoringPoint $point
	 * @param string $filename
	 *
	 * @return string
	 * @throws Throwable
	 */
	private function wrapPackChunkExtract(RestoringPoint $point, string $filename): string {
		if ($this->configService->getAppValueBool(ConfigService::PACK_COMPRESS)) {
			try {
				$zip = $this->packChunkExtract($filename);
				unlink($filename);
				$filename = $zip;
			} catch (Throwable $t) { // in case of crash from the ZipArchive
				unlink($filename);
				throw $t;
			}

			$point->removeStatus(RestoringPoint::STATUS_COMPRESSED);
		}

		return $filename;
	}

	/**
	 * @param string $zipName
	 *
	 * @return string
	 */
	private function packChunkExtract(string $zipName): string {
		$tmp = $this->configService->getTempFileName();
		$write = fopen($tmp, 'wb');

		$zip = new ZipArchive();
		$zip->open($zipName);
		$read = $zip->getStream(self::CHUNK_ENTRY);
		while (($r = fgets($read, 4096)) !== false) {
			fputs($write, $r);
		}

		fclose($read);
		$zip->close();
		fclose($write);

		return $tmp;
	}


	/**
	 * @param RestoringPoint $point
	 * @param RestoringChunk $chunk
	 * @param string $path
	 *
	 * @return ISimpleFolder
	 * @throws NotPermittedException
	 * @throws RestoringPointNotInitiatedException
	 */
	public function getPackFolder(
		RestoringPoint $point,
		RestoringChunk $chunk,
		string &$path = '',
		string &$sub = ''
	): ISimpleFolder {
		if (!$point->hasBaseFolder() || !$point->hasAppDataRootWrapper()) {
			throw new RestoringPointNotInitiatedException('Restoring Point is not initiated');
		}

		$folder = $point->getBaseFolder();
		$sub = $chunk->getPath();
		if ($sub !== '') {
			$path = '/' . $folder->getName() . '/' . $sub;
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
	 * @param RestoringPoint $point
	 * @param RestoringChunk $chunk
	 * @param string $temp
	 *
	 * @throws NotPermittedException
	 * @throws RestoringPointNotInitiatedException
	 */
	private function wrapRecreateChunk(RestoringPoint $point, RestoringChunk $chunk, string $temp): void {
		try {
			$this->recreateChunk($point, $chunk, $temp);
			unlink($temp);
		} catch (Throwable $t) {
			unlink($temp);
			throw $t;
		}
	}


	/**
	 * @param RestoringPoint $point
	 * @param RestoringChunk $chunk
	 * @param string $temp
	 *
	 * @throws NotPermittedException
	 * @throws RestoringPointNotInitiatedException
	 */
	private function recreateChunk(RestoringPoint $point, RestoringChunk $chunk, string $temp): void {
		$read = fopen($temp, 'rb');
		$folder = $this->chunkService->getChunkFolder($point, $chunk);
		try {
			$file = $folder->getFile($chunk->getFilename());
		} catch (NotFoundException $e) {
			$file = $folder->newFile($chunk->getFilename());
		}

		$write = $file->write();
		while (($r = fgets($read, 4096)) !== false) {
			fputs($write, $r);
		}

		fclose($write);
		fclose($read);
	}


	/**
	 * @param RestoringPoint $point
	 * @param RestoringChunk $chunk
	 *
	 * @throws NotPermittedException
	 * @throws RestoringPointNotInitiatedException
	 */
	private function removeOldChunkPartFiles(RestoringPoint $point, RestoringChunk $chunk): void {
		$folder = $this->getPackFolder($point, $chunk);
		foreach ($chunk->getParts() as $part) {
			try {
				$file = $folder->getFile($part->getName());
				$file->delete();
			} catch (Exception $e) {
			}
		}
	}


	/**
	 * @param RestoringPoint $point
	 * @param RestoringChunk[] $chunks
	 */
	private function removeOldChunkFiles(RestoringPoint $point, array $chunks): void {
		foreach ($chunks as $chunk) {
			try {
				$this->chunkService->removeChunkFile($point, $chunk);
			} catch (Exception $e) {
				continue;
			}
		}
	}


	/**
	 * @param string $tmpPath
	 *
	 * @return string
	 * @throws ArchiveNotFoundException
	 */
	public function getTempChecksum(string $tmpPath): string {
		$stream = fopen($tmpPath, 'rb');
		if (is_bool($stream)) {
			throw new ArchiveNotFoundException('temp file ' . $tmpPath . ' not found nor valid');
		}

		return $this->getChecksumFromStream($stream);
	}


	/**
	 * @param RestoringPoint $point
	 * @param RestoringChunk $chunk
	 * @param RestoringChunkPart $part
	 *
	 * @return string
	 * @throws ArchiveNotFoundException
	 */
	public function getChecksum(
		RestoringPoint $point,
		RestoringChunk $chunk,
		RestoringChunkPart $part
	): string {
		try {
			$path = '';
			if ($point->isPackage()) {
				throw new Exception('not managed yet, use documentation');
			} else {
				$folder = $this->getPackFolder($point, $chunk, $path);
				$file = $folder->getFile($part->getName());
				$stream = $file->read();
			}
		} catch (Exception $e) {
			throw new ArchiveNotFoundException(
				'Part ' . $part->getName() . ' from ' . $chunk->getFilename() . ' not found. path: ' . $path
			);
		}

		if (is_bool($stream)) {
			throw new ArchiveNotFoundException('Chunk ' . $chunk->getFilename() . ' not valid');
		}

		return $this->getChecksumFromStream($stream);
	}


	/**
	 * @throws NotPermittedException
	 * @throws RestoringPointNotInitiatedException
	 */
	public function saveChunkPartContent(
		RestoringPoint $point,
		RestoringChunk $chunk,
		RestoringChunkPart $part
	) {
		if ($part->getContent() === '') {
			return;
		}

		$folder = $this->getPackFolder($point, $chunk);
		try {
			try {
				$file = $folder->getFile($part->getName());
			} catch (NotFoundException $e) {
				$file = $folder->newFile($part->getName());
			}

			$file->putContent(base64_decode($part->getContent()));
		} catch (NotPermittedException | NotFoundException $e) {
		}
	}
}
