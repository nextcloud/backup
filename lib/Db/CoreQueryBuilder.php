<?php

declare(strict_types=1);


/**
 * Nextcloud - Backup now. Restore later.
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Maxence Lange <maxence@artificial-owl.com>
 * @copyright 2021
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


namespace OCA\Backup\Db;

use ArtificialOwl\MySmallPhpTools\Db\Nextcloud\nc23\NC23ExtendedQueryBuilder;

/**
 * Class CoreQueryBuilder
 *
 * @package OCA\Backup\Db
 */
class CoreQueryBuilder extends NC23ExtendedQueryBuilder {


	/**
	 * CoreQueryBuilder constructor.
	 */
	public function __construct() {
		parent::__construct();
	}


	/**
	 * Limit the request to the Id
	 *
	 * @param int $id
	 */
	public function limitToId(int $id): void {
		$this->limitInt('id', $id);
	}


	/**
	 * @param string $uid
	 */
	public function limitToUid(string $uid): void {
		$this->limit('uid', $uid);
	}


	/**
	 * @param string $instance
	 */
	public function limitToInstance(string $instance): void {
		$this->limit('instance', $instance, '', false);
	}


	/**
	 * @param string $parent
	 */
	public function limitToParent(string $parent): void {
		$this->limit('parent', $parent);
	}
}
