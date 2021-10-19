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

use ArtificialOwl\MySmallPhpTools\Db\Nextcloud\nc23\INC23QueryRow;
use ArtificialOwl\MySmallPhpTools\Traits\TArrayTools;
use JsonSerializable;

/**
 * Class ChangedFile
 *
 * @package OCA\Backup\Model
 */
class ChangedFile implements INC23QueryRow, JsonSerializable {
	use TArrayTools;


	/** @var string */
	private $path = '';

	/** @var string */
	private $hash = '';


	/**
	 * ChangedFile constructor.
	 *
	 * @param string $path
	 */
	public function __construct(string $path = '') {
		if ($path !== '') {
			$this->setPath($path);
		}
	}


	/**
	 * @param string $path
	 *
	 * @return ChangedFile
	 */
	public function setPath(string $path): self {
		$this->path = $path;
		$this->hash = md5($path);

		return $this;
	}

	/**
	 * @return string
	 */
	public function getPath(): string {
		return $this->path;
	}


	/**
	 * @param string $hash
	 *
	 * @return $this
	 */
	public function setHash(string $hash): self {
		$this->hash = $hash;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getHash(): string {
		return $this->hash;
	}


	/**
	 * @param array $data
	 *
	 * @return INC23QueryRow
	 */
	public function importFromDatabase(array $data): INC23QueryRow {
		$this->setPath($this->get('path', $data));
		$this->setHash($this->get('hash', $data));

		return $this;
	}

	/**
	 * @return array
	 */
	public function jsonSerialize(): array {
		return [
			'path' => $this->getPath(),
			'hash' => $this->getHash()
		];
	}
}
