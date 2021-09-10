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


use ArtificialOwl\MySmallPhpTools\Db\Nextcloud\nc23\INC23QueryRow;
use ArtificialOwl\MySmallPhpTools\IDeserializable;
use ArtificialOwl\MySmallPhpTools\Model\SimpleDataStore;
use ArtificialOwl\MySmallPhpTools\Traits\TArrayTools;
use JsonSerializable;


/**
 * Class RestoringPoint
 *
 * @package OCA\Backup\Model
 */
class RestoringPoint implements IDeserializable, INC23QueryRow, JsonSerializable {


	use TArrayTools;


	/** @var string */
	private $id = '';

	/** @var string */
	private $instance = '';

	/** @var int */
	private $status = 0;

	/** @var SimpleDataStore */
	private $metadata;

	/** @var int */
	private $date = 0;


	/**
	 * @param string $id
	 *
	 * @return RestoringPoint
	 */
	public function setId(string $id): self {
		$this->id = $id;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getId(): string {
		return $this->id;
	}


	/**
	 * @param string $instance
	 *
	 * @return RestoringPoint
	 */
	public function setInstance(string $instance): self {
		$this->instance = $instance;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getInstance(): string {
		return $this->instance;
	}


	/**
	 * @param int $status
	 *
	 * @return RestoringPoint
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
	 * @param SimpleDataStore $metadata
	 *
	 * @return RestoringPoint
	 */
	public function setMetadata(SimpleDataStore $metadata): self {
		$this->metadata = $metadata;

		return $this;
	}

	/**
	 * @return SimpleDataStore
	 */
	public function getMetadata(): SimpleDataStore {
		return $this->metadata;
	}


	/**
	 * @param int $date
	 *
	 * @return RestoringPoint
	 */
	public function setDate(int $date): self {
		$this->date = $date;

		return $this;
	}

	/**
	 * @return int
	 */
	public function getDate(): int {
		return $this->date;
	}


	/**
	 * @param array $data
	 *
	 * @return INC23QueryRow
	 */
	public function importFromDatabase(array $data): INC23QueryRow {
		$this->setId($this->get('uid', $data))
			 ->setInstance($this->get('instance', $data))
			 ->setStatus($this->getInt('status', $data))
			 ->setMetadata(new SimpleDataStore($this->getArray('metadata', $data)))
			 ->setDate($this->getInt('date', $data));

		return $this;
	}


	/**
	 * @param array $data
	 *
	 * @return IDeserializable
	 */
	public function import(array $data): IDeserializable {
		$this->setId($this->get('id', $data))
			 ->setInstance($this->get('instance', $data))
			 ->setStatus($this->getInt('status', $data))
			 ->setMetadata(new SimpleDataStore($this->getArray('metadata', $data)))
			 ->setDate($this->getInt('date', $data));

		return $this;
	}


	/**
	 * @return array
	 */
	public function jsonSerialize(): array {
		return
			[
				'id' => $this->getId(),
				'instance' => $this->getInstance(),
				'status' => $this->getStatus(),
				'metadata' => $this->getMetadata(),
				'date' => $this->getDate()
			];
	}

}

