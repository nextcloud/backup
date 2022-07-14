<?php

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


namespace OCA\Backup\Tools\Model;

use JsonSerializable;
use OCP\IRequest;

class NCSignedRequest implements JsonSerializable {


	/** @var string */
	private $body = '';

	/** @var int */
	private $time = 0;

	/** @var IRequest */
	private $incomingRequest;

	/** @var NCRequest */
	private $outgoingRequest;

	/** @var string */
	private $origin = '';

	/** @var string */
	private $digest = '';

	/** @var SimpleDataStore */
	private $signatureHeader;

	/** @var string */
	private $host = '';

	/** @var string */
	private $clearSignature = '';

	/** @var string */
	private $signedSignature = '';

	/** @var NCSignatory */
	private $signatory;


	public function __construct(string $body = '') {
		$this->setBody($body);
	}


	/**
	 * IRequest of the incoming request
	 * incoming
	 *
	 * @return IRequest
	 */
	public function getIncomingRequest(): IRequest {
		return $this->incomingRequest;
	}

	/**
	 * @param IRequest $request
	 *
	 * @return NCSignedRequest
	 */
	public function setIncomingRequest(IRequest $request): self {
		$this->incomingRequest = $request;

		return $this;
	}


	/**
	 * NCRequest of the outgoing request
	 * outgoing
	 *
	 * @param NCRequest $request
	 *
	 * @return NCSignedRequest
	 */
	public function setOutgoingRequest(NCRequest $request): self {
		$this->outgoingRequest = $request;

		return $this;
	}

	/**
	 * @return NCRequest
	 */
	public function getOutgoingRequest(): NCRequest {
		return $this->outgoingRequest;
	}


	/**
	 * Body content of the request
	 * incoming/outgoing
	 *
	 * @return string
	 */
	public function getBody(): string {
		return $this->body;
	}

	/**
	 * @param string $body
	 *
	 * @return self
	 */
	public function setBody(string $body): self {
		$this->body = $body;
		$this->setDigest('SHA-256=' . base64_encode(hash("sha256", utf8_encode($body), true)));

		return $this;
	}


	/**
	 * Timestamp of the request
	 * incoming (outgoing ?)
	 *
	 * @return int
	 */
	public function getTime(): int {
		return $this->time;
	}

	/**
	 * @param int $time
	 *
	 * @return self
	 */
	public function setTime(int $time): self {
		$this->time = $time;

		return $this;
	}


	/**
	 * Origin of the request, based on the keyId
	 * incoming
	 *
	 * @return string
	 */
	public function getOrigin(): string {
		return $this->origin;
	}

	/**
	 * @param string $origin
	 *
	 * @return self
	 */
	public function setOrigin(string $origin): self {
		$this->origin = $origin;

		return $this;
	}


	/**
	 * @return string
	 */
	public function getDigest(): string {
		return $this->digest;
	}

	/**
	 * @param string $digest
	 *
	 * @return $this
	 */
	public function setDigest(string $digest): self {
		$this->digest = $digest;

		return $this;
	}


	/**
	 * Data from the 'Signature' header
	 * incoming/outgoing
	 *
	 * @return SimpleDataStore
	 */
	public function getSignatureHeader(): SimpleDataStore {
		return $this->signatureHeader;
	}

	/**
	 * @param SimpleDataStore $signatureHeader
	 *
	 * @return self
	 */
	public function setSignatureHeader(SimpleDataStore $signatureHeader): self {
		$this->signatureHeader = $signatureHeader;

		return $this;
	}


	/**
	 * _Clear_ value of the Signature.
	 * incoming/outgoing
	 *
	 * - estimated signature on incoming request
	 * - generated signature on outgoing request
	 *
	 * @param string $clearSignature
	 *
	 * @return NCSignedRequest
	 */
	public function setClearSignature(string $clearSignature): self {
		$this->clearSignature = $clearSignature;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getClearSignature(): string {
		return $this->clearSignature;
	}


	/**
	 * _Signed_ value of the signature.
	 * /!\ base64_encoded, not RAW /!\
	 *
	 * incoming/outgoing
	 *
	 * @param string $signedSignature
	 *
	 * @return self
	 */
	public function setSignedSignature(string $signedSignature): self {
		$this->signedSignature = $signedSignature;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getSignedSignature(): string {
		return $this->signedSignature;
	}


	/**
	 * Host/Address to be used in the signature.
	 * incoming/outgoing
	 *
	 * - incoming should set the local address
	 * - outgoing should set the recipient address
	 *
	 * @param string $host
	 *
	 * @return NCSignedRequest
	 */
	public function setHost(string $host): self {
		$this->host = $host;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getHost(): string {
		return $this->host;
	}


	/**
	 * Signatory used to sign the request
	 * incoming/outgoing
	 *
	 * @param NCSignatory $signatory
	 */
	public function setSignatory(NCSignatory $signatory): void {
		$this->signatory = $signatory;
	}

	/**
	 * @return NCSignatory
	 */
	public function getSignatory(): NCSignatory {
		return $this->signatory;
	}

	/**
	 * @return bool
	 */
	public function hasSignatory(): bool {
		return ($this->signatory !== null);
	}


	/**
	 * @return array
	 */
	public function jsonSerialize(): array {
		return [
			'body' => $this->getBody(),
			'time' => $this->getTime(),
			'incomingRequest' => ($this->incomingRequest !== null),
			'outgoingRequest' => $this->outgoingRequest !== null ? $this->getOutgoingRequest() : false,
			'origin' => $this->getOrigin(),
			'digest' => $this->getDigest(),
			'signatureHeader' => ($this->signatureHeader !== null) ? $this->getSignatureHeader() : false,
			'host' => $this->getHost(),
			'clearSignature' => $this->getClearSignature(),
			'signedSignature' => base64_encode($this->getSignedSignature()),
			'signatory' => ($this->hasSignatory()) ? $this->getSignatory() : false,
		];
	}
}
