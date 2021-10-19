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
use ArtificialOwl\MySmallPhpTools\Model\Nextcloud\nc23\NC23Signatory;
use ArtificialOwl\MySmallPhpTools\Traits\TArrayTools;
use JsonSerializable;
use OCA\Backup\Exceptions\RemoteInstanceNotFoundException;
use OCA\Backup\Exceptions\RemoteInstanceUidException;

/**
 * Class AppService
 *
 * @package OCA\Backup\Model
 */
class RemoteInstance extends NC23Signatory implements INC23QueryRow, IDeserializable, JsonSerializable {
	use TArrayTools;


	public const LOCAL = 'local';
	public const ALL = 'all';


	public const EXCHANGE_IN = 1;
	public const EXCHANGE_OUT = 2;


	public const UID = 'uid';
	public const ROOT = 'root';
	public const AUTH_SIGNED = 'auth-signed';
	public const RP_LIST = 'restoringPoint.list';
	public const RP_GET = 'restoringPoint.get';
	public const RP_DELETE = 'restoringPoint.delete';
	public const RP_CREATE = 'restoringPoint.create';
	public const RP_UPDATE = 'restoringPoint.update';
	public const RP_HEALTH = 'restoringPoint.health';
	public const RP_UPLOAD = 'restoringPoint.upload';
	public const RP_DOWNLOAD = 'restoringPoint.download';


	/** @var int */
	private $dbId = 0;

	/** @var string */
	private $root = '';

	/** @var int */
	private $exchange = 0;

	/** @var string */
	private $RPList = '';

	/** @var string */
	private $RPGet = '';

	/** @var string */
	private $RPHealth = '';

	/** @var string */
	private $RPDownload = '';

	/** @var string */
	private $RPCreate = '';

	/** @var string */
	private $RPDelete = '';

	/** @var string */
	private $RPUpdate = '';

	/** @var string */
	private $RPUpload = '';

	/** @var string */
	private $uid = '';

	/** @var string */
	private $authSigned = '';

	/** @var bool */
	private $identityAuthed = false;

	/** @var bool */
	private $includeExtraDataOnSerialize = false;


	/**
	 * @param int $dbId
	 *
	 * @return self
	 */
	public function setDbId(int $dbId): self {
		$this->dbId = $dbId;

		return $this;
	}

	/**
	 * @return int
	 */
	public function getDbId(): int {
		return $this->dbId;
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
	 * @return $this
	 */
	public function setRoot(string $root): self {
		$this->root = $root;

		return $this;
	}


	/**
	 * @param int $exchange
	 *
	 * @return $this
	 */
	public function setExchange(int $exchange): self {
		$this->exchange = $exchange;

		return $this;
	}

	/**
	 * @return int
	 */
	public function getExchange(): int {
		return $this->exchange;
	}

	/**
	 * @param bool $incoming
	 *
	 * @return $this
	 */
	public function setIncoming(bool $incoming = false): self {
		$this->exchange |= self::EXCHANGE_IN;
		if (!$incoming) {
			$this->exchange -= self::EXCHANGE_IN;
		}

		return $this;
	}

	/**
	 * @return bool
	 */
	public function isIncoming(): bool {
		return (($this->getExchange() & self::EXCHANGE_IN) !== 0);
	}


	/**
	 * @param bool $outgoing
	 *
	 * @return $this
	 */
	public function setOutgoing(bool $outgoing = false): self {
		$this->exchange |= self::EXCHANGE_OUT;
		if (!$outgoing) {
			$this->exchange -= self::EXCHANGE_OUT;
		}

		return $this;
	}

	/**
	 * @return bool
	 */
	public function isOutgoing(): bool {
		return (($this->getExchange() & self::EXCHANGE_OUT) !== 0);
	}


	/**
	 * @param string $RPList
	 *
	 * @return RemoteInstance
	 */
	public function setRPList(string $RPList): self {
		$this->RPList = $RPList;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getRPList(): string {
		return $this->RPList;
	}


	/**
	 * @param string $RPHealth
	 *
	 * @return RemoteInstance
	 */
	public function setRPHealth(string $RPHealth): self {
		$this->RPHealth = $RPHealth;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getRPHealth(): string {
		return $this->RPHealth;
	}


	/**
	 * @param string $RPGet
	 *
	 * @return RemoteInstance
	 */
	public function setRPGet(string $RPGet): self {
		$this->RPGet = $RPGet;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getRPGet(): string {
		return $this->RPGet;
	}


	/**
	 * @param string $RPDownload
	 *
	 * @return RemoteInstance
	 */
	public function setRPDownload(string $RPDownload): self {
		$this->RPDownload = $RPDownload;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getRPDownload(): string {
		return $this->RPDownload;
	}


	/**
	 * @param string $RPCreate
	 *
	 * @return RemoteInstance
	 */
	public function setRPCreate(string $RPCreate): self {
		$this->RPCreate = $RPCreate;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getRPCreate(): string {
		return $this->RPCreate;
	}




	/**
	 * @param string $RPDelete
	 *
	 * @return RemoteInstance
	 */
	public function setRPDelete(string $RPDelete): self {
		$this->RPDelete = $RPDelete;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getRPDelete(): string {
		return $this->RPDelete;
	}


	/**
	 * @param string $RPUpdate
	 *
	 * @return RemoteInstance
	 */
	public function setRPUpdate(string $RPUpdate): self {
		$this->RPUpdate = $RPUpdate;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getRPUpdate(): string {
		return $this->RPUpdate;
	}


	/**
	 * @param string $RPUpload
	 *
	 * @return RemoteInstance
	 */
	public function setRPUpload(string $RPUpload): self {
		$this->RPUpload = $RPUpload;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getRPUpload(): string {
		return $this->RPUpload;
	}


	/**
	 * @return $this
	 */
	public function setUidFromKey(): self {
		$this->setUid(hash('sha512', $this->getPublicKey()));

		return $this;
	}

	/**
	 * @param string $uid
	 *
	 * @return RemoteInstance
	 */
	public function setUid(string $uid): self {
		$this->uid = $uid;

		return $this;
	}

	/**
	 * @param bool $shorten
	 *
	 * @return string
	 */
	public function getUid(bool $shorten = false): string {
		if ($shorten) {
			return substr($this->uid, 0, 18);
		}

		return $this->uid;
	}


	/**
	 * @param string $authSigned
	 *
	 * @return RemoteInstance
	 */
	public function setAuthSigned(string $authSigned): self {
		$this->authSigned = $authSigned;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getAuthSigned(): string {
		return $this->authSigned;
	}


	/**
	 * @param bool $identityAuthed
	 *
	 * @return RemoteInstance
	 */
	public function setIdentityAuthed(bool $identityAuthed): self {
		$this->identityAuthed = $identityAuthed;

		return $this;
	}

	/**
	 * @return bool
	 */
	public function isIdentityAuthed(): bool {
		return $this->identityAuthed;
	}

	/**
	 * @throws RemoteInstanceUidException
	 */
	public function mustBeIdentityAuthed(): void {
		if (!$this->isIdentityAuthed()) {
			throw new RemoteInstanceUidException('identity not authed');
		}
	}


	/**
	 * @param array $data
	 *
	 * @return IDeserializable
	 */
	public function import(array $data): IDeserializable {
		$this->importSignatory($data);
		$this->setInstance($this->get('instance', $data));
		$this->setOrigData($data);
		$this->setExchange($this->getInt('exchange', $data));

		return $this;
	}


	/**
	 * @param array $data
	 *
	 * @return NC23Signatory
	 */
	public function importSignatory(array $data): NC23Signatory {
		parent::importSignatory($data);

		$this->setRoot($this->get(self::ROOT, $data))
			 ->setRPList($this->get(self::RP_LIST, $data))
			 ->setRPCreate($this->get(self::RP_CREATE, $data))
			 ->setRPDelete($this->get(self::RP_DELETE, $data))
			 ->setRPUpdate($this->get(self::RP_UPDATE, $data))
			 ->setRPGet($this->get(self::RP_GET, $data))
			 ->setRPHealth($this->get(self::RP_HEALTH, $data))
			 ->setRPUpload($this->get(self::RP_UPLOAD, $data))
			 ->setRPDownload($this->get(self::RP_DOWNLOAD, $data))
			 ->setUid($this->get(self::UID, $data));

		$algo = '';
		$authSigned = trim($this->get(self::AUTH_SIGNED, $data), ':');
		if (strpos($authSigned, ':') > 0) {
			[$algo, $authSigned] = explode(':', $authSigned);
		}

		$this->setAuthSigned($authSigned)
			 ->setAlgorithm($algo);

		return $this;
	}


	/**
	 * @return array
	 */
	public function jsonSerialize(): array {
		$data = [
			self::UID => $this->getUid(true),
			self::ROOT => $this->getRoot(),
			'restoringPoint' =>
				[
					'list' => $this->getRPList(),
					'create' => $this->getRPCreate(),
					'delete' => $this->getRPDelete(),
					'get' => $this->getRPGet(),
					'health' => $this->getRPHealth(),
					'update' => $this->getRPUpdate(),
					'upload' => $this->getRPUpload(),
					'download' => $this->getRPDownload()
				]
		];

		if ($this->includeExtraDataOnSerialize) {
			$data = array_merge(
				$data, [
					'instance' => $this->getInstance(),
					'exchange' => $this->getExchange()
				]
			);
		}

		if ($this->getAuthSigned() !== '') {
			$data['auth-signed'] = $this->getAlgorithm() . ':' . $this->getAuthSigned();
		}

		return array_filter(array_merge($data, parent::jsonSerialize()));
	}


	/**
	 * @param array $data
	 *
	 * @return self
	 * @throws RemoteInstanceNotFoundException
	 */
	public function importFromDatabase(array $data): INC23QueryRow {
		if ($this->getInt('id', $data) === 0) {
			throw new RemoteInstanceNotFoundException();
		}

		$this->setDbId($this->getInt('id', $data));
		$this->importSignatory($this->getArray('item', $data));
		$this->setExchange($this->getInt('exchange', $data));
		$this->setOrigData($this->getArray('item', $data));
		$this->setInstance($this->get('instance', $data));
		$this->setId($this->get('href', $data));

		if ($this->getBool('_params.includeExtraDataOnSerialize', $data)) {
			$this->includeExtraDataOnSerialize = true;
		}

		return $this;
	}
}
