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


namespace OCA\Backup\Handlers;

use ArtificialOwl\MySmallPhpTools\Exceptions\SignatoryException;
use ArtificialOwl\MySmallPhpTools\Traits\TArrayTools;
use OC\URLGenerator;
use OCA\Backup\AppInfo\Application;
use OCA\Backup\Service\ConfigService;
use OCA\Backup\Service\RemoteStreamService;
use OCP\Http\WellKnown\IHandler;
use OCP\Http\WellKnown\IRequestContext;
use OCP\Http\WellKnown\IResponse;
use OCP\Http\WellKnown\JrdResponse;
use OCP\IURLGenerator;

/**
 * Class WebfingerHandler
 *
 * @package OCA\Backup\Handlers
 */
class WebfingerHandler implements IHandler {
	use TArrayTools;


	/** @var URLGenerator */
	private $urlGenerator;

	/** @var RemoteStreamService */
	private $remoteStreamService;

	/** @var ConfigService */
	private $configService;


	/**
	 * WebfingerHandler constructor.
	 *
	 * @param IURLGenerator $urlGenerator
	 * @param RemoteStreamService $remoteStreamService
	 * @param ConfigService $configService
	 */
	public function __construct(
		IURLGenerator $urlGenerator,
		RemoteStreamService $remoteStreamService,
		ConfigService $configService
	) {
		$this->urlGenerator = $urlGenerator;
		$this->remoteStreamService = $remoteStreamService;
		$this->configService = $configService;
	}


	/**
	 * @param string $service
	 * @param IRequestContext $context
	 * @param IResponse|null $response
	 *
	 * @return IResponse|null
	 */
	public function handle(string $service, IRequestContext $context, ?IResponse $response): ?IResponse {
		if (!$this->configService->isRemoteEnabled() || $service !== 'webfinger') {
			return $response;
		}

		$request = $context->getHttpRequest();
		$subject = $request->getParam('resource', '');
		if ($subject !== Application::APP_SUBJECT) {
			return $response;
		}

		if (!($response instanceof JrdResponse)) {
			$response = new JrdResponse($subject);
		}

		try {
			$this->remoteStreamService->getAppSignatory();
			$href = $this->urlGenerator->linkToRouteAbsolute('backup.Remote.appService');
			$info = [
				'app' => Application::APP_ID,
				'name' => Application::APP_NAME,
				'version' => $this->configService->getAppValue('installed_version')
			];
		} catch (SignatoryException $e) {
			return $response;
		}

		return $response->addLink(Application::APP_REL, 'application/json', $href, [], $info);
	}
}
