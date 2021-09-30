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


use Exception;
use OCA\Backup\Exceptions\EncryptException;
use SodiumException;

/**
 * Class EncryptService
 *
 * @package OCA\Backup\Service
 */
class EncryptService {


	const BLOCK_SIZE = 500;


	/**
	 * EncryptService constructor.
	 */
	public function __construct() {
	}


	/**
	 * @param string $data
	 * @param string $key
	 *
	 * @return string
	 * @throws SodiumException
	 */
	public function encryptString(string $data, string $key): string {
		try {
			$nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
		} catch (Exception $e) {
			throw new SodiumException('random_bytes - ' . $e->getMessage());
		}

		$encrypted = $nonce . sodium_crypto_secretbox($data, $nonce, $key);
		sodium_memzero($data);
		sodium_memzero($key);

		return base64_encode($encrypted);
	}


	/**
	 * @param string $encrypted
	 * @param string $key
	 *
	 * @return string
	 * @throws EncryptException
	 * @throws SodiumException
	 */
	public function decryptString(string $encrypted, string $key): string {
		$encrypted = base64_decode($encrypted);
		if ($encrypted === false) {
			throw new EncryptException('invalid data');
		}

		if (mb_strlen($encrypted, '8bit') < (SODIUM_CRYPTO_SECRETBOX_NONCEBYTES
											 + SODIUM_CRYPTO_SECRETBOX_MACBYTES)) {
			throw new EncryptException('invalid data');
		}

		$nonce = mb_substr($encrypted, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, '8bit');
		$ciphertext = mb_substr($encrypted, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, null, '8bit');

		$plain = sodium_crypto_secretbox_open(
			$ciphertext,
			$nonce,
			$key
		);

		if ($plain === false) {
			throw new EncryptException('invalid data');
		}
		sodium_memzero($ciphertext);
		sodium_memzero($key);

		return $plain;
	}


//
//	/**
//	 * @param resource $in
//	 * @param resource $out
//	 * @param string $key
//	 */
//	public function encryptFile($in, $out, $key) {
//		$iv = openssl_random_pseudo_bytes(16);
//
//		fwrite($out, $iv);
//		while (!feof($in)) {
//			$clear = fread($in, 16 * self::BLOCK_SIZE);
//			$encrypted = openssl_encrypt($clear, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);
//			fwrite($out, $encrypted);
//
//			$iv = substr($encrypted, 0, 16);
//		}
//
//		fclose($in);
//		fclose($out);
//	}
//
//
//	/**
//	 * @param resource $in
//	 * @param resource $out
//	 * @param string $key
//	 *
//	 * @throws ArchiveNotFoundException
//	 * @throws EncryptionKeyException
//	 */
//	public function decryptFile($in, $out, $key) {
//		if (is_bool($in)) {
//			throw new ArchiveNotFoundException('archive not found');
//		}
//
//		$iv = fread($in, 16);
//		while (!feof($in)) {
//			$encrypted = fread($in, 16 * (self::BLOCK_SIZE + 1));
//			$clear = openssl_decrypt($encrypted, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);
//			if (is_bool($clear)) {
//				throw new EncryptionKeyException('Wrong encryption key');
//			}
//			fwrite($out, $clear);
//
//			$iv = substr($encrypted, 0, 16);
//		}
//
//		fclose($in);
//		fclose($out);
//	}

}

