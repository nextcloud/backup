<?php
declare(strict_types=1);


/**
 * Nextcloud - Backup
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Maxence Lange <maxence@artificial-owl.com>
 * @copyright 2019, Maxence Lange <maxence@artificial-owl.com>
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


use daita\MySmallPhpTools\Db\RequestBuilder;
use OCA\Backup\Service\MiscService;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;


/**
 * Class CoreRequestBuilder
 *
 * @package OCA\Backup\Db
 */
class CoreRequestBuilder extends RequestBuilder {


	const SQL_TABLES = [
		'BACKUPS' => 'backups'
	];


	/** @var IDBConnection */
	protected $dbConnection;

	/** @var MiscService */
	protected $miscService;


	/** @var string */
	protected $defaultSelectAlias;


	/**
	 * CoreRequestBuilder constructor.
	 *
	 * @param IDBConnection $connection
	 * @param MiscService $miscService
	 */
	public function __construct(IDBConnection $connection, MiscService $miscService) {
		$this->dbConnection = $connection;
		$this->miscService = $miscService;
	}


	/**
	 * Limit the request to the Id
	 *
	 * @param IQueryBuilder $qb
	 * @param int $id
	 */
	protected function limitToId(IQueryBuilder &$qb, int $id) {
		$this->limitToDBFieldInt($qb, 'id', $id);
	}


	/**
	 * Limit the request to the status
	 *
	 * @param IQueryBuilder $qb
	 * @param int $status
	 */
	protected function limitToStatus(IQueryBuilder &$qb, int $status) {
		$this->limitToDBFieldInt($qb, 'status', $status);
	}


	/**
	 * Limit the request to the instance
	 *
	 * @param IQueryBuilder $qb
	 * @param bool $local
	 */
	protected function limitToLocal(IQueryBuilder &$qb, bool $local) {
		$this->limitToDBField($qb, 'local', ($local) ? '1' : '0');
	}

}

