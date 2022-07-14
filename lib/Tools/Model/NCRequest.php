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

use OCP\Http\Client\IClient;

class NCRequest extends Request {


	/** @var IClient */
	private $client;

	/** @var array */
	private $clientOptions = [];

	/** @var bool */
	private $localAddressAllowed = false;

	/** @var NCRequestResult */
	private $result;

	/** @var NCRequestResult[] */
	private $previousResults = [];


	/**
	 * @param IClient $client
	 *
	 * @return $this
	 */
	public function setClient(IClient $client): self {
		$this->client = $client;

		return $this;
	}

	/**
	 * @return IClient
	 */
	public function getClient(): IClient {
		return $this->client;
	}


	/**
	 * @return array
	 */
	public function getClientOptions(): array {
		return $this->clientOptions;
	}

	/**
	 * @param array $clientOptions
	 *
	 * @return self
	 */
	public function setClientOptions(array $clientOptions): self {
		$this->clientOptions = $clientOptions;

		return $this;
	}


	/**
	 * @return bool
	 */
	public function isLocalAddressAllowed(): bool {
		return $this->localAddressAllowed;
	}

	/**
	 * @param bool $allowed
	 *
	 * @return self
	 */
	public function setLocalAddressAllowed(bool $allowed): self {
		$this->localAddressAllowed = $allowed;

		return $this;
	}


	/**
	 * @return bool
	 */
	public function hasResult(): bool {
		return ($this->result !== null);
	}

	/**
	 * @return NCRequestResult
	 */
	public function getResult(): NCRequestResult {
		return $this->result;
	}

	/**
	 * @param NCRequestResult $result
	 *
	 * @return self
	 */
	public function setResult(NCRequestResult $result): self {
		if (!is_null($this->result)) {
			$this->previousResults[] = $this->result;
		}

		$this->result = $result;

		return $this;
	}

	/**
	 * @return NCRequestResult[]
	 */
	public function getPreviousResults(): array {
		return $this->previousResults;
	}

	/**
	 * @return NCRequestResult[]
	 */
	public function getAllResults(): array {
		return array_values(array_merge([$this->getResult()], $this->previousResults));
	}


	/**
	 * @return array
	 */
	public function jsonSerialize(): array {
		$result = null;
		if ($this->hasResult()) {
			$result = $this->getResult();
		}

		return array_merge(
			parent::jsonSerialize(),
			[
				'clientOptions' => $this->getClientOptions(),
				'localAddressAllowed' => $this->isLocalAddressAllowed(),
				'result' => $result
			]
		);
	}
}
