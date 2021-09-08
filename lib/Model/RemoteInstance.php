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


use ArtificialOwl\MySmallPhpTools\Db\Nextcloud\nc22\INC22QueryRow;
use ArtificialOwl\MySmallPhpTools\Model\Nextcloud\nc23\NC23Signatory;
use ArtificialOwl\MySmallPhpTools\Traits\TArrayTools;
use JsonSerializable;
use OCA\Backup\Exceptions\RemoteInstanceNotFoundException;
use OCA\Backup\Exceptions\RemoteInstanceUidException;


/**
 * Class AppService
 *
 * @package OCA\Circles\Model
 */
class RemoteInstance extends NC23Signatory implements INC22QueryRow, JsonSerializable {


	use TArrayTools;


	const EXCHANGE_IN = 1;
	const EXCHANGE_OUT = 2;


	const TEST = 'test';


	/** @var int */
	private $dbId = 0;

	/** @var string */
	private $root = '';

	/** @var int */
	private $exchange = 0;

	/** @var string */
	private $test = '';

	/** @var string */
	private $uid = '';

	/** @var string */
	private $authSigned = '';

	/** @var bool */
	private $identityAuthed = false;


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
	 * @param string $test
	 *
	 * @return RemoteInstance
	 */
	public function setTest(string $test): self {
		$this->test = $test;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getTest(): string {
		return $this->test;
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
	 * @return NC23Signatory
	 */
	public function import(array $data): NC23Signatory {
		parent::import($data);

		$this->setTest($this->get('test', $data))
			 ->setRoot($this->get('root', $data))
			 ->setUid($this->get('uid', $data));

		$algo = '';
		$authSigned = trim($this->get('auth-signed', $data), ':');
		if (strpos($authSigned, ':') > 0) {
			list($algo, $authSigned) = explode(':', $authSigned);
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
			'uid' => $this->getUid(true),
			'root' => $this->getRoot(),
			'test' => $this->getTest()
		];

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
	public function importFromDatabase(array $data): INC22QueryRow {
		if ($this->getInt('id', $data) === 0) {
			throw new RemoteInstanceNotFoundException();
		}

		$this->setDbId($this->getInt('id', $data));
		$this->import($this->getArray('item', $data));
		$this->setExchange($this->getInt('exchange', $data));
		$this->setOrigData($this->getArray('item', $data));
		$this->setInstance($this->get('instance', $data));
		$this->setId($this->get('href', $data));

		return $this;
	}


}

