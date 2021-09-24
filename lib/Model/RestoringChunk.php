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


use ArtificialOwl\MySmallPhpTools\IDeserializable;
use ArtificialOwl\MySmallPhpTools\Traits\TArrayTools;
use ArtificialOwl\MySmallPhpTools\Traits\TStringTools;
use JsonSerializable;


/**
 * Class RestoringChunk
 *
 * @package OCA\Backup\Model
 */
class RestoringChunk implements JsonSerializable, IDeserializable {


	use TArrayTools;
	use TStringTools;


	/** @var string */
	private $name = '';

	/** @var string */
	private $content = '';

	/** @var string[] */
	private $files = [];

	/** @var int */
	private $count = 0;

	/** @var int */
	private $size = 0;

	/** @var string */
	private $checksum = '';

	/** @var bool */
	private $encrypted = false;

	/** @var bool */
	private $staticName = false;

	/** @var string */
	private $encryptedChecksum = '';


	/**
	 * only add $name for specific Chunk.
	 *
	 * RestoringChunk constructor.
	 */
	public function __construct(string $name = '') {
		if ($name === '') {
			$name = $this->uuid();
		} else {
			$this->staticName = true;
		}

		$this->name = $name;
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
	 * @return RestoringChunk
	 */
	public function setName(string $name): self {
		$this->name = $name;

		return $this;
	}


	/**
	 * @param bool $staticName
	 *
	 * @return RestoringChunk
	 */
	public function setStaticName(bool $staticName): self {
		$this->staticName = $staticName;

		return $this;
	}

	/**
	 * @return bool
	 */
	public function isStaticName(): bool {
		return $this->staticName;
	}


	/**
	 * @return string
	 */
	public function getFilename(): string {
		if ($this->isStaticName()) {
			return $this->getName();
		}

		if ($this->isEncrypted()) {
			return $this->getName();
		}

		return $this->getName() . '.zip';
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
	 * @return RestoringChunk
	 */
	public function setCount(int $count = -1): self {
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
	 * @return RestoringChunk
	 */
	public function setFiles(array $files): self {
		$this->files = $files;

		return $this;
	}

	/**
	 * @param ArchiveFile $file
	 *
	 * @return RestoringChunk
	 */
	public function addFile(ArchiveFile $file): self {
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
	 * @return RestoringChunk
	 */
	public function setSize(int $size): self {
		$this->size = $size;

		return $this;
	}


	/**
	 * @param bool $encrypted
	 *
	 * @return RestoringChunk
	 */
	public function setEncrypted(bool $encrypted): self {
		$this->encrypted = $encrypted;

		return $this;
	}

	/**
	 * @return bool
	 */
	public function isEncrypted(): bool {
		return $this->encrypted;
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
	 * @return RestoringChunk
	 */
	public function setChecksum(string $checksum): self {
		$this->checksum = $checksum;

		return $this;
	}

	/**
	 * @param string $checksum
	 *
	 * @return bool
	 */
	public function compareChecksum(string $checksum): bool {
		if ($this->isEncrypted()) {
			return ($this->getEncryptedChecksum() === $checksum);
		}

		return ($this->getChecksum() === $checksum);
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
	 * @return RestoringChunk
	 */
	public function setEncryptedChecksum(string $encryptedChecksum): self {
		$this->encryptedChecksum = $encryptedChecksum;

		return $this;
	}

	/**
	 * @param string $content
	 *
	 * @return RestoringChunk
	 */
	public function setContent(string $content): self {
		$this->content = $content;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getContent(): string {
		return $this->content;
	}


	/**
	 * @param array $data
	 *
	 * @return RestoringChunk
	 */
	public function import(array $data): IDeserializable {
		$this->setName($this->get('name', $data))
//			 ->setFiles($this->getArray('files', $data, []))
			 ->setCount($this->getInt('count', $data))
			 ->setSize($this->getInt('size', $data))
			 ->setContent($this->get('content', $data))
			 ->setEncrypted($this->getBool('encrypted', $data))
			 ->setStaticName($this->getBool('staticName', $data))
			 ->setChecksum($this->get('checksum', $data))
			 ->setEncryptedChecksum($this->get('encryptedChecksum', $data));

		return $this;
	}


	/**
	 * @return array
	 */
	public function getResume(): array {
		return [
			'files' => $this->getFiles()
		];
	}


	/**
	 * @return array
	 */
	public function jsonSerialize(): array {
		$arr = [
			'name' => $this->getName(),
			'count' => $this->getCount(),
			'size' => $this->getSize(),
			'encrypted' => $this->isEncrypted(),
			'staticName' => $this->isStaticName(),
			'checksum' => $this->getChecksum(),
			'encryptedChecksum' => $this->getEncryptedChecksum()
		];

		if ($this->getContent() !== '') {
			$arr['content'] = $this->getContent();
		}

		return $arr;
	}


}

