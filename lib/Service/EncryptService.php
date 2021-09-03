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


namespace OCA\Backup\Service;


use OCA\Backup\Exceptions\ArchiveNotFoundException;
use OCA\Backup\Exceptions\EncryptionKeyException;

/**
 * Class EncryptService
 *
 * @package OCA\Backup\Service
 */
class EncryptService {


	const BLOCK_SIZE = 500;


	/** @var MiscService */
	private $miscService;


	/**
	 * EncryptService constructor.
	 *
	 * @param MiscService $miscService
	 */
	public function __construct(MiscService $miscService) {
		$this->miscService = $miscService;
	}


	/**
	 * @param resource $in
	 * @param resource $out
	 * @param string $key
	 */
	public function encryptFile($in, $out, $key) {
		$iv = openssl_random_pseudo_bytes(16);

		fwrite($out, $iv);
		while (!feof($in)) {
			$clear = fread($in, 16 * self::BLOCK_SIZE);
			$encrypted = openssl_encrypt($clear, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);
			fwrite($out, $encrypted);

			$iv = substr($encrypted, 0, 16);
		}

		fclose($in);
		fclose($out);
	}


	/**
	 * @param resource $in
	 * @param resource $out
	 * @param string $key
	 *
	 * @throws ArchiveNotFoundException
	 * @throws EncryptionKeyException
	 */
	public function decryptFile($in, $out, $key) {
		if (is_bool($in)) {
			throw new ArchiveNotFoundException('archive not found');
		}

		$iv = fread($in, 16);
		while (!feof($in)) {
			$encrypted = fread($in, 16 * (self::BLOCK_SIZE + 1));
			$clear = openssl_decrypt($encrypted, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);
			if (is_bool($clear)) {
				throw new EncryptionKeyException('Wrong encryption key');
			}
			fwrite($out, $clear);

			$iv = substr($encrypted, 0, 16);
		}

		fclose($in);
		fclose($out);
	}

}

