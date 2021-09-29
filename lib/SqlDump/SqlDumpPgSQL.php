<?php

declare(strict_types=1);


/**
 * Nextcloud - Backup
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Maxence Lange <maxence@artificial-owl.com>
 * @copyright 2019, Maxence Lange <maxence@artificial-owl.com>
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
	 * @param array $data
	 *
	 * @return string
	 * @throws SqlDumpException
	 */
	public function export(array $data): string {
		$tmpPath = '';
		try {
			$tmp = tmpfile();
			$tmpPath = stream_get_meta_data($tmp)['uri'];
			fclose($tmp);

			PostgreSql::create()
					  ->setDbName($this->get('dbname', $data))
					  ->setUserName($this->get('dbuser', $data))
					  ->setPassword($this->get('dbpassword', $data))
					  ->setHost($this->get('dbhost', $data))
					  ->addExtraOption('--clean --inserts')
					  ->dumpToFile($tmpPath);
		} catch (Throwable $t) {
			if ($tmpPath !== '') {
				unlink($tmpPath);
			}

			throw new SqlDumpException($t->getMessage());
		}

		return $tmpPath;
	}


	/**
	 * @param array $data
	 * @param resource $read
	 *
	 * @return bool
	 */
	public function import(array $data, $read): bool {
		$sql = pg_connect(
			'host=' . $data['dbhost'] .
			' dbname=' . $this->get('dbname', $data) .
			' user=' . $this->get('dbuser', $data) .
			' password=' . $this->get('dbpassword', $data)
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

