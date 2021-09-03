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


namespace OCA\Backup\Model;


use daita\MySmallPhpTools\Traits\TArrayTools;
use daita\MySmallPhpTools\Traits\TStringTools;
use JsonSerializable;

/**
 * Class BackupArchive
 *
 * @package OCA\Backup\Model
 */
class BackupArchive implements JsonSerializable {


	use TArrayTools;
	use TStringTools;


	/** @var string */
	private $name = '';

	/** @var string[] */
	private $files = [];

	/** @var int */
	private $count = 0;

	/** @var int */
	private $size = 0;

	/** @var string */
	private $checksum = '';

	/** @var string */
	private $encryptedChecksum = '';


	/**
	 * BackupArchive constructor.
	 */
	public function __construct() {
		$this->name = $this->uuid();
	}


	/**
	 * @param string $ext
	 *
	 * @return string
	 */
	public function getName(string $ext = ''): string {
		if ($ext === '') {
			return $this->name;
		}

		return $this->name . '.' . $ext;
	}

	/**
	 * @param string $name
	 *
	 * @return BackupArchive
	 */
	public function setName(string $name): BackupArchive {
		$this->name = $name;

		return $this;
	}


//	/**
//	 * @return int
//	 */
//	public function count(): int {
//		return sizeof($this->files);
//	}
//
	/**
	 * @return int
	 */
	public function getCount(): int {
		return $this->count;
	}

	/**
	 * @param int $count
	 *
	 * @return BackupArchive
	 */
	public function setCount(int $count = -1): BackupArchive {
		if ($count === -1) {
			$this->count = sizeof($this->files);
		} else {
			$this->count = $count;
		}

		return $this;
	}


	/**
	 * @return ArchiveFile[]
	 */
	public function getFiles(): array {
		return $this->files;
	}

	/**
	 * @param ArchiveFile[] $files
	 *
	 * @return BackupArchive
	 */
	public function setFiles(array $files): BackupArchive {
		$this->files = $files;

		return $this;
	}

	/**
	 * @param ArchiveFile $file
	 *
	 * @return BackupArchive
	 */
	public function addFile(ArchiveFile $file): BackupArchive {
		$this->files[] = $file;

		return $this;
	}


	/**
	 * @return int
	 */
	public function getSize(): int {
		return $this->size;
	}

	/**
	 * @param int $size
	 *
	 * @return BackupArchive
	 */
	public function setSize(int $size): BackupArchive {
		$this->size = $size;

		return $this;
	}


	/**
	 * @return string
	 */
	public function getChecksum(): string {
		return $this->checksum;
	}

	/**
	 * @param string $checksum
	 *
	 * @return BackupArchive
	 */
	public function setChecksum(string $checksum): BackupArchive {
		$this->checksum = $checksum;

		return $this;
	}


	/**
	 * @return string
	 */
	public function getEncryptedChecksum(): string {
		return $this->encryptedChecksum;
	}

	/**
	 * @param string $encryptedChecksum
	 *
	 * @return BackupArchive
	 */
	public function setEncryptedChecksum(string $encryptedChecksum): BackupArchive {
		$this->encryptedChecksum = $encryptedChecksum;

		return $this;
	}


	/**
	 * @param array $data
	 *
	 * @return BackupArchive
	 */
	public function import(array $data): BackupArchive {
		$this->setName($this->get('name', $data, ''))
//			 ->setFiles($this->getArray('files', $data, []))
			 ->setCount($this->getInt('count', $data, 0))
			 ->setSize($this->getInt('size', $data, 0))
			 ->setChecksum($this->get('checksum', $data, ''))
			 ->setEncryptedChecksum($this->get('encrypted', $data, ''));

		return $this;
	}


	/**
	 * @return array
	 */
	public function getResume(): array {
		return [
			'files' => $this->getFileS()
		];
	}


	/**
	 * @return array
	 */
	public function jsonSerialize(): array {
		return
			[
				'name'      => $this->getName(),
				'count'     => $this->getCount(),
				'size'      => $this->getSize(),
				'checksum'  => $this->getChecksum(),
				'encrypted' => $this->getEncryptedChecksum()
			];
	}

}

