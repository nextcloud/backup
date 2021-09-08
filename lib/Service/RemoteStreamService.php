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


namespace OCA\Backup\Service;


use ArtificialOwl\MySmallPhpTools\ActivityPub\Nextcloud\nc23\NC23Signature;
use ArtificialOwl\MySmallPhpTools\Exceptions\RequestNetworkException;
use ArtificialOwl\MySmallPhpTools\Exceptions\SignatoryException;
use ArtificialOwl\MySmallPhpTools\Exceptions\SignatureException;
use ArtificialOwl\MySmallPhpTools\Exceptions\WellKnownLinkNotFoundException;
use ArtificialOwl\MySmallPhpTools\Model\Nextcloud\nc23\NC23Request;
use ArtificialOwl\MySmallPhpTools\Model\Nextcloud\nc23\NC23Signatory;
use ArtificialOwl\MySmallPhpTools\Traits\Nextcloud\nc23\TNC23Deserialize;
use ArtificialOwl\MySmallPhpTools\Traits\Nextcloud\nc23\TNC23LocalSignatory;
use ArtificialOwl\MySmallPhpTools\Traits\Nextcloud\nc23\TNC23WellKnown;
use ArtificialOwl\MySmallPhpTools\Traits\TStringTools;
use OCA\Backup\AppInfo\Application;
use OCA\Backup\Db\RemoteRequest;
use OCA\Backup\Exceptions\RemoteInstanceNotFoundException;
use OCA\Backup\Model\RemoteInstance;
use OCP\IURLGenerator;


/**
 * Class RemoteStreamService
 *
 * @package OCA\Backup\Service
 */
class RemoteStreamService extends NC23Signature {


	use TNC23Deserialize;
	use TNC23LocalSignatory;
	use TStringTools;
	use TNC23WellKnown;


	/** @var IURLGenerator */
	private $urlGenerator;

	/** @var RemoteRequest */
	private $remoteRequest;

	/** @var ConfigService */
	private $configService;


	/**
	 * RemoteStreamService constructor.
	 *
	 * @param IURLGenerator $urlGenerator
	 * @param ConfigService $configService
	 */
	public function __construct(
		IURLGenerator $urlGenerator,
		RemoteRequest $remoteRequest,
		ConfigService $configService
	) {
		$this->urlGenerator = $urlGenerator;
		$this->remoteRequest = $remoteRequest;
		$this->configService = $configService;

		$this->setup('app', 'backup');
	}


	/**
	 * Returns the Signatory model for the Backup app.
	 * Can be signed with a confirmKey.
	 *
	 * @param bool $generate
	 * @param string $confirmKey
	 *
	 * @return RemoteInstance
	 * @throws SignatoryException
	 */
	public function getAppSignatory(bool $generate = true, string $confirmKey = ''): RemoteInstance {
		$app = new RemoteInstance($this->urlGenerator->linkToRouteAbsolute('backup.Remote.appService'));
		$this->fillSimpleSignatory($app, $generate);
		$app->setUidFromKey();

		if ($confirmKey !== '') {
			$app->setAuthSigned($this->signString($confirmKey, $app));
		}

		$app->setRoot($this->urlGenerator->linkToRouteAbsolute('backup.Remote.test'));
		$app->setTest($this->urlGenerator->linkToRouteAbsolute('backup.Remote.test'));

		$app->setOrigData($this->serialize($app));

		return $app;
	}


	/**
	 * Reset the Signatory (and the Identity) for the Circles app.
	 */
	public function resetAppSignatory(): void {
		try {
			$app = $this->getAppSignatory();

			$this->removeSimpleSignatory($app);
		} catch (SignatoryException $e) {
		}
	}


	/**
	 * Add a remote instance, based on the address
	 *
	 * @param string $instance
	 *
	 * @return RemoteInstance
	 * @throws RequestNetworkException
	 * @throws SignatoryException
	 * @throws WellKnownLinkNotFoundException
	 */
	public function retrieveRemoteInstance(string $instance): RemoteInstance {
		$resource = $this->getResourceData($instance, Application::APP_SUBJECT, Application::APP_REL);

		/** @var RemoteInstance $remoteInstance */
		$remoteInstance = $this->retrieveSignatory($resource->g('id'), true);
		$remoteInstance->setInstance($instance);

		return $remoteInstance;
	}


	/**
	 * retrieve Signatory.
	 *
	 * @param string $keyId
	 * @param bool $refresh
	 *
	 * @return NC23Signatory
	 * @throws SignatoryException
	 * @throws SignatureException
	 */
	public function retrieveSignatory(string $keyId, bool $refresh = true): NC23Signatory {
		if (!$refresh) {
			try {
				return $this->remoteRequest->getFromHref(NC23Signatory::removeFragment($keyId));
			} catch (RemoteInstanceNotFoundException $e) {
				throw new SignatoryException();
			}
		}

		$remoteInstance = new RemoteInstance($keyId);
		$confirm = $this->uuid();

		$request = new NC23Request();
		$this->configService->configureRequest($request);

		$this->downloadSignatory($remoteInstance, $keyId, ['auth' => $confirm], $request);
		$remoteInstance->setUidFromKey();

		$this->confirmAuth($remoteInstance, $confirm);

		return $remoteInstance;
	}


	/**
	 * Confirm the Auth of a RemoteInstance, based on the result from a request
	 *
	 * @param RemoteInstance $remote
	 * @param string $auth
	 *
	 * @throws SignatureException
	 */
	private function confirmAuth(RemoteInstance $remote, string $auth): void {
		[$algo, $signed] = explode(':', $this->get('auth-signed', $remote->getOrigData()));
		try {
			if ($signed === null) {
				throw new SignatureException('invalid auth-signed');
			}
			$this->verifyString($auth, base64_decode($signed), $remote->getPublicKey(), $algo);
			$remote->setIdentityAuthed(true);
		} catch (SignatureException $e) {
			$this->e(
				$e,
				['auth' => $auth, 'signed' => $signed, 'signatory' => $remote, 'msg' => 'auth not confirmed']
			);
			throw new SignatureException('auth not confirmed');
		}
	}

}

