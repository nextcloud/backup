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


namespace OCA\Backup\Tools\Traits;

use OC;
use OC\AppFramework\Middleware\Security\Exceptions\NotLoggedInException;
use OCA\Backup\Tools\Exceptions\JsonNotRequestedException;
use OCP\IRequest;
use OCP\IUserSession;

trait TNCController {

	/**
	 * use this one if a method from a Controller is only PublicPage when remote client asking for Json
	 *
	 * try {
	 *      $this->publicPageJsonLimited();
	 *      return new DataResponse(['test' => 42]);
	 * } catch (JsonNotRequestedException $e) {}
	 *
	 *
	 * @throws NotLoggedInException
	 * @throws JsonNotRequestedException
	 */
	public function publicPageJsonLimited(): void {
		if (!$this->jsonRequested()) {
			if (!OC::$server->get(IUserSession::class)
							->isLoggedIn()) {
				throw new NotLoggedInException();
			}

			throw new JsonNotRequestedException();
		}
	}


	/**
	 * @return bool
	 */
	public function jsonRequested(): bool {
		return ($this->areWithinAcceptHeader(
			[
				'application/json',
				'application/ld+json',
				'application/activity+json'
			]
		));
	}


	/**
	 * @param array $needles
	 *
	 * @return bool
	 */
	public function areWithinAcceptHeader(array $needles): bool {
		$request = OC::$server->get(IRequest::class);
		$accepts = array_map([$this, 'trimHeader'], explode(',', $request->getHeader('Accept')));

		foreach ($accepts as $accept) {
			if (in_array($accept, $needles)) {
				return true;
			}
		}

		return false;
	}


	/**
	 * @param string $header
	 *
	 * @return string
	 */
	private function trimHeader(string $header): string {
		$header = trim($header);
		$pos = strpos($header, ';');
		if ($pos === false) {
			return $header;
		}

		return substr($header, 0, $pos);
	}
}
