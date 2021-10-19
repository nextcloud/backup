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

use OCA\Backup\Exceptions\ChangedFileNotFoundException;
use OCA\Backup\Model\ChangedFile;

class ChangesRequest extends ChangesRequestBuilder {


	/**
	 * @param ChangedFile $file
	 */
	public function insert(ChangedFile $file): void {
		$qb = $this->getChangesInsertSql();
		$qb->setValue('path', $qb->createNamedParameter($file->getPath()));
		$qb->setValue('hash', $qb->createNamedParameter($file->getHash()));

		$qb->execute();
	}


	/**
	 * @return ChangedFile[]
	 */
	public function getAll(): array {
		$qb = $this->getChangesSelectSql();

		return $this->getItemsFromRequest($qb);
	}


	/**
	 * @param string $hash
	 *
	 * @return ChangedFile
	 * @throws ChangedFileNotFoundException
	 */
	public function getByHash(string $hash): ChangedFile {
		$qb = $this->getChangesSelectSql();
		$qb->limit('hash', $hash);

		return $this->getItemFromRequest($qb);
	}


	/**
	 * @param ChangedFile $file
	 */
	public function insertIfNotFound(ChangedFile $file): void {
		try {
			$this->getByHash($file->getHash());
		} catch (ChangedFileNotFoundException $e) {
			$this->insert($file);
		}
	}


	/**
	 *
	 */
	public function reset() {
		$qb = $this->getChangesDeleteSql();
		$qb->execute();
	}
}
