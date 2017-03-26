<?php
/**
 * @copyright Copyright (c) 2017 Frank Karlitschek <frank@karlitschek.de>
 *
 * @author Frank Karlitschek <frank@karlitchek.de>
 *
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

namespace OCA\Backup\Backup;

use OCA\Backup\AppInfo\Application;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IRequest;

class Restore {

	/** @var path */
	protected $path;

	/** @var password */
	protected $password;

	/**
	 * @param string $path
	 */
	public function __construct($path) {
		$this->path = $path;
	}

	/**
	 * @param string $password
	 */
	public function password($password) {
		$this->password = $password;
	}

	/**
	 * @param string $dbtype
	 * @param string $dbname
	 * @param string $dbuser
 	 * @param string $dbpassword
	 * @param string $dbhost
	 * @param string $path
	 */
	private function restoreDump($dbtype,$dbname,$dbuser,$dbpassword,$dbhost,$path) {
	}

	/**
	 * @param string $path
	 */
	private function copydata($path) {
	}

	/**
	 * @param string $path
	 */
	private function copyconfig($path) {
	}

	/**
	 * @param string $path
	 */
	private function readmeta($path) {
	}

	/**
	 */
	public function restore() {
		\OC::$server->getLogger()->warning('Restore backup! Path:'.$this->path.' Password:'.$this->password,['app' => 'backup',]);
	}
}
