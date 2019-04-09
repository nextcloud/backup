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

namespace OCA\Backup\Controller;

use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

class APIController extends OCSController {

	/**
	 * @param string $appName
	 * @param IRequest $request
	 */
	public function __construct($appName, IRequest $request) {
		parent::__construct($appName, $request);
	}

	/**
	 * @param string $path
	 * @param string $password
	 * @return DataResponse
	 */
	public function createBackup($path, $password) {
		$backup = new \OCA\Backup\Backup\Create($path);
		$backup -> password($password);
		$backup -> create();

		return new DataResponse();
	}

	/**
	 * @param string $path
	 * @param string $password
	 * @return DataResponse
	 */
	public function restoreBackup($path, $password) {
		$backup = new \OCA\Backup\Backup\Restore($path);
		$backup -> password($password);
		$backup -> restore();

		return new DataResponse();
	}


}
