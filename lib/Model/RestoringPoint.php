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
use ArtificialOwl\MySmallPhpTools\Exceptions\InvalidItemException;
use ArtificialOwl\MySmallPhpTools\IDeserializable;
use ArtificialOwl\MySmallPhpTools\ISignedModel;
use ArtificialOwl\MySmallPhpTools\Model\SimpleDataStore;
use ArtificialOwl\MySmallPhpTools\Traits\Nextcloud\nc23\TNC23Deserialize;
use ArtificialOwl\MySmallPhpTools\Traits\Nextcloud\nc23\TNC23Logger;
use ArtificialOwl\MySmallPhpTools\Traits\TArrayTools;
use JsonSerializable;
use OCA\Backup\Exceptions\RestoringDataNotFoundException;
use OCA\Backup\Wrappers\AppDataRootWrapper;
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


	public const STATUS_UNPACKED = 0;
	public const STATUS_PACKED = 1;
	public const STATUS_COMPRESSED = 2;
	public const STATUS_ENCRYPTED = 4;
	public const STATUS_PACKING = 8;
	public const STATUS_UNKNOWN = 16;

	public const LOCK_TIMEOUT = 1800;

	public static $DEF_STATUS =
		[
			self::STATUS_PACKED => 'packed',
			self::STATUS_COMPRESSED => 'compressed',
			self::STATUS_ENCRYPTED => 'encrypted',
			self::STATUS_PACKING => 'processing',
			self::STATUS_UNKNOWN => 'unknown'
		];

	public const STATUS_ISSUE = 32;


	/** @var string */
	private $id = '';

	/** @var string */
	private $instance = '';

	/** @var string */
	private $parent = '';

	/** @var int */
	private $status = 0;

	/** @var int */
	private $duration = 0;

	/** @var SimpleDataStore */
	private $notes;

	/** @var int */
	private $date = 0;

	/** @var array */
	private $nc = [];

	/** @var string */
	private $comment = '';

	/** @var bool */
	private $archive = false;

	/** @var int */
	private $lock = 0;

	/** @var ISimpleFolder */
	private $baseFolder = null;

	/** @var AppDataRootWrapper */
	private $appDataRootWrapper = null;

	/** @var RestoringData[] */
	private $restoringData = [];

	/** @var RestoringHealth */
	private $health;

	/** @var string */
	private $signature = '';

	/** @var string */
	private $subSignature = '';

	/** @var bool */
	private $package = false;


	/**
	 * RestoringPoint constructor.
	 */
	public function __construct() {
		$this->notes = new SimpleDataStore();
	}


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
	public function setInstance(string $instance = ''): self {
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
	 * @param string $parent
	 *
	 * @return RestoringPoint
	 */
	public function setParent(string $parent): self {
		$this->parent = $parent;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getParent(): string {
		return $this->parent;
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
	 * @param int $flag
	 *
	 * @return $this
	 */
	public function addStatus(int $flag): self {
		$this->status |= $flag;

		return $this;
	}

	/**
	 * @param int $flag
	 *
	 * @return $this
	 */
	public function removeStatus(int $flag): self {
		$this->addStatus($flag);
		$this->status -= $flag;

		return $this;
	}

	/**
	 * @param int $flag
	 *
	 * @return bool
	 */
	public function isStatus(int $flag): bool {
		return (($this->getStatus() & $flag) !== 0);
	}


	/**
	 * @param int $duration
	 *
	 * @return RestoringPoint
	 */
	public function setDuration(int $duration): self {
		$this->duration = $duration;

		return $this;
	}

	/**
	 * @return int
	 */
	public function getDuration(): int {
		return $this->duration;
	}


	/**
	 * @param bool $archive
	 *
	 * @return RestoringPoint
	 */
	public function setArchive(bool $archive): self {
		$this->archive = $archive;

		return $this;
	}

	/**
	 * @return bool
	 */
	public function isArchive(): bool {
		return $this->archive;
	}


	/**
	 * @param int $lock
	 *
	 * @return RestoringPoint
	 */
	public function setLock(int $lock): self {
		$this->lock = $lock;

		return $this;
	}

	/**
	 * @return int
	 */
	public function getLock(): int {
		return $this->lock;
	}

	/**
	 * @return bool
	 */
	public function isLocked(): bool {
		return ($this->getLock() > (time() - self::LOCK_TIMEOUT));
	}


	/**
	 * @param SimpleDataStore $notes
	 *
	 * @return RestoringPoint
	 */
	public function setNotes(SimpleDataStore $notes): self {
		$this->notes = $notes;

		return $this;
	}

	/**
	 * @return $this
	 */
	public function unsetNotes(): self {
		$this->notes = new SimpleDataStore();

		return $this;
	}

	/**
	 * @return SimpleDataStore
	 */
	public function getNotes(): SimpleDataStore {
		return $this->notes;
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
	 *
	 * @return RestoringPoint
	 */
	public function setNC(array $nc): self {
		$this->nc = $nc;

		return $this;
	}

	/**
	 * @return array
	 */
	public function getNC(): array {
		return $this->nc;
	}

	/**
	 * @return string
	 */
	public function getNCVersion(): string {
		return implode('.', $this->getNc());
	}

	/**
	 * @return int
	 */
	public function getNCInt(): int {
		$nc = $this->getNc();

		return 1 * $nc[3] + 100 * $nc[2] + 10000 * $nc[1] + 1000000 * $nc[0];
	}


	/**
	 * @param string $comment
	 *
	 * @return RestoringPoint
	 */
	public function setComment(string $comment): self {
		$this->comment = $comment;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getComment(): string {
		return $this->comment;
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
	 * @return bool
	 */
	public function hasAppDataRootWrapper(): bool {
		return !is_null($this->appDataRootWrapper);
	}

	/**
	 * @param AppDataRootWrapper $root
	 *
	 * @return RestoringPoint
	 */
	public function setAppDataRootWrapper(AppDataRootWrapper $root): self {
		$this->appDataRootWrapper = $root;

		return $this;
	}

	/**
	 * @return AppDataRootWrapper
	 */
	public function getAppDataRootWrapper(): AppDataRootWrapper {
		return $this->appDataRootWrapper;
	}


	/**
	 * @return RestoringData[]
	 */
	public function getRestoringData(): array {
		return $this->restoringData;
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
	 * @param RestoringData $data
	 *
	 * @return RestoringPoint
	 */
	public function addRestoringData(RestoringData $data): self {
		$this->restoringData[] = $data;

		return $this;
	}

	/**
	 * @param string $dataName
	 *
	 * @return RestoringData
	 * @throws RestoringDataNotFoundException
	 */
	public function getData(string $dataName): RestoringData {
		foreach ($this->restoringData as $restoringData) {
			if ($restoringData->getName() === $dataName) {
				return $restoringData;
			}
		}

		throw new RestoringDataNotFoundException();
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
	 * @return $this
	 */
	public function unsetHealth(): self {
		$this->health = null;

		return $this;
	}


	/**
	 * @param string $signature
	 *
	 * @return RestoringPoint
	 */
	public function setSignature(string $signature): ISignedModel {
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
	 * @param string $subSignature
	 *
	 * @return RestoringPoint
	 */
	public function setSubSignature(string $subSignature): ISignedModel {
		$this->subSignature = $subSignature;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getSubSignature(): string {
		return $this->subSignature;
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
	 * @throws InvalidItemException
	 */
	public function importFromDatabase(array $data): INC23QueryRow {
		$this->setId($this->get('uid', $data))
			 ->setInstance($this->get('instance', $data))
			 ->setParent($this->get('parent', $data))
			 ->setArchive($this->getBool('archive', $data))
			 ->setLock($this->getInt('lock', $data))
			 ->setStatus($this->getInt('status', $data))
			 ->setNotes(new SimpleDataStore($this->getArray('notes', $data)))
			 ->setDate($this->getInt('date', $data));

		if ($this->getId() === '') {
			throw new InvalidItemException();
		}

		$metadata = new SimpleDataStore($this->getArray('metadata', $data));
		$this->setNc($metadata->gArray('nc'))
			 ->setSignature($metadata->g('signature'))
			 ->setSubSignature($metadata->g('subSignature'))
			 ->setComment($metadata->g('comment'))
			 ->setDuration($metadata->gInt('duration'));

		try {
			/** @var RestoringHealth $health */
			$health = $this->deserialize($this->getArray('health', $data), RestoringHealth::class);
			$this->setHealth($health);
		} catch (InvalidItemException $e) {
		}

		/** @var RestoringData[] $restoringData */
		$restoringData = $this->deserializeArray($metadata->gArray('data'), RestoringData::class);
		$this->setRestoringData($restoringData);

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
			 ->setParent($this->get('parent', $data))
			 ->setArchive($this->getBool('archive', $data))
			 ->setStatus($this->getInt('status', $data, -1))
			 ->setDuration($this->getInt('duration', $data))
			 ->setNotes(new SimpleDataStore($this->getArray('notes', $data)))
			 ->setDate($this->getInt('date', $data))
			 ->setSignature($this->get('signature', $data))
			 ->setSubSignature($this->get('subSignature', $data))
			 ->setComment($this->get('comment', $data))
			 ->setNc($this->getArray('nc', $data));

		if ($this->getId() === '' || $this->getStatus() === -1) {
			throw new InvalidItemException();
		}

		/** @var RestoringData[] $restoringData */
		$restoringData = $this->deserializeArray($this->getArray('data', $data), RestoringData::class);
		$this->setRestoringData($restoringData);

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
			'duration' => $this->getDuration(),
			'parent' => $this->getParent(),
			'data' => $this->getRestoringData(),
			'date' => $this->getDate()
		];
	}


	/**
	 * @return array
	 */
	public function subSignedData(): array {
		return [
			'id' => $this->getId(),
			'nc' => $this->getNC(),
			'date' => $this->getDate(),
			'comment' => $this->getComment(),
			'archive' => $this->isArchive()
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
			'comment' => $this->getComment(),
			'parent' => $this->getParent(),
			'status' => $this->getStatus(),
			'duration' => $this->getDuration(),
			'archive' => $this->isArchive(),
			'notes' => $this->getNotes(),
			'data' => $this->getRestoringData(),
			'signature' => $this->getSignature(),
			'subSignature' => $this->getSubSignature(),
			'date' => $this->getDate()
		];

		if ($this->hasHealth()) {
			$arr['health'] = $this->getHealth();
		}

		return $arr;
	}
}
