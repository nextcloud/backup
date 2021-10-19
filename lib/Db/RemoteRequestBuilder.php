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
use OCA\Backup\Exceptions\RemoteInstanceNotFoundException;
use OCA\Backup\Model\RemoteInstance;

/**
 * Class RemoteRequestBuilder
 *
 * @package OCA\Backup\Db
 */
class RemoteRequestBuilder extends CoreRequestBuilder {
	use TArrayTools;


	/**
	 * @return CoreQueryBuilder
	 */
	protected function getRemoteInsertSql(): CoreQueryBuilder {
		$qb = $this->getQueryBuilder();
		$qb->insert(self::TABLE_REMOTE);

		return $qb;
	}


	/**
	 * @return CoreQueryBuilder
	 */
	protected function getRemoteUpdateSql(): CoreQueryBuilder {
		$qb = $this->getQueryBuilder();
		$qb->update(self::TABLE_REMOTE);

		return $qb;
	}


	/**
	 * @return CoreQueryBuilder
	 */
	protected function getRemoteSelectSql(): CoreQueryBuilder {
		$qb = $this->getQueryBuilder();
		$qb->generateSelect(
			self::TABLE_REMOTE,
			self::$tables[self::TABLE_REMOTE],
			'remote'
		);

		return $qb;
	}


	/**
	 * @return CoreQueryBuilder
	 */
	protected function getRemoteDeleteSql(): CoreQueryBuilder {
		$qb = $this->getQueryBuilder();
		$qb->delete(self::TABLE_REMOTE);

		return $qb;
	}


	/**
	 * @param CoreQueryBuilder $qb
	 *
	 * @return RemoteInstance
	 * @throws RemoteInstanceNotFoundException
	 */
	public function getItemFromRequest(CoreQueryBuilder $qb): RemoteInstance {
		/** @var RemoteInstance $remote */
		try {
			$remote = $qb->asItem(RemoteInstance::class);
		} catch (RowNotFoundException | InvalidItemException $e) {
			throw new RemoteInstanceNotFoundException();
		}

		return $remote;
	}

	/**
	 * @param CoreQueryBuilder $qb
	 * @param bool $includeExtraDataOnSerialize
	 *
	 * @return RemoteInstance[]
	 */
	public function getItemsFromRequest(
		CoreQueryBuilder $qb,
		bool $includeExtraDataOnSerialize = false
	): array {
		/** @var RemoteInstance[] $result */
		return $qb->asItems(
			RemoteInstance::class,
			['includeExtraDataOnSerialize' => $includeExtraDataOnSerialize]
		);
	}
}
