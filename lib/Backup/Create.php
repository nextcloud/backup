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

class Create {

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
	private function createDump($dbtype,$dbname,$dbuser,$dbpassword,$dbhost,$path) {
		switch ($dbtype) {
    	case 'sqlite3':
        	// nothing to do because DB file is already in the data folder
        	break;
    	case 'mysql':
			$check = shell_exec('which mysqldump');
			if(!empty($check)) {
				$cmd = 'mysqldump --password='.$dbpassword.' --user='.$dbuser.' --host='.$dbhost.' '.$dbname.' >'.$path.'/mysql.dmp';
				shell_exec($cmd);
			} else {
				\OC::$server->getLogger()->error('rsync is not installed',['app' => 'backup',]);
			}
        	break;
    	case 'pqsql':
        	// todo
        	break;
		case 'oci':
			// todo
			break;
		}
	}

	/**
	 * @param string $path
	 */
	private function copydata($path) {
		$check = shell_exec('which rsync');
		if(!empty($check)) {
			$output = shell_exec('rsync -r '.\OCP\Config::getSystemValue('datadirectory').' '.$path);
		} else {
			\OC::$server->getLogger()->error('rsync is not installed',['app' => 'backup',]);
		}
	}

	/**
	 * @param string $path
	 */
	private function copyconfig($path) {
		$check = shell_exec('which rsync');
		if(!empty($check)) {
			$output = shell_exec('rsync -r '.\OC::$configDir.' '.$path.'/config');
		} else {
			\OC::$server->getLogger()->error('rsync is not installed',['app' => 'backup',]);
		}
	}

	/**
	 * @param string $path
	 */
	private function writemeta($path) {
		$meta = array('date' => date('c'), 'instanceid' => \OCP\Config::getSystemValue('instanceid'), 'dbtype' => \OCP\Config::getSystemValue('dbtype'));
		file_put_contents($path.'/backup.json', json_encode($meta));
	}

	/**
	 */
	public function create() {
		\OC::$server->getLogger()->warning('Create backup! Path:'.$this->path.' Password:'.$this->password,['app' => 'backup',]);

		if(!is_dir($this->path) || !is_writable($this->path)) {
			\OC::$server->getLogger()->error('Can\'t access directory '.$this->path,['app' => 'backup',]);
			exit;
		}

		$maintainance = \OCP\Config::getSystemValue('maintenance');
		\OCP\Config::setSystemValue('maintenance',true);

		// create DB dump
		$this->createDump(
			\OCP\Config::getSystemValue('dbtype'),
			\OCP\Config::getSystemValue('dbname'),
			\OCP\Config::getSystemValue('dbuser'),
			\OCP\Config::getSystemValue('dbpassword'),
			\OCP\Config::getSystemValue('dbhost'),
			$this->path
		);

		// copy config folder
		$this->copyconfig($this->path);

		// syncing data directory
		$this->copydata($this->path);

		// write meta file
		$this->writemeta($this->path);

		\OCP\Config::setSystemValue('maintenance',$maintainance);

	}
}
