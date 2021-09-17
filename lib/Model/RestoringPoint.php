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
use ArtificialOwl\MySmallPhpTools\Exceptions\InvalidItemException;
use ArtificialOwl\MySmallPhpTools\IDeserializable;
use ArtificialOwl\MySmallPhpTools\ISignedModel;
use ArtificialOwl\MySmallPhpTools\Model\SimpleDataStore;
use ArtificialOwl\MySmallPhpTools\Traits\Nextcloud\nc23\TNC23Deserialize;
use ArtificialOwl\MySmallPhpTools\Traits\Nextcloud\nc23\TNC23Logger;
use ArtificialOwl\MySmallPhpTools\Traits\TArrayTools;
use JsonSerializable;
use OCP\Files\SimpleFS\ISimpleFolder;


/**
 * Class RestoringPoint
 *
 * @package OCA\Backup\Model
 */
class RestoringPoint implements IDeserializable, INC23QueryRow, ISignedModel, JsonSerializable {


	use TArrayTools;
	use TNC23Deserialize;
	use TNC23Logger;


	/** @var string */
	private $id = '';

	/** @var string */
	private $instance = '';

	/** @var string */
	private $root = '';

	/** @var int */
	private $status = 0;

	/** @var int */
	private $date = 0;

	/** @var array */
	private $nc = [];

	/** @var ISimpleFolder */
	private $baseFolder = null;

	/** @var RestoringData[] */
	private $restoringData = [];

	/** @var RestoringHealth */
	private $health;

	/** @var string */
	private $signature = '';

	/** @var bool */
	private $package = false;


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
	 * @param string $root
	 *
	 * @return RestoringPoint
	 */
	public function setRoot(string $root): self {
		$this->root = $root;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getRoot(): string {
		return $this->root;
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
	 * @param array $nc
	 */
	public function setNC(array $nc): void {
		$this->nc = $nc;
	}

	/**
	 * @return array
	 */
	public function getNC(): array {
		return $this->nc;
	}

	public function getNCVersion(): string {
		return implode('.', $this->getNc());
	}


	public function getNCInt(): int {
		$nc = $this->getNc();

		return 1 * $nc[3] + 100 * $nc[2] + 10000 * $nc[1] + 1000000 * $nc[0];
	}


	/**
	 * @return bool
	 */
	public function hasBaseFolder(): bool {
		return !is_null($this->baseFolder);
	}

	/**
	 * @param ISimpleFolder $baseFolder
	 *
	 * @return RestoringPoint
	 */
	public function setBaseFolder(ISimpleFolder $baseFolder): self {
		$this->baseFolder = $baseFolder;

		return $this;
	}

	/**
	 * @return ISimpleFolder
	 */
	public function getBaseFolder(): ISimpleFolder {
		return $this->baseFolder;
	}


	/**
	 * @param bool $filtered
	 *
	 * @return RestoringData[]
	 */
	public function getRestoringData(bool $filtered = false): array {
		return $this->restoringData;
//		$options = $this->getOptions();
//		if (!$filtered || $options->getChunk() === '') {
//			return $this->chunks;
//		}
//
//		$options = $this->getOptions();
//		foreach ($this->chunks as $chunk) {
//			if ($chunk->getName() === $options->getChunk()) {
//				return [$chunk];
//			}
//		}
//
//		return [];
	}

	/**
	 * @param RestoringData[] $restoringData
	 *
	 * @return RestoringPoint
	 */
	public function setRestoringData(array $restoringData): self {
		$this->restoringData = $restoringData;

		return $this;
	}

	/**
	 * @param RestoringData $chunk
	 *
	 * @return RestoringPoint
	 */
	public function addRestoringData(RestoringData $chunk): self {
		$this->restoringData[] = $chunk;

		return $this;
	}


	/**
	 * @return bool
	 */
	public function hasHealth(): bool {
		return !is_null($this->health);
	}

	/**
	 * @param RestoringHealth $health
	 *
	 * @return RestoringPoint
	 */
	public function setHealth(RestoringHealth $health): self {
		$this->health = $health;

		return $this;
	}

	/**
	 * @return RestoringHealth
	 */
	public function getHealth(): RestoringHealth {
		return $this->health;
	}


	/**
	 * @param string $signature
	 *
	 * @return RestoringPoint
	 */
	public function setSignature(string $signature): self {
		$this->signature = $signature;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getSignature(): string {
		return $this->signature;
	}


	/**
	 * @param bool $package
	 *
	 * @return RestoringPoint
	 */
	public function setPackage(bool $package): self {
		$this->package = $package;

		return $this;
	}

	/**
	 * @return bool
	 */
	public function isPackage(): bool {
		return $this->package;
	}


	/**
	 * @return array
	 */
	public function getMetadata(): array {
		$arr = $this->jsonSerialize();
		unset($arr['health']);

		return $arr;
	}

	/**
	 * @param array $data
	 *
	 * @return INC23QueryRow
	 */
	public function importFromDatabase(array $data): INC23QueryRow {
		$this->setId($this->get('uid', $data))
			 ->setInstance($this->get('instance', $data))
			 ->setRoot($this->get('root', $data))
			 ->setStatus($this->getInt('status', $data))
			 ->setDate($this->getInt('date', $data));

		$metadata = new SimpleDataStore($this->getArray('metadata', $data));
		$this->setNc($metadata->gArray('nc'));
		$this->setSignature($metadata->g('signature'));

		try {
			/** @var RestoringHealth $health */
			$health = $this->deserialize($this->getArray('health', $data), RestoringHealth::class);
			$this->setHealth($health);
		} catch (InvalidItemException $e) {
		}

		try {
			/** @var RestoringData[] $restoringData */
			$restoringData = $this->deserializeArray($metadata->gArray('data'), RestoringData::class);
			$this->setRestoringData($restoringData);
		} catch (InvalidItemException $e) {
		}

		return $this;
	}


	/**
	 * @param array $data
	 *
	 * @return IDeserializable
	 * @throws InvalidItemException
	 */
	public function import(array $data): IDeserializable {
		$this->setId($this->get('id', $data))
			 ->setInstance($this->get('instance', $data))
			 ->setRoot($this->get('root', $data))
			 ->setStatus($this->getInt('status', $data))
			 ->setDate($this->getInt('date', $data))
			 ->setSignature($this->get('signature', $data));
		$this->setNc($this->getArray('nc', $data));

		if ($this->getId() === '' || $this->getStatus() === -1) {
			throw new InvalidItemException();
		}

		try {
			/** @var RestoringData[] $restoringData */
			$restoringData = $this->deserializeArray($this->getArray('data', $data), RestoringData::class);
			$this->setRestoringData($restoringData);
		} catch (InvalidItemException $e) {
		}

		try {
			/** @var RestoringHealth $health */
			$health = $this->deserialize($this->getArray('health', $data), RestoringHealth::class);
			$this->setHealth($health);
		} catch (InvalidItemException $e) {
		}

		return $this;
	}


	/**
	 * @return array
	 */
	public function signedData(): array {
		return [
			'id' => $this->getId(),
			'nc' => $this->getNC(),
			'root' => $this->getRoot(),
			'data' => $this->getRestoringData(),
			'date' => $this->getDate()
		];
	}


	/**
	 * @return array
	 */
	public function jsonSerialize(): array {
		$arr = [
			'id' => $this->getId(),
			'instance' => $this->getInstance(),
			'nc' => $this->getNC(),
			'root' => $this->getRoot(),
			'status' => $this->getStatus(),
			'data' => $this->getRestoringData(),
			'signature' => $this->getSignature(),
			'date' => $this->getDate()
		];

		if ($this->hasHealth()) {
			$arr['health'] = $this->getHealth();
		}

		return $arr;
	}

}
