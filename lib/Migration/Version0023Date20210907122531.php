<?php

declare(strict_types=1);


/**
 * Nextcloud - Backup
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


namespace OCA\Backup\Migration;

use Closure;
use Doctrine\DBAL\Schema\SchemaException;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Class Version0023Date20210907122531
 *
 * @package OCA\Backup\Migration
 */
class Version0023Date20210907122531 extends SimpleMigrationStep {


	/**
	 * @param IDBConnection $connection
	 */
	public function __construct(IDBConnection $connection) {
	}


	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 *
	 * @return null|ISchemaWrapper
	 * @throws SchemaException
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		/**
		 * BACKUP_REMOTE
		 */
		if (!$schema->hasTable('backup_remote')) {
			$table = $schema->createTable('backup_remote');
			$table->addColumn(
				'id', 'integer', [
						'autoincrement' => true,
						'notnull' => true,
						'length' => 4,
						'unsigned' => true,
					]
			);
			$table->addColumn(
				'uid', 'string', [
						 'notnull' => false,
						 'length' => 20,
					 ]
			);
			$table->addColumn(
				'instance', 'string', [
							  'notnull' => false,
							  'length' => 127,
						  ]
			);
			$table->addColumn(
				'exchange', 'integer', [
							  'notnull' => true,
							  'length' => 1,
							  'unsigned' => true,
						  ]
			);
			$table->addColumn(
				'href', 'string', [
						  'notnull' => false,
						  'length' => 254,
					  ]
			);
			$table->addColumn(
				'item', 'text', [
						  'notnull' => false,
					  ]
			);
			$table->addColumn(
				'creation', 'datetime', [
							  'notnull' => false,
						  ]
			);

			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['instance']);
			$table->addIndex(['uid']);
			$table->addIndex(['href']);
		}

		return $schema;
	}
}
