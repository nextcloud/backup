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


namespace OCA\Backup\SqlDump;

use ArtificialOwl\MySmallPhpTools\Traits\TArrayTools;
use Ifsnop\Mysqldump\Mysqldump;
use OCA\Backup\Exceptions\SqlDumpException;
use OCA\Backup\Exceptions\SqlParamsException;
use OCA\Backup\ISqlDump;
use OCA\Backup\Service\ConfigService;
use Throwable;

/**
 * Class SqlDumpMySQL
 *
 * @package OCA\Backup\SqlDump
 */
class SqlDumpMySQL implements ISqlDump {
	use TArrayTools;


	/** @var ConfigService */
	private $configService;


	/**
	 * SqlDumpMySQL constructor.
	 */
	public function __construct() {
	}


	/**
	 * @param array $params
	 * @param string $filename
	 *
	 * @return void
	 * @throws SqlDumpException
	 */
	public function export(array $params, string $filename): void {
		$connect = 'mysql:host=' . $params['dbhost'] . ';dbname=' . $params['dbname'];
		$settings = [
			'compress' => Mysqldump::NONE,
			'no-data' => false,
			'add-drop-table' => true,
			'single-transaction' => true,
			'lock-tables' => true,
			'add-locks' => true,
			'extended-insert' => true,
			'disable-foreign-keys-check' => true,
			'skip-triggers' => false,
			'add-drop-trigger' => true,
			'databases' => false,
			'add-drop-database' => true,
			'hex-blob' => true
		];

		try {
			$dump = new Mysqldump($connect, $params['dbuser'], $params['dbpassword'], $settings);
			$dump->start($filename);
		} catch (Throwable $t) {
			throw new SqlDumpException($t->getMessage());
		}
	}


	/**
	 * @param array $params
	 *
	 * @throws SqlParamsException
	 */
	public function setup(array $params): void {
		$port = $this->getInt(ISqlDump::DB_PORT, $params);
		if ($port === 0) {
			$port = null;
		}
		$sql = mysqli_connect(
			$this->get(ISqlDump::DB_HOST, $params),
			$this->get(ISqlDump::DB_USER, $params),
			$this->get(ISqlDump::DB_PASS, $params),
			null,
			$port
		);

		if (is_bool($sql) || is_null($sql)) {
			throw new SqlParamsException('cannot connect to database');
		}

		$dbName = $this->get(ISqlDump::DB_NAME, $params);
		if (!mysqli_select_db($sql, $dbName)) {
			mysqli_query($sql, 'CREATE DATABASE IF NOT EXISTS ' . $dbName);
			if (!mysqli_select_db($sql, $dbName)) {
				throw new SqlParamsException('can connect but cannot create database ' . $dbName);
			}
		}
	}


	/**
	 * @param array $params
	 * @param resource $read
	 *
	 * @return bool
	 */
	public function import(array $params, $read): bool {
		$port = $this->getInt(ISqlDump::DB_PORT, $params);
		if ($port === 0) {
			$port = null;
		}
		$sql = mysqli_connect(
			$this->get(ISqlDump::DB_HOST, $params),
			$this->get(ISqlDump::DB_USER, $params),
			$this->get(ISqlDump::DB_PASS, $params),
			$this->get(ISqlDump::DB_NAME, $params),
			$port
		);

		$request = '';
		while (($line = fgets($read)) !== false) {
			$line = trim($line);
			if (substr($line, 0, 2) === '--' || $line === '') {
				continue;
			}

			$request .= $line;
			if (substr($line, -1) === ';') {
				mysqli_query($sql, $request);
				$request = '';
			}
		}

		mysqli_close($sql);

		return true;
	}
}
