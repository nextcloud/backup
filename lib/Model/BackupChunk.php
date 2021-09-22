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


use ArtificialOwl\MySmallPhpTools\Traits\TArrayTools;
use ArtificialOwl\MySmallPhpTools\Traits\TStringTools;
use JsonSerializable;


/**
 * Class BackupChunk
 *
 * @package OCA\Backup\Model
 */
class BackupChunk implements JsonSerializable {


	use TArrayTools;
	use TStringTools;


	const ROOT_DISK = 1;
	const ROOT_NEXTCLOUD = 2;
	const ROOT_DATA = 3;
	const ROOT_APPS = 4;

	const FILE_CONFIG = 101;

	// value > 1000 is for content that are not 'file'
	const SQL_DUMP = 1001;

	/** @var string */
	private $name = '';

	/** @var int */
	private $type = 0;

	/** @var string */
	private $path = '';

	/** @var string */
	private $root = '';

	/** @var string */
	private $uniqueFile = '';

	/** @var RestoringChunk[] */
	private $archives = [];

	/** @var string[] */
	private $files = [];


	/**
	 * BackupChunk constructor.
	 *
	 * @param string $name
	 * @param int $type
	 * @param string $path
	 */
	public function __construct(int $type = 0, string $path = '', string $name = '') {
		$this->type = $type;
		$this->path = $path;

		$this->name = $name;
		if ($name === '') {
			$this->name = $this->uuid();
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
	 * @return BackupChunk
	 */
	public function setName(string $name): BackupChunk {
		$this->name = $name;

		return $this;
	}


	/**
	 * @return int
	 */
	public function getType(): int {
		return $this->type;
	}

	/**
	 * @param int $type
	 *
	 * @return BackupChunk
	 */
	public function setType(int $type): BackupChunk {
		$this->type = $type;

		return $this;
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
	 * @return BackupChunk
	 */
	public function setPath(string $path): BackupChunk {
		$this->path = $path;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getRoot(): string {
		return $this->root;
	}

	/**
	 * @param string $root
	 *
	 * @return BackupChunk
	 */
	public function setRoot(string $root): BackupChunk {
		$this->root = $root;

		return $this;
	}


	/**
	 * @return string
	 */
	public function getAbsolutePath(): string {
		return $this->getRoot() . $this->getPath();
	}


	/**
	 * @param string $path
	 *
	 * @return BackupChunk
	 */
	public function addFile(string $path): BackupChunk {
		$this->files[] = $path;

		return $this;
	}

	/**
	 * @return array
	 */
	public function getFiles(): array {
		return $this->files;
	}

	/**
	 * @param string[] $files
	 *
	 * @return BackupChunk
	 */
	public function setFiles(array $files): BackupChunk {
		$this->files = $files;

		return $this;
	}


	/**
	 * @return string
	 */
	public function getUniqueFile(): string {
		return $this->uniqueFile;
	}

	/**
	 * @param string $file
	 *
	 * @return BackupChunk
	 */
	public function setUniqueFile(string $file): BackupChunk {
		$this->uniqueFile = $file;

		return $this;
	}


	/**
	 * @return RestoringChunk[]
	 */
	public function getArchives(): array {
		return $this->archives;
	}

	/**
	 * @param RestoringChunk[] $archives
	 *
	 * @return BackupChunk
	 */
	public function setArchives(array $archives): BackupChunk {
		$this->archives = $archives;

		return $this;
	}

	/**
	 * @param RestoringChunk $archive
	 *
	 * @return BackupChunk
	 */
	public function addArchive(RestoringChunk $archive): BackupChunk {
		$this->archives[] = $archive;

		return $this;
	}


	/**
	 * @param array $data
	 *
	 * @return BackupChunk
	 */
	public function import(array $data): BackupChunk {
		$this->setType($this->getInt('type', $data, 0));
		$this->setName($this->get('name', $data, ''));
		$this->setRoot($this->get('root', $data, ''));
		$this->setPath($this->get('path', $data, ''));
		$this->setArchives($this->getList('archives', $data, [RestoringChunk::class, 'import'], []));

		return $this;
	}


	/**
	 * @return array
	 */
	public function jsonSerialize() {
		return [
			'name' => $this->getName(),
			'type' => $this->getType(),
			'root' => $this->getRoot(),
			'path' => $this->getPath(),
			'archives' => $this->getArchives()
		];
	}

}

