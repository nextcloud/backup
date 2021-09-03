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


use daita\MySmallPhpTools\Traits\TArrayTools;
use OCA\Backup\Model\Backup;
use OCP\DB\QueryBuilder\IQueryBuilder;

/**
 * Class BackupRequestBuilder
 *
 * @package OCA\Backup\Db
 */
class BackupsRequestBuilder extends CoreRequestBuilder {


	use TArrayTools;


	/**
	 * Base of the Sql Insert request
	 *
	 * @return IQueryBuilder
	 */
	protected function getBackupsInsertSql(): IQueryBuilder {
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->insert(self::SQL_TABLES['BACKUPS']);

		return $qb;
	}


	/**
	 * Base of the Sql Update request
	 *
	 * @return IQueryBuilder
	 */
	protected function getBackupsUpdateSql(): IQueryBuilder {
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->update(self::SQL_TABLES['BACKUPS']);

		return $qb;
	}


	/**
	 * Base of the Sql Select request for Shares
	 *
	 * @return IQueryBuilder
	 */
	protected function getBackupsSelectSql(): IQueryBuilder {
		$qb = $this->dbConnection->getQueryBuilder();

		/** @noinspection PhpMethodParametersCountMismatchInspection */
		$qb->select(
			'sa.id', 'sa.user_id', 'sa.preferred_username', 'sa.name', 'sa.summary',
			'sa.public_key', 'sa.avatar_version', 'sa.private_key', 'sa.creation'
		)
		   ->from(self::SQL_TABLES['BACKUPS'], 'sa');

		$this->defaultSelectAlias = 'sa';

		return $qb;
	}


	/**
	 * Base of the Sql Delete request
	 *
	 * @return IQueryBuilder
	 */
	protected function getBackupsDeleteSql(): IQueryBuilder {
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->delete(self::SQL_TABLES['BACKUPS']);

		return $qb;
	}


	/**
	 * @param array $data
	 *
	 * @return Backup
	 */
	protected function parseBackupsSelectSql($data): Backup {
		$backup = new Backup();
		$backup->importFromDatabase($data);

		return $backup;
	}

}

