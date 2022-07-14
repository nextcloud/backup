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


namespace OCA\Backup\RemoteRequest;

use OCA\Backup\Tools\Model\NCSignedRequest;
use OCA\Backup\Tools\Model\SimpleDataStore;

/**
 * Class RemoteRequest
 *
 * @package OCA\Backup\RemoteRequest
 */
class CoreRequest {


	/** @var NCSignedRequest */
	private $signedRequest;

	/** @var SimpleDataStore */
	protected $config;

	/** @var array */
	private $outcome = [];


	/**
	 * CoreRequest constructor.
	 */
	public function __construct() {
		$this->config = new SimpleDataStore();
	}


	/**
	 * @param NCSignedRequest $signedRequest
	 */
	public function setSignedRequest(NCSignedRequest $signedRequest): void {
		$this->signedRequest = $signedRequest;
	}

	/**
	 * @return NCSignedRequest
	 */
	public function getSignedRequest(): NCSignedRequest {
		return $this->signedRequest;
	}


	/**
	 * @param SimpleDataStore $config
	 */
	public function config(SimpleDataStore $config): void {
		$this->config = $config;
	}


	/**
	 * @param array $outcome
	 */
	public function setOutcome(array $outcome): void {
		$this->outcome = $outcome;
	}

	/**
	 * @return array
	 */
	public function getOutcome(): array {
		return $this->outcome;
	}
}
