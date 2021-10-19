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

use Exception;
use OCA\Backup\Exceptions\EncryptException;
use OCA\Backup\Exceptions\EncryptionKeyException;
use SodiumException;

/**
 * Class EncryptService
 *
 * @package OCA\Backup\Service
 */
class EncryptService {
	public const BLOCK_SIZE = 500;
	public const CUSTOM_CHUNK_SIZE = 8192;
	public const KEY_LENGTH = 32;


	/** @var ConfigService */
	private $configService;


	/**
	 * EncryptService constructor.
	 */
	public function __construct(ConfigService $configService) {
		$this->configService = $configService;
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
		$key = base64_decode($key);

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


	/**
	 * @param string $input
	 * @param string $output
	 *
	 * @throws SodiumException
	 * @throws EncryptionKeyException
	 */
	public function encryptFile(string $input, string $output): void {
		$key = base64_decode($this->getEncryptionKey());
		$read = fopen($input, 'rb');
		$write = fopen($output, 'wb');
		[$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($key);

		fwrite($write, $header, 24); // Write the header first:
		$size = fstat($read)['size'];
		for ($pos = 0; $pos < $size; $pos += self::CUSTOM_CHUNK_SIZE) {
			$chunk = fread($read, self::CUSTOM_CHUNK_SIZE);
			$encrypted = sodium_crypto_secretstream_xchacha20poly1305_push($state, $chunk);
			fwrite($write, $encrypted, self::CUSTOM_CHUNK_SIZE + 17);
			sodium_memzero($chunk);
		}

		fclose($read);
		fclose($write);
	}


	/**
	 * @param string $input
	 * @param string $output
	 *
	 * @throws SodiumException
	 * @throws EncryptionKeyException
	 */
	public function decryptFile(string $input, string $output): void {
		$key = base64_decode($this->getEncryptionKey());
		$read = fopen($input, 'rb');
		$write = fopen($output, 'wb');

		$header = fread($read, 24);
		$state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $key);
		$size = fstat($read)['size'];
		$readChunkSize = self::CUSTOM_CHUNK_SIZE + 17;
		for ($pos = 24; $pos < $size; $pos += $readChunkSize) {
			$chunk = fread($read, $readChunkSize);
			[$plain, $tag] = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $chunk);
			fwrite($write, $plain, self::CUSTOM_CHUNK_SIZE);
			sodium_memzero($plain);
		}

		fclose($read);
		fclose($write);
	}


	/**
	 * @throws EncryptionKeyException
	 */
	public function getEncryptionKey(): string {
		$key = $this->configService->getAppValue(ConfigService::ENCRYPTION_KEY);
		if ($key === '') {
			try {
				$key = base64_encode(random_bytes(SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES));
			} catch (Exception $e) {
				throw new EncryptionKeyException();
			}
			$this->configService->setAppValue(ConfigService::ENCRYPTION_KEY, $key);
		}

		return $key;
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
