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


namespace OCA\Backup\Db;

use ArtificialOwl\MySmallPhpTools\Exceptions\InvalidItemException;
use ArtificialOwl\MySmallPhpTools\Exceptions\RowNotFoundException;
use ArtificialOwl\MySmallPhpTools\Traits\TArrayTools;
use OCA\Backup\Exceptions\ExternalFolderNotFoundException;
use OCA\Backup\Model\ExternalFolder;

/**
 * Class ExternalFolderRequestBuilder
 *
 * @package OCA\Backup\Db
 */
class ExternalFolderRequestBuilder extends CoreRequestBuilder {
	use TArrayTools;


	/**
	 * @return CoreQueryBuilder
	 */
	protected function getExternalFolderInsertSql(): CoreQueryBuilder {
		$qb = $this->getQueryBuilder();
		$qb->insert(self::TABLE_EXTERNAL);

		return $qb;
	}


	/**
	 * @return CoreQueryBuilder
	 */
	protected function getExternalFolderUpdateSql(): CoreQueryBuilder {
		$qb = $this->getQueryBuilder();
		$qb->update(self::TABLE_EXTERNAL);

		return $qb;
	}


	/**
	 * @return CoreQueryBuilder
	 */
	protected function getExternalFolderSelectSql(): CoreQueryBuilder {
		$qb = $this->getQueryBuilder();
		$qb->generateSelect(
			self::TABLE_EXTERNAL,
			self::$tables[self::TABLE_EXTERNAL],
			'external'
		);

		return $qb;
	}


	/**
	 * @return CoreQueryBuilder
	 */
	protected function getExternalFolderDeleteSql(): CoreQueryBuilder {
		$qb = $this->getQueryBuilder();
		$qb->delete(self::TABLE_EXTERNAL);

		return $qb;
	}


	/**
	 * @param CoreQueryBuilder $qb
	 *
	 * @return ExternalFolder
	 * @throws ExternalFolderNotFoundException
	 */
	public function getItemFromRequest(CoreQueryBuilder $qb): ExternalFolder {
		/** @var ExternalFolder $remote */
		try {
			$remote = $qb->asItem(ExternalFolder::class);
		} catch (RowNotFoundException | InvalidItemException $e) {
			throw new ExternalFolderNotFoundException();
		}

		return $remote;
	}

	/**
	 * @param CoreQueryBuilder $qb
	 *
	 * @return ExternalFolder[]
	 */
	public function getItemsFromRequest(CoreQueryBuilder $qb): array {
		/** @var ExternalFolder[] $result */
		return $qb->asItems(ExternalFolder::class);
	}
}
