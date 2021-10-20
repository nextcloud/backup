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
use ArtificialOwl\MySmallPhpTools\Traits\TArrayTools;
use JsonSerializable;

/**
 * Class ArchiveFile
 *
 * @package OCA\Backup\Model
 */
class ArchiveFile implements JsonSerializable, IDeserializable {
	use TArrayTools;


	/** @var string */
	private $name;

	/** @var int */
	private $filesize;


	/** @var RestoringChunk */
	private $restoringChunk;


	/**
	 * ArchiveFile constructor.
	 *
	 * @param string $name
	 * @param int $filesize
	 */
	public function __construct(string $name = '', int $filesize = 0) {
		$this->name = $name;
		$this->filesize = $filesize;
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
	 * @return ArchiveFile
	 */
	public function setName(string $name): self {
		$this->name = $name;

		return $this;
	}

	/**
	 * @param int $filesize
	 *
	 * @return ArchiveFile
	 */
	public function setFilesize(int $filesize): self {
		$this->filesize = $filesize;

		return $this;
	}

	/**
	 * @return int
	 */
	public function getFilesize(): int {
		return $this->filesize;
	}

	/**
	 * @param RestoringChunk $restoringChunk
	 *
	 * @return ArchiveFile
	 */
	public function setRestoringChunk(RestoringChunk $restoringChunk): self {
		$this->restoringChunk = $restoringChunk;

		return $this;
	}

	/**
	 * @return RestoringChunk
	 */
	public function getRestoringChunk(): RestoringChunk {
		return $this->restoringChunk;
	}


	/**
	 * @param array $data
	 *
	 * @return ArchiveFile
	 */
	public function import(array $data): IDeserializable {
		$this->setName($this->get('name', $data));
		$this->setFilesize($this->getInt('filesize', $data));

		return $this;
	}


	/**
	 * @return array
	 */
	public function jsonSerialize(): array {
		return
			[
				'name' => $this->getName(),
				'filesize' => $this->getFilesize()
			];
	}
}
