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


use Exception;
use Ifsnop\Mysqldump\Mysqldump;
use OCA\Backup\ISqlDump;


/**
 * Class SqlDumpMySQL
 *
 * @package OCA\Backup\SqlDump
 */
class SqlDumpMySQL implements ISqlDump {


	/**
	 * SqlDumpMySQL constructor.
	 */
	public function __construct() {
	}


	/**
	 * @param array $data
	 *
	 * @return string
	 * @throws Exception
	 */
	public function export(array $data): string {
		$connect = 'mysql:host=' . $data['dbhost'] . ';dbname=' . $data['dbname'];
		$settings = [
			'compress'                   => Mysqldump::NONE,
			'no-data'                    => false,
			'add-drop-table'             => true,
			'single-transaction'         => true,
			'lock-tables'                => true,
			'add-locks'                  => true,
			'extended-insert'            => true,
			'disable-foreign-keys-check' => true,
			'skip-triggers'              => false,
			'add-drop-trigger'           => true,
			'databases'                  => false,
			'add-drop-database'          => true,
			'hex-blob'                   => true
		];

		$dump = new Mysqldump($connect, $data['dbuser'], $data['dbpassword'], $settings);

		ob_start();
		$dump->start();
		$content = ob_get_clean();

		return $content;
	}


	/**
	 * @param array $data
	 * @param resource $read
	 *
	 * @return bool
	 */
	public function import(array $data, $read): bool {
		$sql =
			mysqli_connect($data['dbhost'], $data['dbuser'], $data['dbpassword'], $data['dbname']);

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

