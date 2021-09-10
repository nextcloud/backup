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


use OCA\Backup\Model\Backup;
use OCA\Backup\Model\RestoringPoint;


/**
 * Class BackupsRequest
 *
 * @package OCA\Backup\Db
 */
class PointRequest extends PointRequestBuilder {


	/**
	 * create a new Person in the database.
	 *
	 * @param Backup $backup
	 *
	 * @return int
	 */
	public function create(Backup $backup): int {
		$qb = $this->getPointInsertSql();

//		$qb->setValue('id', $qb->createNamedParameter($id))
////			   ->setValue('type', $qb->createNamedParameter($actor->getType()))
//		   ->setValue('user_id', $qb->createNamedParameter($actor->getUserId()))
//		   ->setValue('name', $qb->createNamedParameter($actor->getName()))
//		   ->setValue('summary', $qb->createNamedParameter($actor->getSummary()))
//		   ->setValue(
//			   'creation',
//			   $qb->createNamedParameter(new DateTime('now'), IQueryBuilder::PARAM_DATE)
//		   );

		return $qb->execute();
	}


	/**
	 * @param Backup $backup
	 */
	public function update(Backup $backup) {
//		$qb = $this->getPointUpdateSql();
//		$qb->limitToId($backup->getId());
//
//		$qb->execute();
	}


	/**
	 * @param string $instance
	 *
	 * @return RestoringPoint[]
	 */
	public function getByInstance(string $instance): array {
		$qb = $this->getPointSelectSql();
		$qb->limitToInstance($instance);

		return $this->getItemsFromRequest($qb);
	}

}

