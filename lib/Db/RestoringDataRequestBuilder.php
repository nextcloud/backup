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
use OCA\Backup\Exceptions\RestoringDataNotFoundException;
use OCA\Backup\Model\RestoringData;

/**
 * Class RestoringDataRequestBuilder
 *
 * @package OCA\Backup\Db
 */
class RestoringDataRequestBuilder extends CoreRequestBuilder {
	use TArrayTools;


	/**
	 * @return CoreQueryBuilder
	 */
	protected function getRestoringDataInsertSql(): CoreQueryBuilder {
		$qb = $this->getQueryBuilder();
		$qb->insert(self::TABLE_DATA);

		return $qb;
	}


	/**
	 * @return CoreQueryBuilder
	 */
	protected function getRestoringDataUpdateSql(): CoreQueryBuilder {
		$qb = $this->getQueryBuilder();
		$qb->update(self::TABLE_DATA);

		return $qb;
	}


	/**
	 * @return CoreQueryBuilder
	 */
	protected function getRestoringDataSelectSql(): CoreQueryBuilder {
		$qb = $this->getQueryBuilder();
		$qb->generateSelect(
			self::TABLE_DATA,
			self::$tables[self::TABLE_DATA],
			'data'
		);

		return $qb;
	}


	/**
	 * @return CoreQueryBuilder
	 */
	protected function getRestoringDataDeleteSql(): CoreQueryBuilder {
		$qb = $this->getQueryBuilder();
		$qb->delete(self::TABLE_DATA);

		return $qb;
	}


	/**
	 * @param CoreQueryBuilder $qb
	 *
	 * @return RestoringData
	 * @throws RestoringDataNotFoundException
	 */
	public function getItemFromRequest(CoreQueryBuilder $qb): RestoringData {
		/** @var RestoringData $data */
		try {
			$data = $qb->asItem(RestoringData::class);
		} catch (RowNotFoundException | InvalidItemException $e) {
			throw new RestoringDataNotFoundException();
		}

		return $data;
	}

	/**
	 * @param CoreQueryBuilder $qb
	 *
	 * @return RestoringData[]
	 */
	public function getItemsFromRequest(CoreQueryBuilder $qb): array {
		/** @var RestoringData[] $result */
		return $qb->asItems(RestoringData::class);
	}
}
