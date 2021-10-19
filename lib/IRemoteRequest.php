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


namespace OCA\Backup;

use ArtificialOwl\MySmallPhpTools\IDeserializable;
use ArtificialOwl\MySmallPhpTools\Model\Nextcloud\nc23\NC23SignedRequest;
use ArtificialOwl\MySmallPhpTools\Model\SimpleDataStore;

/**
 * Interface IRemoteRequest
 *
 * @package OCA\Backup
 */
interface IRemoteRequest extends IDeserializable {

	/**
	 * @param NC23SignedRequest $signedRequest
	 */
	public function setSignedRequest(NC23SignedRequest $signedRequest): void;

	/**
	 * @param SimpleDataStore $config
	 */
	public function config(SimpleDataStore $config): void;

	/**
	 *
	 */
	public function execute(): void;

	/**
	 * @return array
	 */
	public function getOutcome(): array;
}
