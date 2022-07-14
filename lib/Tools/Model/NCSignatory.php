<?php

declare(strict_types=1);


/**
 * Nextcloud - Backup now. Restore later.
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Maxence Lange <maxence@artificial-owl.com>
 * @copyright 2022
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


namespace OCA\Backup\Tools\Model;

use JsonSerializable;
use OCA\Backup\Tools\IDeserializable;
use OCA\Backup\Tools\Traits\TArrayTools;

class NCSignatory implements IDeserializable, JsonSerializable {
	use TArrayTools;


	public const SHA256 = 'sha256';
	public const SHA512 = 'sha512';


	/** @var string */
	private $instance = '';

	/** @var string */
	private $id = '';

	/** @var string */
	private $keyOwner = '';

	/** @var string */
	private $keyId = '';

	/** @var string */
	private $publicKey = '';

	/** @var string */
	private $privateKey = '';

	/** @var array */
	private $origData = [];

	/** @var string */
	private $algorithm = self::SHA256;


	/**
	 * NC22Signatory constructor.
	 *
	 * @param string $id
	 */
	public function __construct(string $id = '') {
		$this->id = self::removeFragment($id);
	}


	/**
	 * @param string $instance
	 *
	 * @return self
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
	 * @return array
	 */
	public function getOrigData(): array {
		return $this->origData;
	}

	/**
	 * /**
	 * @param array $data
	 *
	 * @return $this
	 */
	public function setOrigData(array $data): self {
		$this->origData = $data;

		return $this;
	}


	/**
	 * @return string
	 */
	public function getId(): string {
		return $this->id;
	}

	/**
	 * @param string $id
	 *
	 * @return self
	 */
	public function setId(string $id): self {
		$this->id = $id;

		return $this;
	}


	/**
	 * @param string $keyId
	 *
	 * @return self
	 */
	public function setKeyId(string $keyId): self {
		$this->keyId = $keyId;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getKeyId(): string {
		return $this->keyId;
	}


	/**
	 * @param string $keyOwner
	 *
	 * @return self
	 */
	public function setKeyOwner(string $keyOwner): self {
		$this->keyOwner = $keyOwner;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getKeyOwner(): string {
		return $this->keyOwner;
	}


	/**
	 * @param string $publicKey
	 *
	 * @return self
	 */
	public function setPublicKey(string $publicKey): self {
		$this->publicKey = $publicKey;

		return $this;
	}

	/**
	 * @param string $privateKey
	 *
	 * @return self
	 */
	public function setPrivateKey(string $privateKey): self {
		$this->privateKey = $privateKey;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getPublicKey(): string {
		return $this->publicKey;
	}

	/**
	 * @return string
	 */
	public function getPrivateKey(): string {
		return $this->privateKey;
	}

	/**
	 * @return bool
	 */
	public function hasPublicKey(): bool {
		return ($this->publicKey !== '');
	}

	/**
	 * @return bool
	 */
	public function hasPrivateKey(): bool {
		return ($this->privateKey !== '');
	}


	/**
	 * @param string $algorithm
	 *
	 * @return self
	 */
	public function setAlgorithm(string $algorithm): self {
		$this->algorithm = $algorithm;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getAlgorithm(): string {
		return $this->algorithm;
	}


	/**
	 * @param array $data
	 *
	 * @return $this
	 */
	public function import(array $data): IDeserializable {
		if ($this->getId() === '') {
			$this->setId($this->get('id', $data));
		}

		$this->setKeyId($this->get('publicKey.id', $data));
		$this->setKeyOwner($this->get('publicKey.owner', $data));
		$this->setPublicKey($this->get('publicKey.publicKeyPem', $data));

		return $this;
	}


	/**
	 * @return array
	 */
	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'publicKey' =>
				[
					'id' => $this->getKeyId(),
					'owner' => $this->getKeyOwner(),
					'publicKeyPem' => $this->getPublicKey()
				]
		];
	}


	/**
	 * @param string $id
	 *
	 * @return string
	 */
	public static function removeFragment(string $id): string {
		$temp = strtok($id, '#');
		if (is_string($temp)) {
			$id = $temp;
		}

		return $id;
	}
}
