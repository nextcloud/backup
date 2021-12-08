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
use ArtificialOwl\MySmallPhpTools\Model\SimpleDataStore;
use ArtificialOwl\MySmallPhpTools\Traits\TArrayTools;
use Exception;
use OC;
use OCA\Backup\AppInfo\Application;
use OCA\Files_External\Service\GlobalStoragesService;
use OCP\IConfig;

class ConfigService {
	use TArrayTools;


	public const MAINTENANCE = 'maintenance';
	public const DATA_DIRECTORY = 'datadirectory';
	public const LOGFILE = 'logfile';

	public const CRON_ENABLED = 'cron_enabled';
	public const LOCK = 'lock';
	public const CRON_LOCK = 'cron_lock';
	public const REMOTE_ENABLED = 'remote_enabled';
	public const EXTERNAL_APPDATA = 'external_appdata';
	public const SELF_SIGNED_CERT = 'self_signed_cert';
	public const DELAY_FULL_RP = 'delay_full_rp';
	public const DELAY_PARTIAL_RP = 'delay_partial_rp';
	public const DELAY_UNIT = 'delay_unit';
	public const ALLOW_WEEKDAY = 'allow_weekday';
	public const DATE_FULL_RP = 'date_full_rp';
	public const DATE_PARTIAL_RP = 'date_partial_rp';
	public const LAST_FULL_RP = 'last_full_rp';
	public const LAST_PARTIAL_RP = 'last_partial_rp';
	public const PACK_BACKUP = 'pack_backup';
	public const BACKUP_DAYS = 'backup_days';
	public const GENERATE_LOGS = 'generate_logs';

	public const INCLUDE_LOGS = 'include_logs';
	public const PACK_ENCRYPT = 'pack_encrypt';
	public const PACK_COMPRESS = 'pack_compress';
	public const PACK_INDEX = 'pack_index';
	public const PACK_REMOTE_INDEX = 'pack_remote_index';

	public const STORE_ITEMS = 'store_items';
	public const STORE_ITEMS_EXTERNAL = 'store_items_external';
	public const ENCRYPTION_KEYS = 'encryption_keys';
	public const FORCE_CBC = 'force_cbc';
	public const TIME_SLOTS = 'time_slots';
	public const MOCKUP_DATE = 'mockup_date';

	public const CHUNK_SIZE = 'chunk_size';
	public const CHUNK_PART_SIZE = 'chunk_part_size';


	/** @var array */
	public $defaults = [
		self::CRON_ENABLED => 1,
		self::LOCK => 0,
		self::CRON_LOCK => 0,
		self::REMOTE_ENABLED => 0,
		self::EXTERNAL_APPDATA => '{}',
		self::SELF_SIGNED_CERT => '0',
		self::LAST_FULL_RP => '',
		self::LAST_PARTIAL_RP => '',
		self::DATE_FULL_RP => 0,
		self::DATE_PARTIAL_RP => 0,
		self::DELAY_FULL_RP => 24,
		self::DELAY_PARTIAL_RP => 3,
		self::DELAY_UNIT => 'd',
		self::ALLOW_WEEKDAY => 0,
		self::PACK_BACKUP => '1',
		self::STORE_ITEMS => 3,
		self::STORE_ITEMS_EXTERNAL => 5,
		self::ENCRYPTION_KEYS => '{}',
		self::FORCE_CBC => 0,
		self::TIME_SLOTS => '23-5',
		self::MOCKUP_DATE => 0,
		self::BACKUP_DAYS => 60,
		self::GENERATE_LOGS => 0,

		self::INCLUDE_LOGS => '1',
		self::PACK_ENCRYPT => '1',
		self::PACK_COMPRESS => '1',
		self::PACK_INDEX => '1',
		self::PACK_REMOTE_INDEX => '0',
		self::CHUNK_SIZE => 4000,
		self::CHUNK_PART_SIZE => 100
	];


	/** @var IConfig */
	private $config;

	/** @var int */
	private $externalEnabled = -1;


	/**
	 * ConfigService constructor.
	 *
	 * @param IConfig $config
	 */
	public function __construct(IConfig $config) {
		$this->config = $config;
	}


	/**
	 * @param string $key
	 * @param string $default
	 *
	 * @return string
	 */
	public function getCoreValue(string $key, string $default): string {
		return $this->config->getAppValue('core', $key, $default);
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
		if (($value = $this->config->getAppValue(Application::APP_ID, $key, '')) !== '') {
			return (int)$value;
		}

		return $this->getInt($key, $this->defaults);
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
	 * @param string $key
	 *
	 * @return array
	 */
	public function getAppValueArray(string $key): array {
		return json_decode($this->getAppValue($key), true);
	}


	/**
	 * @param string $key
	 */
	public function unsetAppValue(string $key): void {
		$this->config->deleteAppValue(Application::APP_ID, $key);
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
	 * @param string $key
	 * @param int $value
	 *
	 * @return void
	 */
	public function setAppValueInt(string $key, int $value): void {
		$this->config->setAppValue(Application::APP_ID, $key, $value);
	}

	/**
	 * @param string $key
	 * @param bool $value
	 */
	public function setAppValueBool(string $key, bool $value): void {
		$this->config->setAppValue(Application::APP_ID, $key, ($value) ? 1 : 0);
	}

	/**
	 * @param string $key
	 * @param array $value
	 */
	public function setAppValueArray(string $key, array $value): void {
		$this->config->setAppValue(Application::APP_ID, $key, json_encode($value));
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
	 * @return array
	 */
	public function getSystemValueArray(string $key): array {
		$result = $this->config->getSystemValue($key);
		if (!is_array($result)) {
			return [];
		}

		return $result;
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


	/**
	 * @return bool
	 */
	public function isRemoteEnabled(): bool {
		return $this->getAppValueBool(self::REMOTE_ENABLED);
	}


	/**
	 * @return bool
	 */
	public function isExternalEnabled(): bool {
		if ($this->externalEnabled === -1) {
			try {
				OC::$server->get(GlobalStoragesService::class);
				$this->externalEnabled = 0;
			} catch (Exception $e) {
				$this->externalEnabled = 1;
			}
		}

		return ($this->externalEnabled === 1);
	}


	/**
	 * @return array
	 */
	public function getSettings(): array {
		return [
			self::CRON_ENABLED => $this->getAppValueBool(self::CRON_ENABLED),
			self::DATE_FULL_RP => $this->getAppValueInt(self::DATE_FULL_RP),
			self::DATE_PARTIAL_RP => $this->getAppValueInt(self::DATE_PARTIAL_RP),
			self::TIME_SLOTS => $this->getAppValue(self::TIME_SLOTS),
			self::DELAY_FULL_RP => $this->getAppValueInt(self::DELAY_FULL_RP),
			self::DELAY_PARTIAL_RP => $this->getAppValueInt(self::DELAY_PARTIAL_RP),
			self::ALLOW_WEEKDAY => $this->getAppValueBool(self::ALLOW_WEEKDAY),
			self::PACK_BACKUP => $this->getAppValueBool(self::PACK_BACKUP),
			self::PACK_COMPRESS => $this->getAppValueBool(self::PACK_COMPRESS),
			self::PACK_ENCRYPT => $this->getAppValueBool(self::PACK_ENCRYPT),
			self::STORE_ITEMS => $this->getAppValueInt(self::STORE_ITEMS),
			self::STORE_ITEMS_EXTERNAL => $this->getAppValueInt(self::STORE_ITEMS_EXTERNAL),
			self::MOCKUP_DATE => $this->getAppValueInt(self::MOCKUP_DATE)
		];
	}


	/**
	 * @param array $settings
	 *
	 * @return array
	 */
	public function setSettings(array $settings): array {
		$data = new SimpleDataStore($settings);

		if ($data->hasKey(self::CRON_ENABLED)) {
			$this->setAppValueBool(self::CRON_ENABLED, $data->gBool(self::CRON_ENABLED));
		}
		if ($data->hasKey(self::TIME_SLOTS)) {
			$this->setAppValue(self::TIME_SLOTS, $data->g(self::TIME_SLOTS));
		}
		if ($data->hasKey(self::DELAY_FULL_RP)) {
			$this->setAppValueInt(self::DELAY_FULL_RP, $data->gInt(self::DELAY_FULL_RP));
		}
		if ($data->hasKey(self::DELAY_PARTIAL_RP)) {
			$this->setAppValueInt(self::DELAY_PARTIAL_RP, $data->gInt(self::DELAY_PARTIAL_RP));
		}
		if ($data->hasKey(self::ALLOW_WEEKDAY)) {
			$this->setAppValueBool(self::ALLOW_WEEKDAY, $data->gBool(self::ALLOW_WEEKDAY));
		}
		if ($data->hasKey(self::PACK_BACKUP)) {
			$this->setAppValueBool(self::PACK_BACKUP, $data->gBool(self::PACK_BACKUP));
		}
		if ($data->hasKey(self::PACK_COMPRESS)) {
			$this->setAppValueBool(self::PACK_COMPRESS, $data->gBool(self::PACK_COMPRESS));
		}
		if ($data->hasKey(self::PACK_ENCRYPT)) {
			$this->setAppValueBool(self::PACK_ENCRYPT, $data->gBool(self::PACK_ENCRYPT));
		}
		if ($data->hasKey(self::STORE_ITEMS)) {
			$this->setAppValueInt(self::STORE_ITEMS, $data->gInt(self::STORE_ITEMS));
		}

		return $this->getSettings();
	}
}
