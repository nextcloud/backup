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

use ArtificialOwl\MySmallPhpTools\Db\Nextcloud\nc23\INC23QueryRow;
use ArtificialOwl\MySmallPhpTools\IDeserializable;
use ArtificialOwl\MySmallPhpTools\Traits\TArrayTools;
use ArtificialOwl\MySmallPhpTools\Traits\TStringTools;
use JsonSerializable;

/**
 * Class BackupEvent
 *
 * @package OCA\Backup\Model
 */
class BackupEvent implements JsonSerializable, INC23QueryRow, IDeserializable {
	use TArrayTools;
	use TStringTools;

	public const STATUS_QUEUE = 0;
	public const STATUS_DONE = 1;


	/** @var int */
	private $id = 0;

	/** @var string */
	private $author = '';

	/** @var string */
	private $type = '';

	/** @var int */
	private $status = 0;

	/** @var array */
	private $data = [];

	/** @var array */
	private $result = [];


	/**
	 * BackupEvent constructor.
	 */
	public function __construct() {
	}


	/**
	 * @param int $id
	 *
	 * @return BackupEvent
	 */
	public function setId(int $id): self {
		$this->id = $id;

		return $this;
	}

	/**
	 * @return int
	 */
	public function getId(): int {
		return $this->id;
	}


	/**
	 * @param string $author
	 *
	 * @return BackupEvent
	 */
	public function setAuthor(string $author): self {
		$this->author = $author;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getAuthor(): string {
		return $this->author;
	}


	/**
	 * @param string $type
	 *
	 * @return BackupEvent
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
	 * @param int $status
	 *
	 * @return BackupEvent
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
	 * @return BackupEvent
	 */
	public function setData(array $data): self {
		$this->data = $data;

		return $this;
	}

	/**
	 * @return array
	 */
	public function getData(): array {
		return $this->data;
	}


	/**
	 * @param array $result
	 *
	 * @return BackupEvent
	 */
	public function setResult(array $result): self {
		$this->result = $result;

		return $this;
	}

	/**
	 * @return array
	 */
	public function getResult(): array {
		return $this->result;
	}


	/**
	 * @param array $data
	 *
	 * @return ExternalFolder
	 */
	public function importFromDatabase(array $data): INC23QueryRow {
		$this->setId($this->getInt('id', $data))
			 ->setAuthor($this->get('author', $data))
			 ->setType($this->get('type', $data))
			 ->setStatus($this->getInt('status', $data))
			 ->setData($this->getArray('data', $data))
			 ->setResult($this->getArray('result', $data));

		return $this;
	}


	/**
	 * @param array $data
	 *
	 * @return ExternalFolder
	 */
	public function import(array $data): IDeserializable {
		$this->setId($this->getInt('id', $data))
			 ->setAuthor($this->get('author', $data))
			 ->setType($this->get('type', $data))
			 ->setStatus($this->getInt('status', $data))
			 ->setData($this->getArray('data', $data))
			 ->setResult($this->getArray('result', $data));

		return $this;
	}


	/**
	 * @return array
	 */
	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'author' => $this->getAuthor(),
			'type' => $this->getType(),
			'status' => $this->getStatus(),
			'data' => $this->getData(),
			'result' => $this->getResult()
		];
	}
}
