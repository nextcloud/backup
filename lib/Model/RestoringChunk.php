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


namespace OCA\Backup\Model;

use ArtificialOwl\MySmallPhpTools\IDeserializable;
use ArtificialOwl\MySmallPhpTools\Traits\Nextcloud\nc23\TNC23Deserialize;
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
	use TNC23Deserialize;


	/** @var string */
	private $name = '';

	/** @var string */
	private $path = '';

	/** @var string */
	private $type = '';

	/** @var string */
	private $content = '';

	/** @var int */
	private $compression = 0;

	/** @var string[] */
	private $files = [];

	/** @var RestoringChunkPart[] */
	private $parts = [];

	/** @var int */
	private $count = 0;

	/** @var int */
	private $size = 0;

	/** @var string */
	private $checksum = '';

	/** @var bool */
	private $stored = false;

	/** @var bool */
	private $staticName;


	/**
	 * RestoringChunk constructor.
	 */
	public function __construct(string $name = '', bool $staticName = false) {
		$this->staticName = $staticName;
		if ($name === '') {
			return;
		}

//		if (!$staticName) {
//			$name .= '-';
//			$uuid = $this->uuid();
//			$this->setPath($name . substr($uuid, 0, 1) . '/' . substr($uuid, 0, 2) . '/');
//			$name .= $uuid;
//		}

		if (!$staticName) {
			$this->name = $name . '-' . $this->uuid();
			$this->setPath('/' . $name . '/' . $this->name . '/');
		} else {
			$this->name = $name;
		}
	}


	/**
	 * @return string
	 */
	public function getName(): string {
		return $this->name;
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
	public function getPath(): string {
		return $this->path;
	}

	/**
	 * @param string $path
	 *
	 * @return $this
	 */
	public function setPath(string $path): self {
		$this->path = $path;

		return $this;
	}


	/**
	 * @param string $type
	 *
	 * @return RestoringChunk
	 */
	public function setType(string $type): self {
		$this->type = $type;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getType(): string {
		return $this->type;
	}


	/**
	 * @param int $compression
	 *
	 * @return RestoringChunk
	 */
	public function setCompression(int $compression): self {
		$this->compression = $compression;

		return $this;
	}

	/**
	 * @return int
	 */
	public function getCompression(): int {
		return $this->compression;
	}


	/**
	 * @return string
	 */
	public function getFilename(): string {
		if ($this->isStaticName()) {
			return $this->getName();
		}

		if ($this->getCompression() > 0) {
			return $this->getName() . '.zip.gz';
		}

		return $this->getName() . '.zip';
	}


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
	 * @return RestoringChunkPart[]
	 */
	public function getParts(): array {
		return $this->parts;
	}

	/**
	 * @param RestoringChunkPart[] $parts
	 *
	 * @return RestoringChunk
	 */
	public function setParts(array $parts): self {
		$this->parts = $parts;

		return $this;
	}

	/**
	 * @return bool
	 */
	public function hasParts(): bool {
		return !empty($this->parts);
	}


	/**
	 * @param RestoringChunkPart $part
	 *
	 * @return RestoringChunk
	 */
	public function addPart(RestoringChunkPart $part): self {
		$this->parts[] = $part;

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
			 ->setPath($this->get('path', $data))
			 ->setType($this->get('type', $data))
			 ->setCompression($this->getInt('compression', $data))
//			 ->setFiles($this->getArray('files', $data, []))
			 ->setCount($this->getInt('count', $data))
			 ->setSize($this->getInt('size', $data))
			 ->setContent($this->get('content', $data))
			 ->setStaticName($this->getBool('staticName', $data))
			 ->setChecksum($this->get('checksum', $data));

		/** @var RestoringChunkPart[] $parts */
		$parts = $this->deserializeArray($this->getArray('parts', $data), RestoringChunkPart::class);
		$this->setParts($parts);


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
			'path' => $this->getPath(),
			'count' => $this->getCount(),
			'size' => $this->getSize(),
			'parts' => $this->getParts(),
			'staticName' => $this->isStaticName(),
			'checksum' => $this->getChecksum()
		];

		if ($this->getContent() !== '') {
			$arr['content'] = $this->getContent();
		}

		if ($this->getType() !== '') {
			$arr['type'] = $this->getType();
		}

		if ($this->getCompression() > 0) {
			$arr['compression'] = $this->getCompression();
		}

		return $arr;
	}
}
