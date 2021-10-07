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


namespace OCA\Backup\Controller;


use ArtificialOwl\MySmallPhpTools\Traits\Nextcloud\nc23\TNC23Controller;
use ArtificialOwl\MySmallPhpTools\Traits\Nextcloud\nc23\TNC23Logger;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSException;
use OCP\AppFramework\OCSController;
use OCP\IRequest;


/**
 * Class LocalController
 *
 * @package OCA\Backup\Controller
 */
class LocalController extends OcsController {


	use TNC23Controller;
	use TNC23Logger;


	/**
	 * LocalController constructor.
	 *
	 * @param string $appName
	 * @param IRequest $request
	 */
	public function __construct(string $appName, IRequest $request) {
		parent::__construct($appName, $request);
	}


	/**
	 * @param int $fileId
	 *
	 * @return DataResponse
	 * @throws OCSException
	 */
	public function scanLocalFolder(int $fileId): DataResponse {
		throw new OcsException('fail + ' . $fileId, Http::STATUS_BAD_REQUEST);

		return new DataResponse(['message' => 'ouila ' . $fileId]);
	}

}

