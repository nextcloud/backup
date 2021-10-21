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


namespace OCA\Backup\Model;

use ArtificialOwl\MySmallPhpTools\IDeserializable;
use ArtificialOwl\MySmallPhpTools\Traits\TArrayTools;
use ArtificialOwl\MySmallPhpTools\Traits\TStringTools;
use JsonSerializable;

/**
 * Class RestoringChunkPart
 *
 * @package OCA\Backup\Model
 */
class RestoringChunkPart implements JsonSerializable, IDeserializable {
	use TArrayTools;
	use TStringTools;


	/** @var string */
	private $name;

	/** @var int */
	private $order;

	/** @var string */
	private $content = '';

	/** @var string */
	private $checksum = '';

	/** @var bool */
	private $encrypted = false;

	/** @var string */
	private $algorithm = '';

	/** @var string */
	private $encryptedChecksum = '';


	/**
	 * RestoringChunkPart constructor.
	 *
	 * @param string $name
	 * @param int $order
	 */
	public function __construct(string $name = '', int $order = 0) {
		$this->name = $name;
		$this->order = $order;
	}


	/**
	 * @param string $ext
	 *
	 * @return string
	 */
	public function getName(string $ext = ''): string {
		if ($ext === '') {
			return $this->name;
		}

		return $this->name . '.' . $ext;
	}

	/**
	 * @param string $name
	 *
	 * @return RestoringChunkPart
	 */
	public function setName(string $name): self {
		$this->name = $name;

		return $this;
	}


	/**
	 * @param int $order
	 *
	 * @return RestoringChunkPart
	 */
	public function setOrder(int $order): self {
		$this->order = $order;

		return $this;
	}

	/**
	 * @return int
	 */
	public function getOrder(): int {
		return $this->order;
	}


	/**
	 * @param bool $encrypted
	 *
	 * @return RestoringChunkPart
	 */
	public function setEncrypted(bool $encrypted, string $algorithm = ''): self {
		$this->encrypted = $encrypted;
		$this->algorithm = $algorithm;

		return $this;
	}

	/**
	 * @return bool
	 */
	public function isEncrypted(): bool {
		return $this->encrypted;
	}


	/**
	 * @param string $algorithm
	 *
	 * @return RestoringChunkPart
	 */
	public function setAlgorithm(string $algorithm): self {
		$this->algorithm = $algorithm;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getAlgorithm(): string {
		return $this->algorithm;
	}


	/**
	 * @return string
	 */
	public function getChecksum(): string {
		return $this->checksum;
	}

	/**
	 * @param string $checksum
	 *
	 * @return RestoringChunkPart
	 */
	public function setChecksum(string $checksum): self {
		$this->checksum = $checksum;

		return $this;
	}


	/**
	 * @return string
	 */
	public function getEncryptedChecksum(): string {
		return $this->encryptedChecksum;
	}

	/**
	 * @param string $encryptedChecksum
	 *
	 * @return RestoringChunkPart
	 */
	public function setEncryptedChecksum(string $encryptedChecksum): self {
		$this->encryptedChecksum = $encryptedChecksum;

		return $this;
	}


	/**
	 * @return string
	 */
	public function getCurrentChecksum(): string {
		if ($this->isEncrypted()) {
			return $this->getEncryptedChecksum();
		}

		return $this->getChecksum();
	}


	/**
	 * @param string $content
	 *
	 * @return RestoringChunkPart
	 */
	public function setContent(string $content): self {
		$this->content = $content;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getContent(): string {
		return $this->content;
	}


	/**
	 * @param array $data
	 *
	 * @return RestoringChunk
	 */
	public function import(array $data): IDeserializable {
		$this->setName($this->get('name', $data))
			 ->setOrder($this->getInt('order', $data))
			 ->setContent($this->get('content', $data))
			 ->setEncrypted($this->getBool('encrypted', $data))
			 ->setAlgorithm($this->get('algorithm', $data))
			 ->setChecksum($this->get('checksum', $data))
			 ->setEncryptedChecksum($this->get('encryptedChecksum', $data));

		return $this;
	}


	/**
	 * @return array
	 */
	public function jsonSerialize(): array {
		$arr = [
			'name' => $this->getName(),
			'order' => $this->getOrder(),
			'encrypted' => $this->isEncrypted(),
			'algorithm' => $this->getAlgorithm(),
			'checksum' => $this->getChecksum(),
			'encryptedChecksum' => $this->getEncryptedChecksum()
		];

		if ($this->getContent() !== '') {
			$arr['content'] = $this->getContent();
		}

		return $arr;
	}
}
