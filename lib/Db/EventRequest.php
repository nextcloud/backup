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

use OCA\Backup\Model\BackupEvent;

/**
 * Class EventRequest
 *
 * @package OCA\Backup\Db
 */
class EventRequest extends EventRequestBuilder {
	public function save(BackupEvent $event): void {
		$qb = $this->getEventInsertSql();
		$qb->setValue('type', $qb->createNamedParameter($event->getType()))
		   ->setValue('author', $qb->createNamedParameter($event->getAuthor()))
		   ->setValue('status', $qb->createNamedParameter($event->getStatus()))
		   ->setValue('data', $qb->createNamedParameter(json_encode($event->getData())))
		   ->setValue('result', $qb->createNamedParameter(json_encode($event->getResult())));

		$qb->execute();
	}


	/**
	 * @param BackupEvent $event
	 */
	public function update(BackupEvent $event): void {
		$qb = $this->getEventUpdateSql();
		$qb->set('status', $qb->createNamedParameter($event->getStatus()))
		   ->set('result', $qb->createNamedParameter(json_encode($event->getResult())));

		$qb->execute();
	}


	/**
	 * @return BackupEvent[]
	 */
	public function getQueue(): array {
		$qb = $this->getEventSelectSql();
		$qb->limitInt('status', BackupEvent::STATUS_QUEUE);

		return $this->getItemsFromRequest($qb);
	}
}
