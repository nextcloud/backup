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


namespace OCA\Backup\Service;

use ArtificialOwl\MySmallPhpTools\ActivityPub\Nextcloud\nc23\NC23Signature;
use ArtificialOwl\MySmallPhpTools\Exceptions\RequestNetworkException;
use ArtificialOwl\MySmallPhpTools\Exceptions\SignatoryException;
use ArtificialOwl\MySmallPhpTools\Exceptions\SignatureException;
use ArtificialOwl\MySmallPhpTools\Exceptions\WellKnownLinkNotFoundException;
use ArtificialOwl\MySmallPhpTools\Model\Nextcloud\nc23\NC23Request;
use ArtificialOwl\MySmallPhpTools\Model\Nextcloud\nc23\NC23Signatory;
use ArtificialOwl\MySmallPhpTools\Model\Nextcloud\nc23\NC23SignedRequest;
use ArtificialOwl\MySmallPhpTools\Model\Request;
use ArtificialOwl\MySmallPhpTools\Model\SimpleDataStore;
use ArtificialOwl\MySmallPhpTools\Traits\Nextcloud\nc23\TNC23Deserialize;
use ArtificialOwl\MySmallPhpTools\Traits\Nextcloud\nc23\TNC23LocalSignatory;
use ArtificialOwl\MySmallPhpTools\Traits\Nextcloud\nc23\TNC23WellKnown;
use ArtificialOwl\MySmallPhpTools\Traits\TStringTools;
use JsonSerializable;
use OCA\Backup\AppInfo\Application;
use OCA\Backup\Db\RemoteRequest;
use OCA\Backup\Exceptions\RemoteInstanceException;
use OCA\Backup\Exceptions\RemoteInstanceNotFoundException;
use OCA\Backup\Exceptions\RemoteResourceNotFoundException;
use OCA\Backup\Model\RemoteInstance;
use OCA\Backup\Model\RestoringPoint;
use OCP\AppFramework\Http;
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
	 * @param RemoteRequest $remoteRequest
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

		$app->setRoot($this->urlGenerator->linkToRouteAbsolute('backup.Remote.appService'));

		$app->setRPList($this->urlGenerator->linkToRouteAbsolute('backup.Remote.listRestoringPoint'));
		$app->setRPGet(
			urldecode(
				$this->urlGenerator->linkToRouteAbsolute(
					'backup.Remote.getRestoringPoint', ['pointId' => '{pointId}']
				)
			)
		);
		$app->setRPHealth(
			urldecode(
				$this->urlGenerator->linkToRouteAbsolute(
					'backup.Remote.healthRestoringPoint', ['pointId' => '{pointId}']
				)
			)
		);
		$app->setRPDownload(
			urldecode(
				$this->urlGenerator->linkToRouteAbsolute(
					'backup.Remote.downloadRestoringPoint',
					['pointId' => '{pointId}', 'chunkName' => '{chunkName}']
				)
			)
		);
		$app->setRPCreate($this->urlGenerator->linkToRouteAbsolute('backup.Remote.createRestoringPoint'));
		$app->setRPUpdate(
			urldecode(
				$this->urlGenerator->linkToRouteAbsolute(
					'backup.Remote.updateRestoringPoint', ['pointId' => '{pointId}']
				)
			)
		);
		$app->setRPDelete(
			urldecode(
				$this->urlGenerator->linkToRouteAbsolute(
					'backup.Remote.deleteRestoringPoint', ['pointId' => '{pointId}']
				)
			)
		);
		$app->setRPUpload(
			urldecode(
				$this->urlGenerator->linkToRouteAbsolute(
					'backup.Remote.uploadRestoringChunk',
					['pointId' => '{pointId}', 'chunkName' => '{chunkName}']
				)
			)
		);

		$app->setOrigData($this->serialize($app));

		return $app;
	}


	/**
	 *
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
	 * @throws SignatureException
	 */
	public function retrieveRemoteInstance(string $instance): RemoteInstance {
		$resource = $this->getResourceData($instance, Application::APP_SUBJECT, Application::APP_REL);

		/** @var RemoteInstance $remoteInstance */
		$remoteInstance = $this->retrieveSignatory($resource->g('id'));
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
	 * shortcut to requestRemoteInstance that return result if available, or exception.
	 *
	 * @param string $instance
	 * @param string $item
	 * @param int $type
	 * @param JsonSerializable|null $object
	 * @param array $params
	 * @param bool $longTimeout
	 *
	 * @return array
	 * @throws RemoteInstanceException
	 * @throws RemoteInstanceNotFoundException
	 * @throws RemoteResourceNotFoundException
	 */
	public function resultRequestRemoteInstance(
		string $instance,
		string $item,
		int $type = Request::TYPE_GET,
		?JsonSerializable $object = null,
		array $params = [],
		bool $longTimeout = false
	): array {
		$signedRequest =
			$this->requestRemoteInstance($instance, $item, $type, $object, $params, $longTimeout);

		if (!$signedRequest->getOutgoingRequest()->hasResult()) {
			throw new RemoteInstanceException();
		}

		$result = $signedRequest->getOutgoingRequest()->getResult();
		if ($result->getStatusCode() === Http::STATUS_OK) {
			return $result->getAsArray();
		}

		throw new RemoteInstanceException($this->get('message', $result->getAsArray()));
	}


	/**
	 * Send a request to a remote instance, based on:
	 * - instance: address as saved in database,
	 * - item: the item to request (incoming, event, ...)
	 * - type: GET, POST
	 * - data: Serializable to be send if needed
	 *
	 * @param string $instance
	 * @param string $item
	 * @param int $type
	 * @param JsonSerializable|null $object
	 * @param array $params
	 * @param bool $longTimeout
	 *
	 * @return NC23SignedRequest
	 * @throws RemoteInstanceException
	 * @throws RemoteInstanceNotFoundException
	 * @throws RemoteResourceNotFoundException
	 */
	private function requestRemoteInstance(
		string $instance,
		string $item,
		int $type = Request::TYPE_GET,
		?JsonSerializable $object = null,
		array $params = [],
		bool $longTimeout = false
	): NC23SignedRequest {
		$request = new NC23Request('', $type);
		$this->configService->configureRequest($request, $longTimeout);
		$link = $this->getRemoteInstanceEntry($instance, $item, $params);
		$request->basedOnUrl($link);

		// TODO: Work Around: if object is empty, request can takes up to 10s on some configuration
		if (is_null($object) || empty($object->jsonSerialize())) {
			$object = new SimpleDataStore(['empty' => 1]);
		}

		if (!is_null($object)) {
			$request->setDataSerialize($object);
		}

		try {
			$app = $this->getAppSignatory();
//		$app->setAlgorithm(NC22Signatory::SHA512);
			$signedRequest = $this->signOutgoingRequest($request, $app);
			$this->doRequest($signedRequest->getOutgoingRequest(), false);
		} catch (RequestNetworkException | SignatoryException $e) {
			throw new RemoteInstanceException($e->getMessage());
		}

		return $signedRequest;
	}


	/**
	 * get the value of an entry from the Signatory of the RemoteInstance.
	 *
	 * @param string $instance
	 * @param string $item
	 * @param array $params
	 *
	 * @return string
	 * @throws RemoteInstanceNotFoundException
	 * @throws RemoteResourceNotFoundException
	 */
	private function getRemoteInstanceEntry(string $instance, string $item, array $params = []): string {
		$remote = $this->remoteRequest->getByInstance($instance);

		$value = $this->get($item, $remote->getOrigData());
		if ($value === '') {
			throw new RemoteResourceNotFoundException();
		}

		return $this->feedStringWithParams($value, $params);
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
			$this->verifyString($auth, $signed, $remote->getPublicKey(), $algo);
			$remote->setIdentityAuthed(true);
		} catch (SignatureException $e) {
			$this->e(
				$e,
				['auth' => $auth, 'signed' => $signed, 'signatory' => $remote, 'msg' => 'auth not confirmed']
			);
			throw new SignatureException('auth not confirmed');
		}
	}


	/**
	 * @param RestoringPoint $point
	 *
	 * @throws SignatoryException
	 */
	public function signPoint(RestoringPoint $point) {
		$this->signModel($point, $this->getAppSignatory(true));
		$this->subSignPoint($point);
	}

	/**
	 * @param RestoringPoint $point
	 *
	 * @throws SignatoryException
	 * @throws SignatureException
	 */
	public function verifyPoint(RestoringPoint $point) {
		$this->verifyModel($point, $this->getAppSignatory()->getPublicKey());
	}


	/**
	 * @param RestoringPoint $point
	 *
	 * @throws SignatoryException
	 */
	public function subSignPoint(RestoringPoint $point): void {
		$string = json_encode($point->subSignedData());
		$signature = $this->signString($string, $this->getAppSignatory(false));
		$point->setSubSignature($signature);
	}

	/**
	 * @param RestoringPoint $point
	 *
	 * @throws SignatoryException
	 * @throws SignatureException
	 */
	public function verifySubSign(RestoringPoint $point) {
		$string = json_encode($point->subSignedData());
		$this->verifyString($string, $point->getSubSignature(), $this->getAppSignatory()->getPublicKey());
	}
}
