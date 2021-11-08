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

use ArtificialOwl\MySmallPhpTools\Exceptions\InvalidOriginException;
use ArtificialOwl\MySmallPhpTools\Exceptions\JsonNotRequestedException;
use ArtificialOwl\MySmallPhpTools\Exceptions\MalformedArrayException;
use ArtificialOwl\MySmallPhpTools\Exceptions\SignatoryException;
use ArtificialOwl\MySmallPhpTools\Exceptions\SignatureException;
use ArtificialOwl\MySmallPhpTools\Model\Nextcloud\nc23\NC23SignedRequest;
use ArtificialOwl\MySmallPhpTools\Model\SimpleDataStore;
use ArtificialOwl\MySmallPhpTools\Traits\Nextcloud\nc23\TNC23Controller;
use ArtificialOwl\MySmallPhpTools\Traits\Nextcloud\nc23\TNC23Logger;
use Exception;
use OC;
use OC\AppFramework\Middleware\Security\Exceptions\NotLoggedInException;
use OCA\Backup\AppInfo\Application;
use OCA\Backup\Exceptions\RemoteRequestException;
use OCA\Backup\IRemoteRequest;
use OCA\Backup\Model\RemoteInstance;
use OCA\Backup\RemoteRequest\CreateRestoringPoint;
use OCA\Backup\RemoteRequest\DeleteRestoringPoint;
use OCA\Backup\RemoteRequest\DownloadRestoringChunk;
use OCA\Backup\RemoteRequest\GetRestoringPoint;
use OCA\Backup\RemoteRequest\ListRestoringPoint;
use OCA\Backup\RemoteRequest\UpdateRestoringPoint;
use OCA\Backup\RemoteRequest\UploadRestoringChunk;
use OCA\Backup\Service\RemoteStreamService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use ReflectionClass;
use ReflectionException;

/**
 * Class RemoteController
 *
 * @package OCA\Backup\Controller
 */
class RemoteController extends Controller {
	use TNC23Controller;
	use TNC23Logger;


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

		$this->setup('app', Application::APP_ID);
		$this->setupArray('enforceSignatureHeaders', ['digest', 'content-length']);
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


	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 *
	 * @return DataResponse
	 */
	public function createRestoringPoint(): DataResponse {
		try {
			$request = $this->extractRequest(CreateRestoringPoint::class);
		} catch (Exception $e) {
			return $this->exceptionResponse($e, Http::STATUS_UNAUTHORIZED);
		}

		try {
			$request->execute();

			return new DataResponse($request->getOutcome());
		} catch (Exception $e) {
			$this->e($e, ['request' => $request]);

			return $this->exceptionResponse($e);
		}
	}


	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 *
	 * @return DataResponse
	 */
	public function listRestoringPoint(): DataResponse {
		try {
			$request = $this->extractRequest(ListRestoringPoint::class);
		} catch (Exception $e) {
			return $this->exceptionResponse($e, Http::STATUS_UNAUTHORIZED);
		}

		try {
			$request->execute();

			return new DataResponse($request->getOutcome());
		} catch (Exception $e) {
			$this->e($e, ['request' => $request]);

			return $this->exceptionResponse($e);
		}
	}


	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 *
	 * @param string $pointId
	 *
	 * @return DataResponse
	 */
	public function getRestoringPoint(string $pointId): DataResponse {
		try {
			$request = $this->extractRequest(GetRestoringPoint::class);
		} catch (Exception $e) {
			return $this->exceptionResponse($e, Http::STATUS_UNAUTHORIZED);
		}

		try {
			$request->execute();

			return new DataResponse($request->getOutcome());
		} catch (Exception $e) {
			$this->e($e, ['request' => $request]);

			return $this->exceptionResponse($e);
		}
	}


	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 *
	 * @param string $pointId
	 *
	 * @return DataResponse
	 */
	public function updateRestoringPoint(string $pointId): DataResponse {
		try {
			$request = $this->extractRequest(UpdateRestoringPoint::class);
		} catch (Exception $e) {
			return $this->exceptionResponse($e, Http::STATUS_UNAUTHORIZED);
		}

		try {
			$request->execute();

			return new DataResponse($request->getOutcome());
		} catch (Exception $e) {
			$this->e($e, ['request' => $request]);

			return $this->exceptionResponse($e);
		}
	}



	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 *
	 * @param string $pointId
	 *
	 * @return DataResponse
	 */
	public function deleteRestoringPoint(string $pointId): DataResponse {
		try {
			$request = $this->extractRequest(DeleteRestoringPoint::class);
		} catch (Exception $e) {
			return $this->exceptionResponse($e, Http::STATUS_UNAUTHORIZED);
		}

		try {
			$request->execute();

			return new DataResponse($request->getOutcome());
		} catch (Exception $e) {
			$this->e($e, ['request' => $request]);

			return $this->exceptionResponse($e);
		}
	}



	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 *
	 * @param string $pointId
	 *
	 * @return DataResponse
	 */
	public function healthRestoringPoint(string $pointId): DataResponse {
		try {
			$request = $this->extractRequest(GetRestoringPoint::class);
		} catch (Exception $e) {
			return $this->exceptionResponse($e, Http::STATUS_UNAUTHORIZED);
		}

		try {
			$request->config(new SimpleDataStore(['refreshHealth' => true]));
			$request->execute();

			return new DataResponse($request->getOutcome());
		} catch (Exception $e) {
			$this->e($e, ['request' => $request]);

			return $this->exceptionResponse($e);
		}
	}


	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 *
	 * @param string $pointId
	 * @param string $chunkName
	 *
	 * @return DataResponse
	 */
	public function downloadRestoringPoint(string $pointId, string $chunkName): DataResponse {
		try {
			$request = $this->extractRequest(DownloadRestoringChunk::class);
		} catch (Exception $e) {
			return $this->exceptionResponse($e, Http::STATUS_UNAUTHORIZED);
		}

		try {
			$request->execute();

			return new DataResponse($request->getOutcome());
		} catch (Exception $e) {
			$this->e($e, ['request' => $request]);

			return $this->exceptionResponse($e);
		}
	}


	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 *
	 * @param string $pointId
	 * @param string $chunkName
	 *
	 * @return DataResponse
	 */
	public function uploadRestoringChunk(string $pointId, string $chunkName): DataResponse {
		try {
			$request = $this->extractRequest(UploadRestoringChunk::class);
		} catch (Exception $e) {
			return $this->exceptionResponse($e, Http::STATUS_UNAUTHORIZED);
		}

		try {
			$request->execute();

			return new DataResponse($request->getOutcome());
		} catch (Exception $e) {
			$this->e($e, ['request' => $request]);

			return $this->exceptionResponse($e);
		}
	}


	/**
	 * @param string $class
	 *
	 * @return IRemoteRequest
	 * @throws RemoteRequestException
	 * @throws SignatoryException
	 * @throws InvalidOriginException
	 * @throws MalformedArrayException
	 * @throws SignatureException
	 */
	private function extractRequest(string $class = ''): ?IRemoteRequest {
		$signed = $this->remoteStreamService->incomingSignedRequest();
		$this->confirmRemoteInstance($signed);

		if ($class === '') {
			return null;
		}

		try {
			$test = new ReflectionClass($class);
		} catch (ReflectionException $e) {
			throw new RemoteRequestException('ReflectionException with ' . $class . ': ' . $e->getMessage());
		}

		if (!in_array(IRemoteRequest::class, $test->getInterfaceNames())) {
			throw new RemoteRequestException($class . ' does not implements IRemoteRequest');
		}

		$item = OC::$server->get($class);
		if (!($item instanceof IRemoteRequest)) {
			throw new RemoteRequestException($class . ' not an IRemoteRequest');
		}

		$item->import(json_decode($signed->getBody(), true));
		$item->setSignedRequest($signed);

		return $item;
	}


	/**
	 * @param NC23SignedRequest $signedRequest
	 *
	 * @return RemoteInstance
	 * @throws SignatoryException
	 */
	private function confirmRemoteInstance(NC23SignedRequest $signedRequest): RemoteInstance {
		/** @var RemoteInstance $signatory */
		$signatory = $signedRequest->getSignatory();

		if (!$signatory instanceof RemoteInstance) {
			$this->debug('Signatory is not a known RemoteInstance', ['signedRequest' => $signedRequest]);
			throw new SignatoryException('Could not confirm identity');
		}

		if (!$signatory->isIncoming()) {
			throw new SignatoryException('Remote instance is not configured as Incoming');
		}

		return $signatory;
	}


	/**
	 * @param Exception $e
	 * @param int $httpErrorCode
	 *
	 * @return DataResponse
	 */
	public function exceptionResponse(
		Exception $e,
		int $httpErrorCode = Http::STATUS_BAD_REQUEST
	): DataResponse {
		return new DataResponse(
			[
				'message' => $e->getMessage(),
				'code' => $e->getCode()
			],
			($e->getCode() > 0) ? $e->getCode() : $httpErrorCode
		);
	}
}
