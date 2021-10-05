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


namespace OCA\Backup\Service;


use ArtificialOwl\MySmallPhpTools\Model\Nextcloud\nc23\NC23Request;
use ArtificialOwl\MySmallPhpTools\Traits\TArrayTools;
use OCA\Backup\AppInfo\Application;
use OCP\IConfig;


class ConfigService {


	use TArrayTools;


	const MAINTENANCE = 'maintenance';

	const SELF_SIGNED_CERT = 'self_signed_cert';
	const DELAY_FULL_RP = 'delay_full_rp';
	const DELAY_PARTIAL_RP = 'delay_partial_rp';
	const DELAY_UNIT = 'delay_unit';
	const ALLOW_WEEKDAY = 'allow_weekday';
	const DATE_FULL_RP = 'date_full_rp';
	const DATE_PARTIAL_RP = 'date_partial_rp';
	const LAST_FULL_RP = 'last_full_rp';
	const LAST_PARTIAL_RP = 'last_partial_rp';
	const PACK_BACKUP = 'pack_backup';
	const ENCRYPT_BACKUP = 'encrypt_backup';
	const ENCRYPTION_KEY = 'encryption_key';
	const TIME_SLOTS = 'time_slots';


	/** @var array */
	public $defaults = [
		self::SELF_SIGNED_CERT => '0',
		self::LAST_FULL_RP => '',
		self::LAST_PARTIAL_RP => '',
		self::DATE_FULL_RP => 0,
		self::DATE_PARTIAL_RP => 0,
		self::DELAY_FULL_RP => 14,
		self::DELAY_PARTIAL_RP => 3,
		self::DELAY_UNIT => 'd',
		self::ALLOW_WEEKDAY => 0,
		self::PACK_BACKUP => '1',
		self::ENCRYPT_BACKUP => '1',
		self::ENCRYPTION_KEY => '',
		self::TIME_SLOTS => '23-5'
	];

	/** @var IConfig */
	private $config;


	/**
	 * ConfigService constructor.
	 *
	 * @param IConfig $config
	 */
	public function __construct(IConfig $config) {
		$this->config = $config;
	}


	/**
	 * Get a value by key
	 *
	 * @param string $key
	 *
	 * @return string
	 */
	public function getAppValue(string $key): string {
		if (($value = $this->config->getAppValue(Application::APP_ID, $key)) !== '') {
			return $value;
		}

		if (($value = $this->config->getSystemValue(Application::APP_ID . '.' . $key)) !== '') {
			return $value;
		}

		return $this->get($key, $this->defaults);
	}

	/**
	 * @param string $key
	 *
	 * @return int
	 */
	public function getAppValueInt(string $key): int {
		return (int)$this->getAppValue($key);
	}

	/**
	 * @param string $key
	 *
	 * @return bool
	 */
	public function getAppValueBool(string $key): bool {
		return ($this->getAppValueInt($key) === 1);
	}


	/**
	 * Set a value by key
	 *
	 * @param string $key
	 * @param string $value
	 *
	 * @return void
	 */
	public function setAppValue(string $key, string $value): void {
		$this->config->setAppValue(Application::APP_ID, $key, $value);
	}

	/**
	 * Set a value by key
	 *
	 * @param string $key
	 * @param int $value
	 *
	 * @return void
	 */
	public function setAppValueInt(string $key, int $value): void {
		$this->config->setAppValue(Application::APP_ID, $key, $value);
	}


	/**
	 *
	 */
	public function unsetAppConfig(): void {
		$this->config->deleteAppValues(Application::APP_ID);
	}


	/**
	 * @param string $key
	 *
	 * @return string
	 */
	public function getSystemValue(string $key): string {
		return $this->config->getSystemValue($key);
	}

	/**
	 * @param string $key
	 *
	 * @return int
	 */
	public function getSystemValueInt(string $key): int {
		return $this->config->getSystemValueInt($key);
	}

	/**
	 * @param string $key
	 *
	 * @return bool
	 */
	public function getSystemValueBool(string $key): bool {
		return $this->config->getSystemValueBool($key);
	}

	/**
	 * @param string $key
	 * @param string $value
	 */
	public function setSystemValue(string $key, string $value): void {
		$this->config->setSystemValue($key, $value);
	}

	/**
	 * @param string $key
	 * @param bool $value
	 */
	public function setSystemValueBool(string $key, bool $value): void {
		$this->config->setSystemValue($key, $value);
	}


	/**
	 * @param bool $maintenance
	 */
	public function maintenanceMode(bool $maintenance = false): void {
		$this->setSystemValueBool(self::MAINTENANCE, $maintenance);
	}

	/**
	 * @param NC23Request $request
	 * @param bool $longTimeout
	 */
	public function configureRequest(NC23Request $request, bool $longTimeout = false): void {
		$request->setVerifyPeer($this->getAppValue(self::SELF_SIGNED_CERT) !== '1');
		$request->setProtocols(['https', 'http']);
		$request->setHttpErrorsAllowed(true);
		$request->setLocalAddressAllowed(true);
		$request->setFollowLocation(true);
		if ($longTimeout) {
			$request->setTimeout(1800);
		}
	}


	/**
	 * @return string
	 */
	public function getTempFileName(): string {
		$tmp = tmpfile();
		$tmpPath = stream_get_meta_data($tmp)['uri'];
		fclose($tmp);

		return $tmpPath;
	}

}
