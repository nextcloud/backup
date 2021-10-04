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


use ArtificialOwl\MySmallPhpTools\Traits\TStringTools;
use OCA\Backup\Exceptions\EncryptionKeyException;
use OCA\Backup\Exceptions\RestoringPointNotInitiatedException;
use OCA\Backup\Model\RestoringChunk;
use OCA\Backup\Model\RestoringChunkPart;
use OCA\Backup\Model\RestoringData;
use OCA\Backup\Model\RestoringPoint;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
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


	const PACK_SIZE = 1000000;
	const CHUNK_ENTRY = 'pack';


	/** @var PointService */
	private $pointService;

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
	 * @param PointService $pointService
	 * @param ChunkService $chunkService
	 * @param EncryptService $encryptService
	 * @param OutputService $outputService
	 * @param ConfigService $configService
	 */
	public function __construct(
		PointService $pointService,
		ChunkService $chunkService,
		EncryptService $encryptService,
		OutputService $outputService,
		ConfigService $configService
	) {
		$this->pointService = $pointService;
		$this->chunkService = $chunkService;
		$this->encryptService = $encryptService;
		$this->outputService = $outputService;
		$this->configService = $configService;
	}


	/**
	 * @param RestoringPoint $point
	 */
	public function packPoint(RestoringPoint $point): void {
		foreach ($point->getRestoringData() as $data) {
			if ($data->getType() === RestoringData::INTERNAL_DATA) {
				continue;
			}

			foreach ($data->getChunks() as $chunk) {
				try {
					$this->packChunk($point, $chunk);
				} catch (RestoringPointNotInitiatedException | NotPermittedException | NotFoundException $e) {
				}
			}
		}

		$this->pointService->update($point, true);
	}


	/**
	 * @param RestoringPoint $point
	 * @param RestoringChunk $chunk
	 *
	 * @throws RestoringPointNotInitiatedException
	 * @throws NotPermittedException
	 * @throws NotFoundException
	 */
	public function packChunk(RestoringPoint $point, RestoringChunk $chunk): void {
		$temp = $this->packChunkTempFile($point, $chunk);
		$zip = $this->packChunkCompress($temp);
		unlink($temp);

		$parts = $this->packExplode($zip);
		unlink($zip);

		$encrypted = $this->packEncrypt($parts);
		foreach ($parts as $item) {
			unlink($item);
		}

		$this->storeParts($point, $chunk, $encrypted);
		foreach ($encrypted as $item) {
			unlink($item);
		}

		$this->chunkService->removeChunkFile($point, $chunk);
	}


	/**
	 * @param RestoringPoint $point
	 * @param RestoringChunk $chunk
	 *
	 * @return string
	 */
	private function packChunkTempFile(RestoringPoint $point, RestoringChunk $chunk): string {
		$tmpPath = '';
		try {
			$tmpPath = $this->configService->getTempFileName();

			$orig = $this->chunkService->getChunkResource($point, $chunk);
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
		}

		return $tmpPath;
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
	 * @return array
	 */
	private function packExplode(string $filename): array {
		$read = fopen($filename, 'rb');
		$parts = [];

		while (true) {
			$tmpPath = $this->configService->getTempFileName();

			$write = fopen($tmpPath, 'wb');
			$parts[] = $tmpPath;
			$size = 0;
			while (true) {
				$r = fgets($read, 4096);
				if ($r === false) {
					fclose($write);

					return $parts;
				}

				$size += strlen($r);
				fputs($write, $r);

				if ($size >= self::PACK_SIZE) {
					fclose($write);
					break;
				}
			}
		}
	}


	/**
	 * @param array $parts
	 *
	 * @return array
	 */
	private function packEncrypt(array $parts): array {
		$encrypted = [];

		foreach ($parts as $filename) {
			$tmp = $this->configService->getTempFileName();
			try {
				$this->encryptService->encryptFile($filename, $tmp);
			} catch (SodiumException
			| EncryptionKeyException $e) {
				echo '### ' . $e->getMessage();
			}

			$encrypted[] = $tmp;
		}

		return $encrypted;
	}


	/**
	 * @param RestoringPoint $point
	 * @param RestoringChunk $chunk
	 * @param array $parts
	 *
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws RestoringPointNotInitiatedException
	 */
	private function storeParts(RestoringPoint $point, RestoringChunk $chunk, array $parts): void {
		$folder = $this->getPackFolder($point, $chunk);
		foreach ($parts as $tmp) {
			$part = new RestoringChunkPart($this->token());
			$file = $folder->newFile($part->getName());
			$read = fopen($tmp, 'rb');
			$write = $file->write();
			while (($r = fgets($read, 4096)) !== false) {
				fputs($write, $r);
			}
			fclose($write);
			fclose($read);

			$chunk->addPart($part);
		}
	}


	/**
	 * @param RestoringPoint $point
	 */
	public function unpackPoint(RestoringPoint $point): void {
		foreach ($point->getRestoringData() as $data) {
			if ($data->getType() === RestoringData::INTERNAL_DATA) {
				continue;
			}

			foreach ($data->getChunks() as $chunk) {
				try {
					$this->unpackChunk($point, $chunk);
				} catch (RestoringPointNotInitiatedException | NotPermittedException $e) {
				}
			}
		}
	}


	/**
	 * @param RestoringPoint $point
	 * @param RestoringChunk $chunk
	 *
	 * @throws RestoringPointNotInitiatedException
	 * @throws NotPermittedException
	 */
	public function unpackChunk(RestoringPoint $point, RestoringChunk $chunk): void {
		$parts = $this->putOutParts($point, $chunk);
		$decrypted = $this->packDecrypt($parts);
		foreach ($parts as $part) {
			unlink($part);
		}

		$zip = $this->packImplode($decrypted);
		foreach ($decrypted as $item) {
			unlink($item);
		}

		$temp = $this->packChunkExtract($zip);
		unlink($zip);

		$this->recreateChunk($point, $chunk, $temp);
		unlink($temp);
	}


	/**
	 * @param RestoringPoint $point
	 * @param RestoringChunk $chunk
	 *
	 * @return array
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
			$tmp = $this->configService->getTempFileName();
			$write = fopen($tmp, 'wb');
			while (($r = fgets($read, 4096)) !== false) {
				fputs($write, $r);
			}

			fclose($write);
			fclose($read);

			$temp[] = $tmp;
		}

		return $temp;
	}


	/**
	 * @param array $parts
	 *
	 * @return array
	 */
	private function packDecrypt(array $parts): array {
		$decrypted = [];
		foreach ($parts as $filename) {
			$tmp = $this->configService->getTempFileName();
			try {
				$this->encryptService->decryptFile($filename, $tmp);
			} catch (SodiumException
			| EncryptionKeyException $e) {
				echo '### ' . $e->getMessage();
			}

			$decrypted[] = $tmp;
		}

		return $decrypted;
	}


	/**
	 * @param array $parts
	 *
	 * @return string
	 */
	private function packImplode(array $parts): string {
		$tmp = $this->configService->getTempFileName();
		$write = fopen($tmp, 'wb');

		foreach ($parts as $part) {
			$read = fopen($part, 'rb');
			while (($r = fgets($read, 4096)) !== false) {
				fputs($write, $r);
			}
			fclose($read);
		}
		fclose($write);

		return $tmp;
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
	 *
	 * @return ISimpleFolder
	 * @throws NotPermittedException
	 * @throws RestoringPointNotInitiatedException
	 */
	public function getPackFolder(RestoringPoint $point, RestoringChunk $chunk): ISimpleFolder {
		if (!$point->hasBaseFolder() || !$point->hasRootFolder()) {
			throw new RestoringPointNotInitiatedException('Restoring Point is not initiated');
		}

		$folder = $point->getBaseFolder();
		if ($chunk->getPath() !== '') {
			$root = $point->getRootFolder();
			try {
				$folder = $root->getFolder(
					'/' . $folder->getName() . '/' . $chunk->getPath() . '/' . $chunk->getName()
				);
			} catch (NotFoundException $e) {
				$folder = $root->newFolder(
					'/' . $folder->getName() . '/' . $chunk->getPath() . '/' . $chunk->getName()
				);
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

}
