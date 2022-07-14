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
use OCA\Backup\Tools\Exceptions\InvalidItemException;
use OCA\Backup\Tools\Exceptions\ItemNotFoundException;
use OCA\Backup\Tools\Exceptions\MalformedArrayException;
use OCA\Backup\Tools\Exceptions\UnknownTypeException;
use OCA\Backup\Tools\IDeserializable;
use OCA\Backup\Tools\Traits\TArrayTools;

class SimpleDataStore implements JsonSerializable {
	use TArrayTools;


	/** @var array */
	private $data;


	/**
	 * SimpleDataStore constructor.
	 *
	 * @param array|null $data
	 */
	public function __construct(?array $data = []) {
		if (!is_array($data)) {
			$data = [];
		}

		$this->data = $data;
	}

	public function default(array $default = []): void {
		$this->data = array_merge($default, $this->data);
	}


	/**
	 * @param string $key
	 * @param string $value
	 *
	 * @return SimpleDataStore
	 */
	public function s(string $key, string $value): self {
		$this->data[$key] = $value;

		return $this;
	}

	/**
	 * @param string $key
	 *
	 * @return string
	 */
	public function g(string $key): string {
		return $this->get($key, $this->data);
	}

	/**
	 * @param string $key
	 *
	 * @return $this
	 */
	public function u(string $key): self {
		if ($this->hasKey($key)) {
			unset($this->data[$key]);
		}

		return $this;
	}

	/**
	 * @param string $key
	 * @param string $value
	 *
	 * @return SimpleDataStore
	 */
	public function a(string $key, string $value): self {
		if (!array_key_exists($key, $this->data)) {
			$this->data[$key] = [];
		}

		$this->data[$key][] = $value;

		return $this;
	}


	/**
	 * @param string $key
	 * @param int $value
	 *
	 * @return SimpleDataStore
	 */
	public function sInt(string $key, int $value): self {
		$this->data[$key] = $value;

		return $this;
	}

	/**
	 * @param string $key
	 *
	 * @return int
	 */
	public function gInt(string $key): int {
		return $this->getInt($key, $this->data);
	}

	/**
	 * @param string $key
	 * @param int $value
	 *
	 * @return SimpleDataStore
	 */
	public function aInt(string $key, int $value): self {
		if (!array_key_exists($key, $this->data)) {
			$this->data[$key] = [];
		}

		$this->data[$key][] = $value;

		return $this;
	}


	/**
	 * @param string $key
	 * @param bool $value
	 *
	 * @return SimpleDataStore
	 */
	public function sBool(string $key, bool $value): self {
		$this->data[$key] = $value;

		return $this;
	}

	/**
	 * @param string $key
	 *
	 * @return bool
	 */
	public function gBool(string $key): bool {
		return $this->getBool($key, $this->data);
	}

	/**
	 * @param string $key
	 * @param bool $value
	 *
	 * @return SimpleDataStore
	 */
	public function aBool(string $key, bool $value): self {
		if (!array_key_exists($key, $this->data)) {
			$this->data[$key] = [];
		}

		$this->data[$key][] = $value;

		return $this;
	}


	/**
	 * @param string $key
	 * @param array $values
	 *
	 * @return SimpleDataStore
	 */
	public function sArray(string $key, array $values): self {
		$this->data[$key] = $values;

		return $this;
	}

	/**
	 * @param string $key
	 *
	 * @return array
	 */
	public function gArray(string $key): array {
		return $this->getArray($key, $this->data);
	}

	/**
	 * @param string $key
	 * @param array $values
	 *
	 * @return SimpleDataStore
	 */
	public function aArray(string $key, array $values): self {
		if (!array_key_exists($key, $this->data)) {
			$this->data[$key] = [];
		}

		$this->data[$key] = array_merge($this->data[$key], $values);

		return $this;
	}


	/**
	 * @param string $key
	 * @param JsonSerializable $value
	 *
	 * @return SimpleDataStore
	 */
	public function sObj(string $key, JsonSerializable $value): self {
		$this->data[$key] = $value;

		return $this;
	}


	/**
	 * @param string $key
	 * @param string $class
	 *
	 * @return JsonSerializable[]
	 */
	public function gObjs(string $key, string $class = ''): array {
		$list = $this->gArray($key);
		$result = [];
		foreach ($list as $item) {
			$data = new SimpleDataStore([$key => $item]);
			$result[] = $data->gObj($key, $class);
		}

		return array_filter($result);
	}


	/**
	 * @param string $key
	 * @param string $class
	 *
	 * @return null|JsonSerializable
	 * @throws InvalidItemException
	 * @throws UnknownTypeException
	 * @throws ItemNotFoundException
	 */
	public function gObj(string $key, string $class = ''): ?JsonSerializable {
		$type = $this->typeOf($key, $this->data);

		if ($type === self::$TYPE_NULL) {
			if ($class === '') {
				return null;
			}

			throw new InvalidItemException();
		}

		if ($type === self::$TYPE_SERIALIZABLE) {
			return $this->getObj($key, $this->data);
		}

		if ($type === self::$TYPE_ARRAY && $class !== '') {
			$item = new $class();
			if (!$item instanceof IDeserializable && !$item instanceof JsonSerializable) {
				throw new InvalidItemException(
					$class . ' does not implement IDeserializable and JsonSerializable'
				);
			}

			$item->import($this->getArray($key, $this->data));

			return $item;
		}

		throw new InvalidItemException();
	}

	/**
	 * @param string $key
	 * @param JsonSerializable $value
	 *
	 * @return SimpleDataStore
	 */
	public function aObj(string $key, JsonSerializable $value): self {
		if (!array_key_exists($key, $this->data)) {
			$this->data[$key] = [];
		}

		$this->data[$key][] = $value;

		return $this;
	}


	/**
	 * @param string $key
	 * @param SimpleDataStore $data
	 *
	 * @return $this
	 */
	public function sData(string $key, SimpleDataStore $data): self {
		$this->data[$key] = $data->gAll();

		return $this;
	}

	/**
	 * @param string $key
	 * @param SimpleDataStore $data
	 *
	 * @return $this
	 */
	public function aData(string $key, SimpleDataStore $data): self {
		if (!array_key_exists($key, $this->data) || !is_array($this->data[$key])) {
			$this->data[$key] = [];
		}

		$this->data[$key][] = $data->gAll();

		return $this;
	}

	/**
	 * @param string $key
	 *
	 * @return SimpleDataStore
	 */
	public function gData(string $key): SimpleDataStore {
		return new SimpleDataStore($this->getArray($key, $this->data));
	}


	/**
	 * @param string $key
	 *
	 * @return mixed
	 * @throws ItemNotFoundException
	 */
	public function gItem(string $key) {
		if (!array_key_exists($key, $this->data)) {
			throw new ItemNotFoundException();
		}

		return $this->data[$key];
	}


	/**
	 * @return array
	 */
	public function gAll(): array {
		return $this->data;
	}

	/**
	 * @param array $data
	 *
	 * @return SimpleDataStore
	 */
	public function sAll(array $data): self {
		$this->data = $data;

		return $this;
	}


	public function keys(): array {
		return array_keys($this->data);
	}

	/**
	 * @param string $key
	 *
	 * @return bool
	 */
	public function hasKey(string $key): bool {
		return (array_key_exists($key, $this->data));
	}


	/**
	 * @param array $keys
	 *
	 * @param bool $must
	 *
	 * @return bool
	 * @throws MalformedArrayException
	 */
	public function hasKeys(array $keys, bool $must = false): bool {
		foreach ($keys as $key) {
			if (!$this->haveKey($key)) {
				if ($must) {
					throw new MalformedArrayException($key . ' missing in ' . json_encode($this->keys()));
				}

				return false;
			}
		}

		return true;
	}


	/**
	 * @param array $keys
	 * @param bool $must
	 *
	 * @return bool
	 * @throws MalformedArrayException
	 * @deprecated
	 */
	public function haveKeys(array $keys, bool $must = false): bool {
		return $this->hasKeys($keys, $must);
	}

	/**
	 * @param string $key
	 *
	 * @return bool
	 * @deprecated
	 */
	public function haveKey(string $key): bool {
		return $this->hasKey($key);
	}


	/**
	 * @param string $json
	 *
	 * @return $this
	 */
	public function json(string $json): self {
		$data = json_decode($json, true);
		if (is_array($data)) {
			$this->data = $data;
		}

		return $this;
	}

	/**
	 * @param JsonSerializable $obj
	 *
	 * @return $this
	 */
	public function obj(JsonSerializable $obj): self {
		$data = $obj->jsonSerialize();
		if (is_array($data)) {
			$this->data = $data;
		}

		return $this;
	}

	/**
	 * @return array
	 */
	public function jsonSerialize(): array {
		return $this->data;
	}
}
