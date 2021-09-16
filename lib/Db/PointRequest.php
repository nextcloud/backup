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


use OCA\Backup\Exceptions\RestoringPointException;
use OCA\Backup\Exceptions\RestoringPointNotFoundException;
use OCA\Backup\Model\RestoringPoint;


/**
 * Class BackupsRequest
 *
 * @package OCA\Backup\Db
 */
class PointRequest extends PointRequestBuilder {


	/**
	 * @param RestoringPoint $point
	 *
	 * @return int
	 * @throws RestoringPointException
	 */
	public function save(RestoringPoint $point): int {
		$qb = $this->getPointInsertSql();

		$qb->setValue('uid', $qb->createNamedParameter($point->getId()))
		   ->setValue('instance', $qb->createNamedParameter($point->getInstance()))
		   ->setValue('root', $qb->createNamedParameter($point->getRoot()))
		   ->setValue('status', $qb->createNamedParameter($point->getStatus()))
		   ->setValue('metadata', $qb->createNamedParameter(json_encode($point->getMetadata())))
		   ->setValue('date', $qb->createNamedParameter($point->getDate()));

		if ($point->hasHealth()) {
			$qb->setValue('health', $qb->createNamedParameter(json_encode($point->getHealth())));
		}

		return $qb->execute();
	}


	/**
	 * @param RestoringPoint $point
	 */
	public function update(RestoringPoint $point): void {
		$qb = $this->getPointUpdateSql();

		$qb->setValue('status', $qb->createNamedParameter($point->getStatus()));
		if ($point->hasHealth()) {
			$qb->set('health', $qb->createNamedParameter(json_encode($point->getHealth())));
		}

		$qb->limitToUid($point->getId());
		$qb->limitToInstance($point->getInstance());

		$qb->execute();
	}


	/**
	 * @param string $pointId
	 * @param string $instance
	 *
	 * @return RestoringPoint
	 * @throws RestoringPointNotFoundException
	 */
	public function getById(string $pointId, string $instance = ''): RestoringPoint {
		$qb = $this->getPointSelectSql();
		$qb->limitToUid($pointId);
		if ($instance !== '') {
			$qb->limitToInstance($instance);
		}

		return $this->getItemFromRequest($qb);
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

