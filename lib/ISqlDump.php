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


interface ISqlDump {

	const MYSQL = 'mysql';
	const PGSQL = 'pgsql';

	const DB_TYPE = 'dbtype';
	const DB_NAME = 'dbname';
	const DB_HOST = 'dbhost';
	const DB_PORT = 'dbport';
	const DB_USER = 'dbuser';
	const DB_PASS = 'dbpassword';


	/**
	 * @param array $data
	 * @param string $filename
	 *
	 * @return string
	 */
	public function export(array $data, string $filename): void;


	/**
	 * @param array $data
	 * @param resource $read
	 *
	 * @return bool
	 */
	public function import(array $data, $read): bool;

}

