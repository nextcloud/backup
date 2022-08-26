<?php
/*
 * Nextcloud - Backup now. Restore later.
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Maxence Lange <maxence@artificial-owl.com>
 * @copyright 2017
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

declare(strict_types=1);


/**
 * Nextcloud - Backup now. Restore later.
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Maxence Lange <maxence@artificial-owl.com>
 * @copyright 2022
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


namespace OCA\Backup\Tools\ActivityPub;

use DateTime;
use Exception;
use OC;
use OCA\Backup\Tools\Exceptions\InvalidOriginException;
use OCA\Backup\Tools\Exceptions\ItemNotFoundException;
use OCA\Backup\Tools\Exceptions\MalformedArrayException;
use OCA\Backup\Tools\Exceptions\SignatoryException;
use OCA\Backup\Tools\Exceptions\SignatureException;
use OCA\Backup\Tools\Model\NCRequest;
use OCA\Backup\Tools\Model\NCSignatory;
use OCA\Backup\Tools\Model\NCSignedRequest;
use OCA\Backup\Tools\Model\SimpleDataStore;
use OCA\Backup\Tools\Traits\TNCSignatory;
use OCP\IRequest;

class NCSignature {
	public const DATE_HEADER = 'D, d M Y H:i:s T';
	public const DATE_OBJECT = 'Y-m-d\TH:i:s\Z';

	public const DATE_TTL = 300;


	use TNCSignatory;


	/** @var int */
	private $ttl = self::DATE_TTL;
	private $dateHeader = self::DATE_HEADER;


	/**
	 * @param string $body
	 *
	 * @return NCSignedRequest
	 * @throws InvalidOriginException
	 * @throws MalformedArrayException
	 * @throws SignatoryException
	 * @throws SignatureException
	 */
	public function incomingSignedRequest(string $body = ''): NCSignedRequest {
		if ($body === '') {
			$body = file_get_contents('php://input');
		}

		$this->debug('[<<] incoming', ['body' => $body]);

		$signedRequest = new NCSignedRequest($body);
		$signedRequest->setIncomingRequest(OC::$server->get(IRequest::class));

		$this->verifyIncomingRequestTime($signedRequest);
		$this->verifyIncomingRequestContent($signedRequest);
		$this->setIncomingSignatureHeader($signedRequest);
		$this->setIncomingClearSignature($signedRequest);
		$this->parseIncomingSignatureHeader($signedRequest);
		$this->verifyIncomingRequestSignature($signedRequest);

		return $signedRequest;
	}


	/**
	 * @param NCRequest $request
	 * @param NCSignatory $signatory
	 *
	 * @return NCSignedRequest
	 * @throws SignatoryException
	 */
	public function signOutgoingRequest(NCRequest $request, NCSignatory $signatory): NCSignedRequest {
		$signedRequest = new NCSignedRequest($request->getDataBody());
		$signedRequest->setOutgoingRequest($request)
					  ->setSignatory($signatory);

		$this->setOutgoingSignatureHeader($signedRequest);
		$this->setOutgoingClearSignature($signedRequest);
		$this->setOutgoingSignedSignature($signedRequest);
		$this->signingOutgoingRequest($signedRequest);

		return $signedRequest;
	}


	/**
	 * @param NCSignedRequest $signedRequest
	 *
	 * @throws SignatureException
	 */
	private function verifyIncomingRequestTime(NCSignedRequest $signedRequest): void {
		$request = $signedRequest->getIncomingRequest();

		try {
			$dTime = new DateTime($request->getHeader('date'));
			$signedRequest->setTime($dTime->getTimestamp());
		} catch (Exception $e) {
			$this->e($e, ['header' => $request->getHeader('date')]);
			throw new SignatureException('datetime exception');
		}

		if ($signedRequest->getTime() < (time() - $this->ttl)) {
			throw new SignatureException('object is too old');
		}
	}


	/**
	 * @param NCSignedRequest $signedRequest
	 *
	 * @throws SignatureException
	 */
	private function verifyIncomingRequestContent(NCSignedRequest $signedRequest): void {
		$request = $signedRequest->getIncomingRequest();

		if (strlen($signedRequest->getBody()) !== (int)$request->getHeader('content-length')) {
			throw new SignatureException('issue with content-length');
		}

		if ($request->getHeader('digest') !== ''
			&& $signedRequest->getDigest() !== $request->getHeader('digest')) {
			throw new SignatureException('issue with digest');
		}
	}

	/**
	 * @param NCSignedRequest $signedRequest
	 */
	private function setIncomingSignatureHeader(NCSignedRequest $signedRequest): void {
		$sign = [];
		$request = $signedRequest->getIncomingRequest();
		foreach (explode(',', $request->getHeader('Signature')) as $entry) {
			if ($entry === '' || !strpos($entry, '=')) {
				continue;
			}

			[$k, $v] = explode('=', $entry, 2);
			preg_match('/"([^"]+)"/', $v, $varr);
			if ($varr[0] !== null) {
				$v = trim($varr[0], '"');
			}
			$sign[$k] = $v;
		}

		$signedRequest->setSignatureHeader(new SimpleDataStore($sign));
	}


	/**
	 * @param NCSignedRequest $signedRequest
	 *
	 * @throws SignatureException
	 */
	private function setIncomingClearSignature(NCSignedRequest $signedRequest): void {
		$request = $signedRequest->getIncomingRequest();
		$headers = explode(' ', $signedRequest->getSignatureHeader()->g('headers'));

		$enforceHeaders = array_merge(
			['content-length', 'date', 'host'],
			$this->setupArray('enforceSignatureHeaders')
		);
		if (!empty(array_diff($enforceHeaders, $headers))) {
			throw new SignatureException('missing elements in \'headers\'');
		}

		$target = strtolower($request->getMethod()) . " " . $request->getRequestUri();
		$estimated = ['(request-target): ' . $target];

		foreach ($headers as $key) {
			$value = $request->getHeader($key);
			if (strtolower($key) === 'host') {
				$value = $signedRequest->getIncomingRequest()->getServerHost();
			}
			if ($value === '') {
				throw new SignatureException('empty elements in \'headers\'');
			}

			$estimated[] = $key . ': ' . $value;
		}
		$signedRequest->setClearSignature(implode("\n", $estimated));
	}


	/**
	 * @param NCSignedRequest $signedRequest
	 *
	 * @throws MalformedArrayException
	 * @throws InvalidOriginException
	 */
	private function parseIncomingSignatureHeader(NCSignedRequest $signedRequest): void {
		$data = $signedRequest->getSignatureHeader();
		$data->hasKeys(['keyId', 'headers', 'signature'], true);

		$signedRequest->setOrigin($this->getKeyOrigin($data->g('keyId')));
		$signedRequest->setSignedSignature($data->g('signature'));
	}


	/**
	 * @param NCSignedRequest $signedRequest
	 *
	 * @throws SignatoryException
	 * @throws SignatureException
	 */
	private function verifyIncomingRequestSignature(NCSignedRequest $signedRequest) {
		$data = $signedRequest->getSignatureHeader();

		try {
			$signedRequest->setSignatory($this->retrieveSignatory($data->g('keyId'), false));
			$this->verifySignedRequest($signedRequest);
		} catch (SignatoryException $e) {
			$signedRequest->setSignatory($this->retrieveSignatory($data->g('keyId'), true));
			$this->verifySignedRequest($signedRequest);
		}
	}


	/**
	 * @param NCSignedRequest $signedRequest
	 *
	 * @throws SignatureException
	 */
	private function verifySignedRequest(NCSignedRequest $signedRequest) {
		$publicKey = $signedRequest->getSignatory()->getPublicKey();
		if ($publicKey === '') {
			throw new SignatureException('empty public key');
		}

		try {
			$this->verifyString(
				$signedRequest->getClearSignature(),
				$signedRequest->getSignedSignature(),
				$publicKey,
				$this->getUsedEncryption($signedRequest)
			);
		} catch (SignatureException $e) {
			$this->debug('signature issue', ['signed' => $signedRequest]);
			throw $e;
		}
	}


	/**
	 * @param NCSignedRequest $signedRequest
	 */
	private function setOutgoingSignatureHeader(NCSignedRequest $signedRequest): void {
		$request = $signedRequest->getOutgoingRequest();

		$data = new SimpleDataStore();
		$data->s('(request-target)', NCRequest::method($request->getType()) . ' ' . $request->getPath())
			 ->sInt('content-length', strlen($signedRequest->getBody()))
			 ->s('date', gmdate($this->dateHeader))
			 ->s('digest', $signedRequest->getDigest())
			 ->s('host', $request->getHost());

		$signedRequest->setSignatureHeader($data);
	}


	/**
	 * @param NCSignedRequest $signedRequest
	 */
	private function setOutgoingClearSignature(NCSignedRequest $signedRequest): void {
		$signing = [];
		$data = $signedRequest->getSignatureHeader();
		foreach ($data->keys() as $element) {
			try {
				$value = $data->gItem($element);
				$signing[] = $element . ': ' . $value;
				if ($element !== '(request-target)') {
					$signedRequest->getOutgoingRequest()->addHeader($element, $value);
				}
			} catch (ItemNotFoundException $e) {
			}
		}

		$signedRequest->setClearSignature(implode("\n", $signing));
	}


	/**
	 * @param NCSignedRequest $signedRequest
	 *
	 * @throws SignatoryException
	 */
	private function setOutgoingSignedSignature(NCSignedRequest $signedRequest): void {
		$clear = $signedRequest->getClearSignature();
		$signed = $this->signString($clear, $signedRequest->getSignatory());
		$signedRequest->setSignedSignature($signed);
	}


	/**
	 * @param NCSignedRequest $signedRequest
	 *
	 * @return void
	 */
	private function signingOutgoingRequest(NCSignedRequest $signedRequest): void {
		$headers = array_diff($signedRequest->getSignatureHeader()->keys(), ['(request-target)']);
		$signatory = $signedRequest->getSignatory();
		$signatureElements = [
			'keyId="' . $signatory->getKeyId() . '"',
			'algorithm="' . $this->getChosenEncryption($signatory) . '"',
			'headers="' . implode(' ', $headers) . '"',
			'signature="' . $signedRequest->getSignedSignature() . '"'
		];

		$signedRequest->getOutgoingRequest()->addHeader('Signature', implode(',', $signatureElements));
	}


	/**
	 * @param NCSignedRequest $signedRequest
	 *
	 * @return string
	 */
	private function getUsedEncryption(NCSignedRequest $signedRequest): string {
		switch ($signedRequest->getSignatureHeader()->g('algorithm')) {
			case 'rsa-sha512':
				return NCSignatory::SHA512;

			case 'rsa-sha256':
			default:
				return NCSignatory::SHA256;
		}
	}

	/**
	 * @param NCSignatory $signatory
	 *
	 * @return string
	 */
	private function getChosenEncryption(NCSignatory $signatory): string {
		switch ($signatory->getAlgorithm()) {
			case NCSignatory::SHA512:
				return 'ras-sha512';

			case NCSignatory::SHA256:
			default:
				return 'ras-sha256';
		}
	}


	/**
	 * @param NCSignatory $signatory
	 *
	 * @return int
	 */
	public function getOpenSSLAlgo(NCSignatory $signatory): int {
		switch ($signatory->getAlgorithm()) {
			case NCSignatory::SHA512:
				return OPENSSL_ALGO_SHA512;

			case NCSignatory::SHA256:
			default:
				return OPENSSL_ALGO_SHA256;
		}
	}
}
