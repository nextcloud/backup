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

	/** @var currentDate */
	protected $currentDate;

	/** @var rsyncOptions */
	protected $rsyncOptions;

	/**
	 * @param string $path
	 */
	public function __construct($path) {
		$this->path = $path;
		$this->currentDate = date('c');
	}

	/**
	 * @param string $password
	 */
	public function password($password) {
		$this->password = $password;
	}

	/**
	 * @param string $path
	 *
	 * @return string $directory
	 */
	 private function getLastBackup($path) {
		 $directories = scandir($path, SCANDIR_SORT_DESCENDING);
		 $key = array_search('initial', $directories);
		 unset($directories[$key]);
		 $directory = reset($directories);
		 \OC::$server->getLogger()->warning('Last backup: '.$directory);
		 return $directory;
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
				\OC::$server->getLogger()->error('mysqldump is not installed',['app' => 'backup',]);
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
	 * @param string $sourcePath
	 * @param string $destPath
	 */
	 private function copyFiles($sourcePath, $destPath) {
		 $check = shell_exec('which rsync');
		 if(!empty($check)) {
			 $output = shell_exec('rsync '.$this->rsyncOptions.' '.$sourcePath.' '.$destPath);
		 } else {
			 \OC::$server->getLogger()->error('rsync is not installed',['app' => 'backup',]);
		 }
	 }

	 /**
	  * @param string $path
		* @param bool $realSize
		*
		* @return int $size
		*/
	 private function getDirectorySize($path, $realSize = TRUE) {
		 $check = shell_exec('which du');
		 if($realSize === TRUE) {
		 	 $options = '-sk --inodes';
		 } else {
		 	 $options = '-sk';
		 }
		 if(!empty($check)) {
			 $cmd = 'du '.$options.' "'.$path.'"';
			 $output = shell_exec($cmd);
			 $size = explode("\t", $output);
			 return $size[0];
		 } else {
			 \OC::$server->getLogger()->error('du is not installed',['app' => 'backup',]);
		 }
	 }

	/**
	 * @param string $path
	 */
	private function writeMeta($path) {
		$meta = array('date'				=> $this->currentDate,
									'directory'		=> $path,
									'size'				=> $this->getDirectorySize($path, FALSE),
									'realsize'		=> $this->getDirectorySize($path),
									'instanceid'	=> \OCP\Config::getSystemValue('instanceid'),
									'dbtype'			=> \OCP\Config::getSystemValue('dbtype'));
		file_put_contents($path.'/backup.json', json_encode($meta));
	}

	public function create() {
		\OC::$server->getLogger()->warning('Create backup! Path:'.$this->path.' Password:'.$this->password,['app' => 'backup',]);

		$rsyncOptionsGeneral = '-a -l -z --delete --delete-excluded --exclude=lost+found --exclude=*.swp';
		$rsyncOptionsIncremental = '-a -l -H -z --link-dest='.$this->path.'/initial';

		if(!is_dir($this->path) || !is_writable($this->path)) {
			\OC::$server->getLogger()->error('Can\'t access directory '.$this->path,['app' => 'backup',]);
			exit;
		}

		if(!file_exists($this->path.'/initial')) {
			mkdir($this->path.'/initial');
		}

		if(!file_exists($this->path.'/'.$this->currentDate)) {
			mkdir($this->path.'/'.$this->currentDate);
		}

		$lastBackup = $this->getLastBackup($this->path);

		$this->rsyncOptions = $rsyncOptionsGeneral;

		// pre-syncing data directory (shorter maintenance time)
		$this->copyFiles(\OCP\Config::getSystemValue('datadirectory'), $this->path.'/initial/data');

		$maintainance = \OCP\Config::getSystemValue('maintenance');
		\OCP\Config::setSystemValue('maintenance',true);

		// create DB dump
		$this->createDump(
			\OCP\Config::getSystemValue('dbtype'),
			\OCP\Config::getSystemValue('dbname'),
			\OCP\Config::getSystemValue('dbuser'),
			\OCP\Config::getSystemValue('dbpassword'),
			\OCP\Config::getSystemValue('dbhost'),
			$this->path.'/'.$this->currentDate
		);

		// copy config folder
		$this->copyFiles(\OC::$configDir, $this->path.'/initial/config');

		// post-syncing data directory
		$this->copyFiles(\OCP\Config::getSystemValue('datadirectory'), $this->path.'/initial/data');

		\OCP\Config::setSystemValue('maintenance',$maintainance);

		$this->rsyncOptions = $rsyncOptionsIncremental;
		$this->copyFiles($this->path.'/initial/', $this->path.'/'.$lastBackup);

		// write meta file
		$this->writeMeta($this->path.'/'.$this->currentDate);
	}
}
