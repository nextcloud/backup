<?php

declare(strict_types=1);


/**
 * Nextcloud - Backup now. Restore Later
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
 * Class RestoringChunkHealth
 *
 * @package OCA\Backup\Model
 */
class RestoringChunkHealth implements IDeserializable, JsonSerializable {


	use TArrayTools;


	const STATUS_UNKNOWN = 0;
	const STATUS_OK = 1;
	const STATUS_MISSING = 2;
	const STATUS_CHECKSUM = 3;


	/** @var string */
	private $chunkName = '';

	/** @var string */
	private $dataName = '';

	/** @var int */
	private $status = 0;


	/**
	 * @param string $chunkName
	 *
	 * @return RestoringChunkHealth
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
	 * @return RestoringChunkHealth
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
	 * @return RestoringChunkHealth
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
			 ->setChunkName($this->get('chunk', $data))
			 ->setDataName($this->get('data', $data));

		return $this;
	}


	/**
	 * @return array
	 */
	public function jsonSerialize(): array {
		return [
			'chunk' => $this->getChunkName(),
			'data' => $this->getDataName(),
			'status' => $this->getStatus()
		];
	}

}

