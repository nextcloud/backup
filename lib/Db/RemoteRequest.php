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

use OCA\Backup\Exceptions\RemoteInstanceNotFoundException;
use OCA\Backup\Exceptions\RemoteInstanceUidException;
use OCA\Backup\Model\RemoteInstance;

/**
 * Class RemoteRequest
 *
 * @package OCA\Backup\Db
 */
class RemoteRequest extends RemoteRequestBuilder {


	/**
	 * create a new Person in the database.
	 *
	 * @param RemoteInstance $remote
	 * @param bool $force
	 *
	 * @return int
	 * @throws RemoteInstanceUidException
	 */
	public function save(RemoteInstance $remote, bool $force = false): int {
		if (!$force) {
			$remote->mustBeIdentityAuthed();
		}
		$qb = $this->getRemoteInsertSql();
		$qb->setValue('uid', $qb->createNamedParameter($remote->getUid(true)))
		   ->setValue('instance', $qb->createNamedParameter($remote->getInstance()))
		   ->setValue('href', $qb->createNamedParameter($remote->getId()))
		   ->setValue('exchange', $qb->createNamedParameter($remote->getExchange()))
		   ->setValue('item', $qb->createNamedParameter(json_encode($remote->getOrigData())));

		return $qb->execute();
	}


	/**
	 * @param RemoteInstance $remote
	 * @param bool $force
	 *
	 * @throws RemoteInstanceUidException
	 */
	public function update(RemoteInstance $remote, bool $force = false) {
		if (!$force) {
			$remote->mustBeIdentityAuthed();
		}

		$qb = $this->getRemoteUpdateSql();
		$qb->set('uid', $qb->createNamedParameter($remote->getUid(true)))
		   ->set('href', $qb->createNamedParameter($remote->getId()))
		   ->set('exchange', $qb->createNamedParameter($remote->getExchange()))
		   ->set('item', $qb->createNamedParameter(json_encode($remote->getOrigData())));

		$qb->limitToInstance($remote->getInstance());

		$qb->execute();
	}


	/**
	 * @return RemoteInstance[]
	 */
	public function getAll(bool $includeExtraDataOnSerialize = false): array {
		$qb = $this->getRemoteSelectSql();

		return $this->getItemsFromRequest($qb, $includeExtraDataOnSerialize);
	}

	/**
	 * @return RemoteInstance[]
	 */
	public function getOutgoing(): array {
		$qb = $this->getRemoteSelectSql();
		$qb->limitBitwise('exchange', RemoteInstance::EXCHANGE_OUT);

		return $this->getItemsFromRequest($qb);
	}


	/**
	 * @throws RemoteInstanceNotFoundException
	 */
	public function getByInstance(string $instance): RemoteInstance {
		$qb = $this->getRemoteSelectSql();
		$qb->limit('instance', $instance, '', false);

		return $this->getItemFromRequest($qb);
	}

	/**
	 * @param string $href
	 *
	 * @return RemoteInstance
	 * @throws RemoteInstanceNotFoundException
	 */
	public function getFromHref(string $href): RemoteInstance {
		$qb = $this->getRemoteSelectSql();
		$qb->limit('href', $href, '', false);

		return $this->getItemFromRequest($qb);
	}


	/**
	 * @param RemoteInstance $remoteInstance
	 * @param bool $force
	 *
	 * @throws RemoteInstanceUidException
	 */
	public function insertOrUpdate(RemoteInstance $remoteInstance, bool $force = false): void {
		try {
			$this->getFromHref($remoteInstance->getId());
			$this->update($remoteInstance, $force);
		} catch (RemoteInstanceNotFoundException $e) {
			$this->save($remoteInstance, $force);
		}
	}


	/**
	 * @param string $address
	 */
	public function remove(string $address): void {
		$qb = $this->getRemoteDeleteSql();
		$qb->limit('instance', $address, '', false);

		$qb->execute();
	}
}
