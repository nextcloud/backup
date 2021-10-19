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

use OCA\Backup\Exceptions\ExternalFolderNotFoundException;
use OCA\Backup\Model\ExternalFolder;

/**
 * Class ExternalFolderRequest
 *
 * @package OCA\Backup\Db
 */
class ExternalFolderRequest extends ExternalFolderRequestBuilder {


	/**
	 * @param ExternalFolder $folder
	 */
	public function save(ExternalFolder $folder): void {
		$qb = $this->getExternalFolderInsertSql();
		$qb->setValue('storage_id', $qb->createNamedParameter($folder->getStorageId()))
		   ->setValue('root', $qb->createNamedParameter($folder->getRoot()));

		$qb->execute();
	}


	/**
	 * @param int $storageId
	 */
	public function remove(int $storageId): void {
		$qb = $this->getExternalFolderDeleteSql();
		$qb->limitInt('storage_id', $storageId);

		$qb->execute();
	}


	/**
	 * @return ExternalFolder[]
	 */
	public function getAll(): array {
		$qb = $this->getExternalFolderSelectSql();

		return $this->getItemsFromRequest($qb);
	}


	/**
	 * @param int $mountId
	 *
	 * @return ExternalFolder
	 * @throws ExternalFolderNotFoundException
	 */
	public function getById(int $mountId): ExternalFolder {
		$qb = $this->getExternalFolderSelectSql();
		$qb->limitToId($mountId);

		return $this->getItemFromRequest($qb);
	}


	/**
	 * @param int $storageId
	 *
	 * @return ExternalFolder
	 * @throws ExternalFolderNotFoundException
	 */
	public function getByStorageId(int $storageId): ExternalFolder {
		$qb = $this->getExternalFolderSelectSql();
		$qb->limitInt('storage_id', $storageId);

		return $this->getItemFromRequest($qb);
	}
}
