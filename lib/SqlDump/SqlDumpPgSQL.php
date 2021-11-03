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
use OCA\Backup\Exceptions\SqlDumpException;
use OCA\Backup\Exceptions\SqlParamsException;
use OCA\Backup\ISqlDump;
use Spatie\DbDumper\Databases\PostgreSql;
use Throwable;

/**
 * Class SqlDumpPgSQL
 *
 * @package OCA\Backup\SqlDump
 */
class SqlDumpPgSQL implements ISqlDump {
	use TArrayTools;


	/**
	 * SqlDumpPgSQL constructor.
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
		try {
			$dump = PostgreSql::create();
			$dump->setDbName($this->get(ISqlDump::DB_NAME, $params))
				 ->setUserName($this->get(ISqlDump::DB_USER, $params))
				 ->setPassword($this->get(ISqlDump::DB_PASS, $params))
				 ->setHost($this->get(ISqlDump::DB_HOST, $params));

			$port = $this->getInt(ISqlDump::DB_PORT, $params);
			if ($port > 0) {
				$dump->setPort($port);
			}

			$dump->addExtraOption('--clean --inserts')
				 ->dumpToFile($filename);
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
		$sql = pg_connect(
			'host=' . $this->get(ISqlDump::DB_HOST, $params) .
			' dbname=' . $this->get(ISqlDump::DB_NAME, $params) .
			' user=' . $this->get(ISqlDump::DB_USER, $params) .
			' password=' . $this->get(ISqlDump::DB_PASS, $params)
		);

		if (is_bool($sql) || is_null($sql)) {
			throw new SqlParamsException('cannot connect to database');
		}

		$dbName = $this->get(ISqlDump::DB_NAME, $params);
		$check = pg_query($sql, 'SELECT FROM pg_database WHERE datname=\'' . $dbName . '\'');
		if (pg_num_rows($check) === 0) {
			pg_query($sql, 'CREATE DATABASE \'' . $dbName . '\'');
			$check = pg_query($sql, 'SELECT FROM pg_database WHERE datname=\'' . $dbName . '\'');
			if (pg_num_rows($check) === 0) {
				throw new SqlParamsException('cannot create database');
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
		$sql = pg_connect(
			'host=' . $this->get(ISqlDump::DB_HOST, $params) .
			' dbname=' . $this->get(ISqlDump::DB_NAME, $params) .
			' user=' . $this->get(ISqlDump::DB_USER, $params) .
			' password=' . $this->get(ISqlDump::DB_PASS, $params)
		);

		$request = '';
		while (($line = fgets($read)) !== false) {
			$content = trim($line);
			if (substr($content, 0, 2) === '--' || $content === '') {
				continue;
			}

			$request .= $line;
			if (substr($content, -1) === ';') {
				pg_query($sql, $request);
				$request = '';
			}
		}

		pg_close($sql);

		return true;
	}
}
