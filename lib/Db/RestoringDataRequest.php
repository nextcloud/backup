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

use OCA\Backup\Model\RestoringData;

/**
 * Class RestoringDataRequest
 *
 * @package OCA\Backup\Db
 */
class RestoringDataRequest extends RestoringDataRequestBuilder {
	public function save(RestoringData $data): void {
		$qb = $this->getRestoringDataInsertSql();

		$qb->setValue('name', $qb->createNamedParameter($data->getName()))
		   ->setValue('type', $qb->createNamedParameter($data->getType()))
		   ->setValue('root', $qb->createNamedParameter($data->getRoot()))
		   ->setValue('path', $qb->createNamedParameter($data->getPath()));
//		   ->setValue('static', $qb->createNamedParameter(($data->isStatic() ? '1' : '0')));
		$qb->execute();
	}


	/**
	 * @return RestoringData[]
	 */
	public function getAll(): array {
		$qb = $this->getRestoringDataSelectSql();

		return $this->getItemsFromRequest($qb);
	}
}
