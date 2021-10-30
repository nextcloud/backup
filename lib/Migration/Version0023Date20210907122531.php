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
		 * BACKUP_CHANGE
		 */
		if (!$schema->hasTable('backup_changes')) {
			$table = $schema->createTable('backup_changes');
			$table->addColumn(
				'id', 'integer', [
					'autoincrement' => true,
					'notnull' => true,
					'length' => 11,
					'unsigned' => true,
				]
			);
			$table->addColumn(
				'path', 'text', [
					'notnull' => true
				]
			);
			$table->addColumn(
				'hash', 'string', [
					'notnull' => true,
					'length' => 63
				]
			);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['hash']);
		}


		/**
		 * BACKUP_DATA
		 */
		if (!$schema->hasTable('backup_data')) {
			$table = $schema->createTable('backup_data');
			$table->addColumn(
				'id', 'integer', [
					'autoincrement' => true,
					'notnull' => true,
					'length' => 4,
					'unsigned' => true,
				]
			);
			$table->addColumn(
				'name', 'string', [
					'notnull' => true,
					'length' => 63
				]
			);
			$table->addColumn(
				'type', 'integer', [
					'notnull' => true,
					'length' => 11,
					'unsigned' => true
				]
			);
			$table->addColumn(
				'root', 'string', [
					'notnull' => true,
					'length' => 255
				]
			);
			$table->addColumn(
				'path', 'string', [
					'notnull' => true,
					'length' => 255
				]
			);

			$table->setPrimaryKey(['id']);
		}


		/**
		 * BACKUP_POINT
		 */
		if (!$schema->hasTable('backup_point')) {
			$table = $schema->createTable('backup_point');
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
					'notnull' => true,
					'length' => 63,
				]
			);
			$table->addColumn(
				'archive', 'integer', [
					'unsigned' => true,
					'notnull' => true,
					'length' => 1,
				]
			);
			$table->addColumn(
				'lock', 'integer', [
					'notnull' => true,
					'unsigned' => true,
					'length' => 11,
				]
			);
			$table->addColumn(
				'instance', 'string', [
					'notnull' => true,
					'length' => 127,
				]
			);
			$table->addColumn(
				'parent', 'string', [
					'notnull' => true,
					'length' => 63,
				]
			);
			$table->addColumn(
				'status', 'integer', [
					'notnull' => true,
					'unsigned' => true,
					'length' => 1
				]
			);
			$table->addColumn(
				'notes', 'text', [
					'notnull' => true
				]
			);
			$table->addColumn(
				'metadata', 'text', [
					'notnull' => true
				]
			);
			$table->addColumn(
				'health', 'text', [
					'notnull' => false,
					'default' => '[]'
				]
			);
			$table->addColumn(
				'date', 'integer', [
					'notnull' => true,
					'unsigned' => true,
					'length' => 11
				]
			);

			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['uid']);
			$table->addIndex(['uid']);
			$table->addIndex(['parent']);
			$table->addIndex(['instance']);
		}


		/**
		 * BACKUP_EXTERNAL
		 */
		if (!$schema->hasTable('backup_external')) {
			$table = $schema->createTable('backup_external');
			$table->addColumn(
				'id', 'integer', [
					'autoincrement' => true,
					'notnull' => true,
					'length' => 4,
					'unsigned' => true,
				]
			);
			$table->addColumn(
				'storage_id', 'integer', [
					'notnull' => false,
					'length' => 4,
				]
			);
			$table->addColumn(
				'root', 'string', [
					'notnull' => false,
					'length' => 255,
				]
			);
			$table->addColumn(
				'creation', 'datetime', [
					'notnull' => false,
				]
			);

			$table->setPrimaryKey(['id']);
			$table->addIndex(['storage_id']);
		}


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


		/**
		 * BACKUP_EVENT
		 */
		if (!$schema->hasTable('backup_event')) {
			$table = $schema->createTable('backup_event');
			$table->addColumn(
				'id', 'integer', [
					'autoincrement' => true,
					'notnull' => true,
					'length' => 4,
					'unsigned' => true,
				]
			);
			$table->addColumn(
				'author', 'string', [
					'notnull' => false,
					'length' => 127,
				]
			);
			$table->addColumn(
				'type', 'string', [
					'notnull' => false,
					'length' => 127,
				]
			);
			$table->addColumn(
				'status', 'integer', [
					'notnull' => false,
					'unsigned' => true,
					'length' => 1,
				]
			);
			$table->addColumn(
				'data', 'text', [
				]
			);
			$table->addColumn(
				'result', 'string', [
				]
			);

			$table->setPrimaryKey(['id']);
			$table->addIndex(['type']);
		}

		return $schema;
	}
}
