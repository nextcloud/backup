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

use OCA\Backup\Tools\Exceptions\RequestNetworkException;
use OCA\Backup\Tools\Exceptions\WellKnownLinkNotFoundException;
use OCA\Backup\Tools\Model\NCRequest;
use OCA\Backup\Tools\Model\NCWebfinger;
use OCA\Backup\Tools\Model\NCWellKnownLink;
use OCA\Backup\Tools\Model\SimpleDataStore;

trait TNCWellKnown {
	use TNCRequest;

	public static $WEBFINGER = '/.well-known/webfinger';


	/**
	 * @param string $host
	 * @param string $subject
	 * @param string $rel
	 *
	 * @return SimpleDataStore
	 * @throws RequestNetworkException
	 * @throws WellKnownLinkNotFoundException
	 */
	public function getResourceData(string $host, string $subject, string $rel): SimpleDataStore {
		$link = $this->getLink($host, $subject, $rel);

		$request = new NCRequest('');
		$request->basedOnUrl($link->getHref());
		$request->addHeader('Accept', $link->getType());
		$request->setFollowLocation(true);
		$request->setLocalAddressAllowed(true);
		$request->setTimeout(5);
		$data = $this->retrieveJson($request);

		return new SimpleDataStore($data);
	}


	/**
	 * @param string $host
	 * @param string $subject
	 * @param string $rel
	 *
	 * @return NCWellKnownLink
	 * @throws RequestNetworkException
	 * @throws WellKnownLinkNotFoundException
	 */
	public function getLink(string $host, string $subject, string $rel): NCWellKnownLink {
		return $this->extractLink($rel, $this->getWebfinger($host, $subject));
	}


	/**
	 * @param string $host
	 * @param string $subject
	 * @param string $rel
	 *
	 * @return NCWebfinger
	 * @throws RequestNetworkException
	 */
	public function getWebfinger(string $host, string $subject, string $rel = ''): NCWebfinger {
		$request = new NCRequest(self::$WEBFINGER);
		$request->setHost($host);
		$request->setProtocols(['https', 'http']);
		$request->setFollowLocation(true);
		$request->setLocalAddressAllowed(true);
		$request->setTimeout(5);

		$request->addParam('resource', $subject);
		if ($rel !== '') {
			$request->addParam('rel', $rel);
		}

		$result = $this->retrieveJson($request);

		return new NCWebfinger($result);
	}


	/**
	 * @param string $rel
	 * @param NCWebfinger $webfinger
	 *
	 * @return NCWellKnownLink
	 * @throws WellKnownLinkNotFoundException
	 */
	public function extractLink(string $rel, NCWebfinger $webfinger): NCWellKnownLink {
		foreach ($webfinger->getLinks() as $link) {
			if ($link->getRel() === $rel) {
				return $link;
			}
		}

		throw new WellKnownLinkNotFoundException();
	}
}
