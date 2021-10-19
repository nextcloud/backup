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
 * Class ChunkPartHealth
 *
 * @package OCA\Backup\Model
 */
class ChunkPartHealth implements IDeserializable, JsonSerializable {
	use TArrayTools;


	public const STATUS_UNKNOWN = 0;
	public const STATUS_OK = 1;
	public const STATUS_MISSING = 2;
	public const STATUS_CHECKSUM = 3;


	public static $DEF_STATUS = [
		self::STATUS_UNKNOWN => 'unknown',
		self::STATUS_OK => 'ok',
		self::STATUS_MISSING => 'missing',
		self::STATUS_CHECKSUM => 'checksum'
	];

	/** @var bool */
	private $packed;

	/** @var string */
	private $partName = '';

	/** @var string */
	private $chunkName = '';

	/** @var string */
	private $dataName = '';

	/** @var int */
	private $status = 0;


	/**
	 * ChunkPartHealth constructor.
	 *
	 * @param bool $packed
	 */
	public function __construct(bool $packed = false) {
		$this->packed = $packed;
	}


	/**
	 * @param bool $packed
	 *
	 * @return ChunkPartHealth
	 */
	public function setPacked(bool $packed): self {
		$this->packed = $packed;

		return $this;
	}

	/**
	 * @return bool
	 */
	public function isPacked(): bool {
		return $this->packed;
	}


	/**
	 * @param string $partName
	 *
	 * @return ChunkPartHealth
	 */
	public function setPartName(string $partName): self {
		$this->partName = $partName;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getPartName(): string {
		return $this->partName;
	}


	/**
	 * @param string $chunkName
	 *
	 * @return ChunkPartHealth
	 */
	public function setChunkName(string $chunkName): self {
		$this->chunkName = $chunkName;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getChunkName(): string {
		return $this->chunkName;
	}


	/**
	 * @param string $dataName
	 *
	 * @return ChunkPartHealth
	 */
	public function setDataName(string $dataName): self {
		$this->dataName = $dataName;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getDataName(): string {
		return $this->dataName;
	}


	/**
	 * @param int $status
	 *
	 * @return ChunkPartHealth
	 */
	public function setStatus(int $status): self {
		$this->status = $status;

		return $this;
	}

	/**
	 * @return int
	 */
	public function getStatus(): int {
		return $this->status;
	}


	/**
	 * @param array $data
	 *
	 * @return RestoringHealth
	 */
	public function import(array $data): IDeserializable {
		$this->setStatus($this->getInt('status', $data))
			 ->setPacked($this->getBool('packed', $data))
			 ->setPartName($this->get('part', $data))
			 ->setChunkName($this->get('chunk', $data))
			 ->setDataName($this->get('data', $data));

		return $this;
	}


	/**
	 * @return array
	 */
	public function jsonSerialize(): array {
		return [
			'packed' => $this->isPacked(),
			'part' => $this->getPartName(),
			'chunk' => $this->getChunkName(),
			'data' => $this->getDataName(),
			'status' => $this->getStatus()
		];
	}
}
