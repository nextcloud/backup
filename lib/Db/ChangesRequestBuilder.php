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
use OCA\Backup\Exceptions\ChangedFileNotFoundException;
use OCA\Backup\Model\ChangedFile;

/**
 * Class ChangesRequestBuilder
 *
 * @package OCA\Backup\Db
 */
class ChangesRequestBuilder extends CoreRequestBuilder {
	use TArrayTools;


	/**
	 * @return CoreQueryBuilder
	 */
	protected function getChangesInsertSql(): CoreQueryBuilder {
		$qb = $this->getQueryBuilder();
		$qb->insert(self::TABLE_FILE_CHANGES);

		return $qb;
	}


	/**
	 * @return CoreQueryBuilder
	 */
	protected function getChangesUpdateSql(): CoreQueryBuilder {
		$qb = $this->getQueryBuilder();
		$qb->update(self::TABLE_FILE_CHANGES);

		return $qb;
	}


	/**
	 * @return CoreQueryBuilder
	 */
	protected function getChangesSelectSql(): CoreQueryBuilder {
		$qb = $this->getQueryBuilder();
		$qb->generateSelect(
			self::TABLE_FILE_CHANGES,
			self::$tables[self::TABLE_FILE_CHANGES],
			'changes'
		);

		return $qb;
	}


	/**
	 * @return CoreQueryBuilder
	 */
	protected function getChangesDeleteSql(): CoreQueryBuilder {
		$qb = $this->getQueryBuilder();
		$qb->delete(self::TABLE_FILE_CHANGES);

		return $qb;
	}


	/**
	 * @param CoreQueryBuilder $qb
	 *
	 * @return ChangedFile
	 * @throws ChangedFileNotFoundException
	 */
	public function getItemFromRequest(CoreQueryBuilder $qb): ChangedFile {
		/** @var ChangedFile $changed */
		try {
			$changed = $qb->asItem(ChangedFile::class);
		} catch (RowNotFoundException | InvalidItemException $e) {
			throw new ChangedFileNotFoundException();
		}

		return $changed;
	}

	/**
	 * @param CoreQueryBuilder $qb
	 *
	 * @return ChangedFile[]
	 */
	public function getItemsFromRequest(CoreQueryBuilder $qb): array {
		/** @var ChangedFile[] $result */
		return $qb->asItems(ChangedFile::class);
	}
}
