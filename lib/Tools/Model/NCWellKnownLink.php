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


namespace OCA\Backup\Tools\Model;

use JsonSerializable;
use OCA\Backup\Tools\Traits\TArrayTools;

class NCWellKnownLink implements JsonSerializable {
	use TArrayTools;


	/** @var string */
	private $rel = '';

	/** @var string */
	private $type = '';

	/** @var string */
	private $href = '';

	/** @var array */
	private $titles = [];

	/** @var array */
	private $properties = [];


	/**
	 * NC22WellKnownLink constructor.
	 *
	 * @param array $json
	 */
	public function __construct(array $json = []) {
		$this->setRel($this->get('rel', $json));
		$this->setType($this->get('type', $json));
		$this->setHref($this->get('href', $json));
		$this->setTitles($this->getArray('titles', $json));
		$this->setProperties($this->getArray('properties', $json));
	}


	/**
	 * @return string
	 */
	public function getRel(): string {
		return $this->rel;
	}

	/**
	 * @param string $rel
	 *
	 * @return self
	 */
	public function setRel(string $rel): self {
		$this->rel = $rel;

		return $this;
	}


	/**
	 * @return string
	 */
	public function getType(): string {
		return $this->type;
	}

	/**
	 * @param string $type
	 *
	 * @return self
	 */
	public function setType(string $type): self {
		$this->type = $type;

		return $this;
	}


	/**
	 * @return string
	 */
	public function getHref(): string {
		return $this->href;
	}

	/**
	 * @param string $href
	 *
	 * @return self
	 */
	public function setHref(string $href): self {
		$this->href = $href;

		return $this;
	}


	/**
	 * @return array
	 */
	public function getTitles(): array {
		return $this->titles;
	}

	/**
	 * @param array $titles
	 *
	 * @return self
	 */
	public function setTitles(array $titles): self {
		$this->titles = $titles;

		return $this;
	}

	/**
	 * @param string $key
	 *
	 * @return string
	 */
	public function getTitle(string $key): string {
		return $this->get($key, $this->properties);
	}


	/**
	 * @return array
	 */
	public function getProperties(): array {
		return $this->properties;
	}

	/**
	 * @param array $properties
	 *
	 * @return self
	 */
	public function setProperties(array $properties): self {
		$this->properties = $properties;

		return $this;
	}

	/**
	 * @param string $key
	 *
	 * @return string
	 */
	public function getProperty(string $key): string {
		return $this->get($key, $this->properties);
	}


	/**
	 * @return array
	 */
	public function jsonSerialize(): array {
		return array_filter(
			[
				'rel' => $this->getRel(),
				'type' => $this->getType(),
				'href' => $this->getHref(),
				'titles' => $this->getTitles(),
				'properties' => $this->getProperties()
			]
		);
	}
}
