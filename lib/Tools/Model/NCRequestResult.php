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

use GuzzleHttp\Exception\BadResponseException;
use JsonSerializable;
use OCA\Backup\Tools\Exceptions\RequestContentException;
use OCA\Backup\Tools\Traits\TArrayTools;
use OCP\Http\Client\IResponse;

class NCRequestResult implements JsonSerializable {
	use TArrayTools;


	public const TYPE_STRING = 0;
	public const TYPE_BINARY = 1;
	public const TYPE_JSON = 2;
	public const TYPE_XRD = 3;


	/** @var int */
	private $statusCode = 0;

	/** @var array */
	private $headers = [];

	/** @var mixed */
	private $content;

	/** @var array */
	private $contentAsArray = [];

	/** @var int */
	private $contentType = 0;

	/** @var BadResponseException */
	private $exception = null;


	/**
	 * NCRequestResult constructor.
	 *
	 * @param IResponse|null $response
	 * @param BadResponseException|null $e
	 */
	public function __construct(?IResponse $response, ?BadResponseException $e = null) {
		if (!is_null($response)) {
			$this->setStatusCode($response->getStatusCode());
			$this->setContent($response->getBody());
			$this->setHeaders($response->getHeaders());
		}

		if (!is_null($e)) {
			$this->setException($e);
		}

		$this->generateMeta();
	}


	/**
	 * @return int
	 */
	public function getStatusCode(): int {
		return $this->statusCode;
	}

	/**
	 * @param int $statusCode
	 *
	 * @return self
	 */
	public function setStatusCode(int $statusCode): self {
		$this->statusCode = $statusCode;

		return $this;
	}


	/**
	 * @return array
	 */
	public function getHeaders(): array {
		return $this->headers;
	}

	/**
	 * @param array $headers
	 *
	 * @return self
	 */
	public function setHeaders(array $headers): self {
		$this->headers = $headers;

		return $this;
	}

	/**
	 * @param string $key
	 *
	 * @return array
	 */
	public function getHeader(string $key): array {
		return $this->getArray($key, $this->headers);
	}

	public function withinHeader(string $key, string $needle): bool {
		foreach ($this->getHeader($key) as $header) {
			if (strpos($header, $needle) !== false) {
				return true;
			}
		}

		return false;
	}


	/**
	 * @param string $content
	 *
	 * @return self
	 */
	public function setContent(string $content): self {
		$this->content = $content;

		return $this;
	}


	/**
	 * @return string
	 * @throws RequestContentException
	 */
	public function getContent(): string {
		if (is_null($this->content) || !is_string($this->content)) {
			throw new RequestContentException();
		}

		return $this->content;
	}

	/**
	 * @return array
	 */
	public function getAsArray(): array {
		if (empty($this->contentAsArray)) {
			$this->generateContentAsArray();
		}

		return $this->contentAsArray;
	}


	/**
	 * @return string
	 */
	public function getBinary() {
		return $this->content;
	}


	/**
	 * @return int
	 */
	public function getContentType(): int {
		return $this->contentType;
	}

	/**
	 * @param int $type
	 *
	 * @return $this
	 */
	public function setContentType(int $type): self {
		$this->contentType = $type;

		return $this;
	}

	/**
	 * @param int $type
	 *
	 * @return bool
	 */
	public function isContentType(int $type): bool {
		return ($this->contentType === $type);
	}


	/**
	 *
	 */
	private function generateMeta(): void {
		$this->setContentType($this->discoverContentType());
		$this->generateContentAsArray();
	}

	/**
	 * @return int
	 */
	private function discoverContentType(): int {
		if ($this->withinHeader('Content-Type', 'application/xrd')) {
			return self::TYPE_XRD;
		}

		if ($this->withinHeader('Content-Type', 'application/json')
			|| $this->withinHeader('Content-Type', 'application/jrd')
		) {
			return self::TYPE_JSON;
		}

		try {
			$content = $this->getContent();
		} catch (RequestContentException $e) {
			return self::TYPE_BINARY;
		}

		// in case header failure
		$arr = json_decode($content, true);
		if (is_array($arr)) {
			return self::TYPE_JSON;
		}

		return self::TYPE_STRING;
	}

	/**
	 *
	 */
	private function generateContentAsArray(): void {
		try {
			$content = $this->getContent();
			if ($this->isContentType(self::TYPE_XRD)) {
				$xml = simplexml_load_string($content);
				$content = json_encode($xml, JSON_UNESCAPED_SLASHES);
			}

			$arr = json_decode($content, true);
			if (is_array($arr)) {
				$this->contentAsArray = $arr;
			}
		} catch (RequestContentException $e) {
		}
	}


	/**
	 * @param BadResponseException $e
	 *
	 * @return self
	 */
	public function setException(BadResponseException $e): self {
		$this->exception = $e;
		$this->setStatusCode($e->getResponse()->getStatusCode());

		return $this;
	}

	/**
	 * @return BadResponseException
	 */
	public function getException(): BadResponseException {
		return $this->exception;
	}

	/**
	 * @return bool
	 */
	public function hasException(): bool {
		return (!is_null($this->exception));
	}


	/**
	 * @return array
	 */
	public function jsonSerialize(): array {
		try {
			$content = $this->getContent();
		} catch (RequestContentException $e) {
			$content = 'not a string';
		}

		return [
			'statusCode' => $this->getStatusCode(),
			'headers' => $this->getHeaders(),
			'content' => $content,
			'contentAsArray' => $this->contentAsArray,
			'contentType' => $this->getContentType()
		];
	}
}
