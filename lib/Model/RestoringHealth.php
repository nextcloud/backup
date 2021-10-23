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

use ArtificialOwl\MySmallPhpTools\Exceptions\InvalidItemException;
use ArtificialOwl\MySmallPhpTools\IDeserializable;
use ArtificialOwl\MySmallPhpTools\Traits\Nextcloud\nc23\TNC23Deserialize;
use ArtificialOwl\MySmallPhpTools\Traits\Nextcloud\nc23\TNC23Logger;
use ArtificialOwl\MySmallPhpTools\Traits\TArrayTools;
use JsonSerializable;
use OCA\Backup\AppInfo\Application;
use OCA\Backup\Exceptions\RestoringChunkPartNotFoundException;

/**
 * Class RestoringHealth
 *
 * @package OCA\Backup\Model
 */
class RestoringHealth implements IDeserializable, JsonSerializable {
	use TArrayTools;
	use TNC23Deserialize;
	use TNC23Logger;


	public const STATUS_ISSUE = 0;
	public const STATUS_ORPHAN = 1;
	public const STATUS_OK = 9;


	public static $DEF = [
		self::STATUS_ISSUE => 'not complete',
		self::STATUS_ORPHAN => 'without parent',
		self::STATUS_OK => 'ok'
	];


	/** @var int */
	private $status = 0;

	/** @var ChunkPartHealth[] */
	private $parts = [];

	/** @var int */
	private $checked = 0;


	/**
	 * RestoringHealth constructor.
	 */
	public function __construct() {
		$this->setup('app', Application::APP_ID);
	}


	/**
	 * @param int $status
	 *
	 * @return RestoringHealth
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
	 * @param int $checked
	 *
	 * @return RestoringHealth
	 */
	public function setChecked(int $checked): self {
		$this->checked = $checked;

		return $this;
	}

	/**
	 * @return int
	 */
	public function getChecked(): int {
		return $this->checked;
	}


	/**
	 * @param ChunkPartHealth[] $parts
	 */
	public function setParts(array $parts): self {
		$this->parts = $parts;

		return $this;
	}

	/**
	 * @return ChunkPartHealth[]
	 */
	public function getParts(): array {
		return $this->parts;
	}

	/**
	 * @param ChunkPartHealth $part
	 *
	 * @return $this
	 */
	public function addPart(ChunkPartHealth $part): self {
		$k = $this->generateName($part->getChunkName(), $part->getPartName());
		$this->parts[$k] = $part;

		return $this;
	}

	/**
	 * @param string $chunkName
	 * @param string $partName
	 *
	 * @return ChunkPartHealth
	 * @throws RestoringChunkPartNotFoundException
	 */
	public function getPart(string $chunkName, string $partName): ChunkPartHealth {
		$k = $this->generateName($chunkName, $partName);
		if (!array_key_exists($k, $this->parts)) {
			throw new RestoringChunkPartNotFoundException();
		}

		return $this->parts[$chunkName . '-' . $partName];
	}


	/**
	 * @param string $chunkName
	 * @param string $partName
	 *
	 * @return string
	 */
	private function generateName(string $chunkName, string $partName = ''): string {
		$k = $chunkName;
		if ($partName !== '') {
			$k .= '-' . $partName;
		}

		return $k;
	}


	/**
	 * @param array $data
	 *
	 * @return RestoringHealth
	 * @throws InvalidItemException
	 */
	public function import(array $data): IDeserializable {
		$this->setStatus($this->getInt('status', $data));
		$this->setChecked($this->getInt('checked', $data));

		/** @var ChunkPartHealth[] $parts */
		$parts = $this->deserializeArray(
			$this->getArray('parts', $data),
			ChunkPartHealth::class,
			true
		);

		if (empty($parts)) {
			throw new InvalidItemException();
		}

		$this->setParts($parts);

		return $this;
	}


	/**
	 * @return array
	 */
	public function jsonSerialize(): array {
		return [
			'status' => $this->getStatus(),
			'checked' => $this->getChecked(),
			'parts' => $this->getParts()
		];
	}
}
