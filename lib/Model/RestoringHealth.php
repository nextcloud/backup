<?php

declare(strict_types=1);


/**
 * Nextcloud - Backup
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
use ArtificialOwl\MySmallPhpTools\Traits\Nextcloud\nc23\TNC23Logger;
use ArtificialOwl\MySmallPhpTools\Traits\TArrayTools;
use JsonSerializable;
use OCA\Backup\AppInfo\Application;


/**
 * Class RestoringHealth
 *
 * @package OCA\Backup\Model
 */
class RestoringHealth implements IDeserializable, JsonSerializable {


	use TArrayTools;
	use TNC23Deserialize;
	use TNC23Logger;


	const STATUS_ISSUE = 0;
	const STATUS_ORPHAN = 1;
	const STATUS_OK = 9;


	static public $DEF = [
		self::STATUS_ISSUE => 'not complete',
		self::STATUS_ORPHAN => 'without parent',
		self::STATUS_OK => 'ok'
	];


	/** @var int */
	private $status = 0;

	/** @var RestoringChunkHealth[] */
	private $chunks = [];


	/**
	 * RestoringHealth constructor.
	 */
	public function __construct() {
		$this->setup('app', Application::APP_ID);
	}


	/**
	 * @param int $status
	 */
	public function setStatus(int $status): void {
		$this->status = $status;
	}

	/**
	 * @return int
	 */
	public function getStatus(): int {
		return $this->status;
	}


	/**
	 * @param RestoringChunkHealth[] $chunks
	 */
	public function setChunks(array $chunks): self {
		$this->chunks = $chunks;

		return $this;
	}

	/**
	 * @return RestoringChunkHealth[]
	 */
	public function getChunks(): array {
		return $this->chunks;
	}

	/**
	 * @param RestoringChunkHealth $chunk
	 *
	 * @return $this
	 */
	public function addChunk(RestoringChunkHealth $chunk): self {
		$this->chunks[$chunk->getChunkName()] = $chunk;

		return $this;
	}


	/**
	 * @param array $data
	 *
	 * @return RestoringHealth
	 */
	public function import(array $data): IDeserializable {
		$this->setStatus($this->getInt('status', $data));

		/** @var RestoringChunkHealth[] $chunks */
		$chunks = $this->deserializeArray(
			$this->getArray('chunks', $data),
			RestoringChunkHealth::class,
			true
		);
		$this->setChunks($chunks);

		return $this;
	}


	/**
	 * @return array
	 */
	public function jsonSerialize(): array {
		return [
			'status' => $this->getStatus(),
			'chunks' => $this->getChunks()
		];
	}

}

