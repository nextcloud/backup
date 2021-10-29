<?php

declare(strict_types=1);


/**
 * Nextcloud - Backup now. Restore later.
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Maxence Lange <maxence@artificial-owl.com>
 * @copyright 2021
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


namespace OCA\Backup\Db;

use Exception;
use OC;
use OC\DB\Connection;
use OC\DB\SchemaWrapper;
use OCA\Backup\Service\ConfigService;

/**
 * Class CoreRequestBuilder
 *
 * @package OCA\Backup\Db
 */
class CoreRequestBuilder {
	public const TABLE_FILE_CHANGES = 'backup_changes';
	public const TABLE_RESTORING_POINT = 'backup_point';
	public const TABLE_REMOTE = 'backup_remote';
	public const TABLE_EXTERNAL = 'backup_external';
	public const TABLE_EVENT = 'backup_event';
	public const TABLE_DATA = 'backup_data';

	public const TABLE_AUTHTOKEN = 'authtoken';


	/** @var array */
	public static $tables = [
		self::TABLE_FILE_CHANGES => [
			'id',
			'path',
			'hash'
		],
		self::TABLE_REMOTE => [
			'id',
			'uid',
			'instance',
			'href',
			'exchange',
			'item'
		],
		self::TABLE_EXTERNAL => [
			'storage_id',
			'root'
		],
		self::TABLE_RESTORING_POINT => [
			'id',
			'uid',
			'archive',
			'lock',
			'instance',
			'parent',
			'status',
			'notes',
			'metadata',
			'health',
			'date'
		],
		self::TABLE_EVENT => [
			'id',
			'author',
			'type',
			'status',
			'data',
			'result'
		],
		self::TABLE_DATA => [
			'id',
			'name',
			'type',
			'root',
			'path'
		]
	];


	/** @var ConfigService */
	protected $configService;


	/**
	 * CoreQueryBuilder constructor.
	 *
	 * @param ConfigService $configService
	 */
	public function __construct(ConfigService $configService) {
		$this->configService = $configService;
	}


	/**
	 * @return CoreQueryBuilder
	 */
	public function getQueryBuilder(): CoreQueryBuilder {
		return new CoreQueryBuilder();
	}


	/**
	 *
	 */
	public function cleanDatabase(): void {
		foreach (array_keys(self::$tables) as $table) {
			$qb = $this->getQueryBuilder();
			try {
				$qb->delete($table);
				$qb->execute();
			} catch (Exception $e) {
			}
		}
	}


	public function uninstall(): void {
		$this->uninstallAppTables();
		$this->uninstallFromMigrations();
		$this->uninstallFromJobs();
		$this->configService->unsetAppConfig();
	}

	/**
	 * this just empty all tables from the app.
	 */
	public function uninstallAppTables() {
		$dbConn = OC::$server->get(Connection::class);
		$schema = new SchemaWrapper($dbConn);

		foreach (array_keys(self::$tables) as $table) {
			if ($schema->hasTable($table)) {
				$schema->dropTable($table);
			}
		}

		$schema->performDropTableCalls();
	}


	/**
	 *
	 */
	public function uninstallFromMigrations() {
		$qb = $this->getQueryBuilder();
		$qb->delete('migrations');
		$qb->limit('app', 'backup');

		$qb->execute();
	}

	/**
	 *
	 */
	public function uninstallFromJobs(): void {
		$qb = $this->getQueryBuilder();
//		$qb->delete('jobs');
//		$qb->where($this->exprLimitToDBField($qb, 'class', 'OCA\Backup\', true, true));
//		$qb->execute();
	}


	/**
	 * @param string $table
	 */
	public function emptyTable(string $table): void {
		$qb = $this->getQueryBuilder();
		$qb->delete($table);

		$qb->execute();
	}
}
