<?php

declare(strict_types=1);


/**
 * Nextcloud - Backup now. Restore later.
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Maxence Lange <maxence@artificial-owl.com>
 * @copyright 2022
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


namespace OCA\Backup\Tools\Traits;

use JsonSerializable;

trait TAsync {
	use TNCSetup;


	/** @var string */
	public static $SETUP_TIME_LIMIT = 'async_time_limit';


	/**
	 * Hacky way to async the rest of the process without keeping client on hold.
	 *
	 * @param string $result
	 */
	public function async(string $result = ''): void {
		if (ob_get_contents() !== false) {
			ob_end_clean();
		}

		header('Connection: close');
		header('Content-Encoding: none');
		ignore_user_abort();
		$timeLimit = $this->setupInt(self::$SETUP_TIME_LIMIT);
		set_time_limit(($timeLimit > 0) ? $timeLimit : 0);
		ob_start();

		echo($result);

		$size = ob_get_length();
		header('Content-Length: ' . $size);
		ob_end_flush();
		flush();
	}

	/**
	 * @param JsonSerializable $obj
	 */
	public function asyncObj(JsonSerializable $obj): void {
		$this->async(json_encode($obj));
	}
}
