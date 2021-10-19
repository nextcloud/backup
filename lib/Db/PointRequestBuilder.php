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
use OCA\Backup\Exceptions\RestoringPointNotFoundException;
use OCA\Backup\Model\RestoringPoint;

/**
 * Class BackupRequestBuilder
 *
 * @package OCA\Backup\Db
 */
class PointRequestBuilder extends CoreRequestBuilder {
	use TArrayTools;


	/**
	 * @return CoreQueryBuilder
	 */
	protected function getPointInsertSql(): CoreQueryBuilder {
		$qb = $this->getQueryBuilder();
		$qb->insert(self::TABLE_RESTORING_POINT);

		return $qb;
	}


	/**
	 * Base of the Sql Update request
	 *
	 * @return CoreQueryBuilder
	 */
	protected function getPointUpdateSql(): CoreQueryBuilder {
		$qb = $this->getQueryBuilder();
		$qb->update(self::TABLE_RESTORING_POINT);

		return $qb;
	}


	/**
	 * Base of the Sql Select request for Shares
	 *
	 * @return CoreQueryBuilder
	 */
	protected function getPointSelectSql(): CoreQueryBuilder {
		$qb = $this->getQueryBuilder();
		$qb->generateSelect(
			self::TABLE_RESTORING_POINT,
			self::$tables[self::TABLE_RESTORING_POINT],
			'rp'
		);

		return $qb;
	}


	/**
	 * Base of the Sql Delete request
	 *
	 * @return CoreQueryBuilder
	 */
	protected function getPointDeleteSql(): CoreQueryBuilder {
		$qb = $this->getQueryBuilder();
		$qb->delete(self::TABLE_RESTORING_POINT);

		return $qb;
	}


	/**
	 * @param CoreQueryBuilder $qb
	 *
	 * @return RestoringPoint
	 * @throws RestoringPointNotFoundException
	 */
	public function getItemFromRequest(CoreQueryBuilder $qb): RestoringPoint {
		/** @var RestoringPoint $restoringPoint */
		try {
			$restoringPoint = $qb->asItem(RestoringPoint::class);
		} catch (RowNotFoundException | InvalidItemException $e) {
			throw new RestoringPointNotFoundException();
		}

		return $restoringPoint;
	}

	/**
	 * @param CoreQueryBuilder $qb
	 *
	 * @return RestoringPoint[]
	 */
	public function getItemsFromRequest(CoreQueryBuilder $qb): array {
		/** @var RestoringPoint[] $result */
		return $qb->asItems(RestoringPoint::class);
	}
}
