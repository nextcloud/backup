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

use OC;
use OCP\IConfig;

trait TNCSetup {
	use TArrayTools;


	/** @var array */
	private $_setup = [];


	/**
	 * @param string $key
	 * @param string $value
	 *
	 * @param string $default
	 *
	 * @return string
	 */
	public function setup(string $key, string $value = '', string $default = ''): string {
		if ($value !== '') {
			$this->_setup[$key] = $value;
		}

		return $this->get($key, $this->_setup, $default);
	}

	/**
	 * @param string $key
	 * @param array $value
	 * @param array $default
	 *
	 * @return array
	 */
	public function setupArray(string $key, array $value = [], array $default = []): array {
		if (!empty($value)) {
			$this->_setup[$key] = $value;
		}

		return $this->getArray($key, $this->_setup, $default);
	}

	/**
	 * @param string $key
	 * @param int $value
	 * @param int $default
	 *
	 * @return int
	 */
	public function setupInt(string $key, int $value = -999, int $default = 0): int {
		if ($value !== -999) {
			$this->_setup[$key] = $value;
		}

		return $this->getInt($key, $this->_setup, $default);
	}

	/**
	 * @param string $key
	 *
	 * @return string
	 */
	public function appConfig(string $key): string {
		$app = $this->setup('app');
		if ($app === '') {
			return '';
		}

		/** @var IConfig $config */
		$config = OC::$server->get(IConfig::class);

		return $config->getAppValue($app, $key, '');
	}
}
