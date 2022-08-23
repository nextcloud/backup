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


namespace OCA\Backup\Tools\Traits;

use JsonSerializable;
use OCA\Backup\Tools\Exceptions\InvalidItemException;
use OCA\Backup\Tools\IDeserializable;

trait TDeserialize {


	/**
	 * @param JsonSerializable $model
	 *
	 * @return array
	 */
	public function serialize(JsonSerializable $model): array {
		return json_decode(json_encode($model), true);
	}

	/**
	 * @param array $data
	 *
	 * @return array
	 */
	public function serializeArray(array $data): array {
		return json_decode(json_encode($data), true);
	}


	/**
	 * @param array $data
	 * @param string $class
	 *
	 * @return IDeserializable
	 * @throws InvalidItemException
	 */
	public function deserialize(array $data, string $class): IDeserializable {
		if ($class instanceof IDeserializable) {
			throw new InvalidItemException(get_class($class) . ' does not implement IDeserializable');
		}

		/** @var IDeserializable $item */
		$item = new $class;
		$item->import($data);

		return $item;
	}


	/**
	 * @param array $data
	 * @param string $class
	 * @param bool $associative
	 *
	 * @return IDeserializable[]
	 */
	public function deserializeArray(array $data, string $class, bool $associative = false): array {
		$arr = [];
		foreach ($data as $key => $entry) {
			if (!is_array($entry)) {
				continue;
			}

			try {
				if ($associative) {
					$arr[$key] = $this->deserialize($entry, $class);
				} else {
					$arr[] = $this->deserialize($entry, $class);
				}
			} catch (InvalidItemException $e) {
			}
		}

		return $arr;
	}


	/**
	 * @param string $json
	 * @param string $class
	 *
	 * @return IDeserializable[]
	 * @throws InvalidItemException
	 */
	public function deserializeList(string $json, string $class): array {
		$arr = [];
		$data = json_decode($json, true);
		if (!is_array($data)) {
			return $arr;
		}

		foreach ($data as $entry) {
			$arr[] = $this->deserialize($entry, $class);
		}

		return $arr;
	}


	/**
	 * @param string $json
	 * @param string $class
	 *
	 * @return IDeserializable
	 * @throws InvalidItemException
	 */
	public function deserializeJson(string $json, string $class): IDeserializable {
		$data = json_decode($json, true);

		if (!is_array($data)) {
			throw new InvalidItemException();
		}

		return $this->deserialize($data, $class);
	}
}
