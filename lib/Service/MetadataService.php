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


namespace OCA\Backup\Service;


use OCA\Backup\Model\RestoringPoint;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;

/**
 * Class MetadataService
 *
 * @package OCA\Backup\Service
 */
class MetadataService {


	const METADATA_FILE = 'restoring-point.data';


	/**
	 * @param RestoringPoint $point
	 *
	 * @throws NotPermittedException
	 * @throws NotFoundException
	 */
	public function saveMetadata(RestoringPoint $point) {
		$folder = $point->getBaseFolder();

		try {
			$file = $folder->getFile(self::METADATA_FILE);
		} catch (NotFoundException $e) {
			$file = $folder->newFile(self::METADATA_FILE);
		}

		$file->putContent(json_encode($point, JSON_PRETTY_PRINT));
	}



}

