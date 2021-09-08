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


namespace OCA\Backup\Controller;


use ArtificialOwl\MySmallPhpTools\Exceptions\JsonNotRequestedException;
use ArtificialOwl\MySmallPhpTools\Exceptions\SignatoryException;
use ArtificialOwl\MySmallPhpTools\Traits\Nextcloud\nc22\TNC22Controller;
use OC\AppFramework\Middleware\Security\Exceptions\NotLoggedInException;
use OCA\Backup\Service\RemoteStreamService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;


/**
 * Class RemoteController
 *
 * @package OCA\Backup\Controller
 */
class RemoteController extends Controller {


	use TNC22Controller;


	/** @var RemoteStreamService */
	private $remoteStreamService;


	/**
	 * RemoteController constructor.
	 *
	 * @param string $appName
	 * @param IRequest $request
	 * @param RemoteStreamService $remoteStreamService
	 */
	public function __construct(
		string $appName,
		IRequest $request,
		RemoteStreamService $remoteStreamService
	) {
		parent::__construct($appName, $request);

		$this->remoteStreamService = $remoteStreamService;
	}


	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 *
	 * @return DataResponse
	 * @throws NotLoggedInException
	 * @throws SignatoryException
	 */
	public function appService(): DataResponse {
		try {
			$this->publicPageJsonLimited();
		} catch (JsonNotRequestedException $e) {
			return new DataResponse();
		}

		$signatory = $this->remoteStreamService->getAppSignatory(false, $this->request->getParam('auth', ''));

		return new DataResponse($signatory);
	}

}

