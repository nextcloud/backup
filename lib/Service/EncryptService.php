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

use ArtificialOwl\MySmallPhpTools\Traits\TArrayTools;
use Exception;
use OCA\Backup\Exceptions\EncryptException;
use OCA\Backup\Exceptions\EncryptionKeyException;
use OCA\Backup\Exceptions\PackDecryptException;
use OCA\Backup\Exceptions\PackEncryptException;
use SodiumException;

/**
 * Class EncryptService
 *
 * @package OCA\Backup\Service
 */
class EncryptService {
	use TArrayTools;


	public const BLOCK_SIZE = 500;
	public const CUSTOM_CHUNK_SIZE = 1048576;
	public const KEY_LENGTH = 32;

	public const AES_GCM = 'aes-256-gcm';
	public const AES_GCM_NONCE = 'aes-256-gcm-nonce';

	public const AES_CBC = 'aes-256-cbc';
	public const AES_CBC_IV = 'aes-256-cbc-iv';

	public const CHACHA = 'chacha';


	public static $LIST = [
		self::AES_GCM,
		self::AES_GCM_NONCE,
		self::AES_CBC,
		self::AES_CBC_IV,
		self::CHACHA
	];


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
	 * @param string $name
	 *
	 * @return string
	 * @throws EncryptionKeyException
	 * @throws SodiumException
	 */
	public function encryptFile(string $input, string $output, string $name): string {
		if (!$this->useSodiumCryptoAead()) {
			$this->encryptFileCBC($input, $output);

			return self::AES_CBC;
		}

		$this->encryptFileGCM($input, $output, $name);

		return self::AES_GCM;
	}


	/**
	 * @param string $input
	 * @param string $output
	 * @param string $name
	 *
	 * @throws EncryptionKeyException
	 * @throws SodiumException
	 */
	public function encryptFileGCM(string $input, string $output, string $name): void {
		$key = base64_decode($this->getEncryptionKey(self::AES_GCM));
		$nonce = base64_decode($this->getEncryptionKey(self::AES_GCM_NONCE));
		$write = fopen($output, 'wb');

		$plain = file_get_contents($input);
		$encrypted = sodium_crypto_aead_aes256gcm_encrypt(
			$plain,
			$name,
			$nonce,
			$key
		);

		fwrite($write, $encrypted);
		fclose($write);
		sodium_memzero($plain);
	}


	/**
	 * @param string $input
	 * @param string $output
	 *
	 * @throws EncryptionKeyException
	 * @throws SodiumException
	 * @throws PackEncryptException
	 */
	public function encryptFileCBC(string $input, string $output): void {
		$key = base64_decode($this->getEncryptionKey(self::AES_CBC));
		$iv = base64_decode($this->getEncryptionKey(self::AES_CBC_IV));

		$write = fopen($output, 'wb');

		$plain = file_get_contents($input);
		$encrypted = openssl_encrypt(
			$plain,
			self::AES_CBC,
			$key,
			OPENSSL_RAW_DATA,
			$iv
		);

		if (!$encrypted) {
			throw new PackEncryptException('data were not encrypted');
		}

		fwrite($write, $encrypted);
		fclose($write);
		sodium_memzero($plain);
	}


	/**
	 * @param string $input
	 * @param string $output
	 *
	 * @throws EncryptionKeyException
	 * @throws SodiumException
	 */
	public function encryptFileChacha(string $input, string $output): void {
		$key = base64_decode($this->getEncryptionKey(self::CHACHA));

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
	 * @param string $name
	 * @param string $algorithm
	 *
	 * @throws EncryptionKeyException
	 * @throws PackDecryptException
	 * @throws SodiumException
	 */
	public function decryptFile(string $input, string $output, string $name, string $algorithm = ''): void {
		if ($algorithm === '') {
			// TODO: test them all ?
		}

		switch ($algorithm) {
			case self::CHACHA:
				$this->decryptFileChacha($input, $output);
				break;
			case self::AES_GCM:
				$this->decryptFileGCM($input, $output, $name);
				break;
			case self::AES_CBC:
				$this->decryptFileOpenSSL($input, $output);
				break;
		}
	}


	/**
	 * @param string $input
	 * @param string $output
	 * @param string $name
	 *
	 * @throws EncryptionKeyException
	 * @throws PackDecryptException
	 * @throws SodiumException
	 */
	public function decryptFileGCM(string $input, string $output, string $name): void {
		$key = base64_decode($this->getEncryptionKey(self::AES_GCM));
		$nonce = base64_decode($this->getEncryptionKey(self::AES_GCM_NONCE));
		$write = fopen($output, 'wb');

		$encrypted = file_get_contents($input);
		$plain = sodium_crypto_aead_aes256gcm_decrypt(
			$encrypted,
			$name,
			$nonce,
			$key
		);

		if ($plain === false) {
			throw new PackDecryptException('cannot decrypt data');
		}

		fwrite($write, $plain);
		sodium_memzero($plain);
		fclose($write);
	}


	/**
	 * @param string $input
	 * @param string $output
	 *
	 * @throws EncryptionKeyException
	 * @throws PackDecryptException
	 * @throws SodiumException
	 */
	public function decryptFileOpenSSL(string $input, string $output): void {
		$key = base64_decode($this->getEncryptionKey(self::AES_CBC));
		$nonce = base64_decode($this->getEncryptionKey(self::AES_CBC_IV));
		$write = fopen($output, 'wb');

		$encrypted = file_get_contents($input);
		$plain = openssl_decrypt(
			$encrypted,
			self::AES_CBC,
			$key,
			OPENSSL_RAW_DATA,
			$nonce
		);

		if ($plain === false) {
			throw new PackDecryptException('cannot decrypt data');
		}

		fwrite($write, $plain);
		sodium_memzero($plain);
		fclose($write);
	}


	/**
	 * @param string $input
	 * @param string $output
	 *
	 * @throws EncryptionKeyException
	 * @throws SodiumException
	 */
	public function decryptFileChacha(string $input, string $output): void {
		$key = base64_decode($this->getEncryptionKey(self::CHACHA));
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
	 * @param bool $generate
	 *
	 * @return array
	 */
	public function getEncryptionKeys(bool $generate = false): array {
		if ($generate) {
			foreach (self::$LIST as $item) {
				try {
					$this->getEncryptionKey($item);
				} catch (EncryptionKeyException $e) {
				}
			}
		}

		$keys = json_decode($this->configService->getAppValue(ConfigService::ENCRYPTION_KEYS), true);
		if (!is_array($keys)) {
			$keys = [];
		}

		return $keys;
	}


	/**
	 * @throws EncryptionKeyException
	 */
	public function getEncryptionKey(string $type): string {
		$keys = $this->getEncryptionKeys();

		$key = $this->get($type, $keys);
		if ($key === '') {
			try {
				$key = $this->generateKey($type);
			} catch (Exception $e) {
				throw new EncryptionKeyException($e->getMessage());
			}

			$keys[$type] = $key;
			$this->configService->setAppValue(ConfigService::ENCRYPTION_KEYS, json_encode($keys));
		}

		return $key;
	}


	/**
	 * @param string $type
	 *
	 * @return string
	 * @throws Exception
	 */
	private function generateKey(string $type): string {
		switch ($type) {
			case self::CHACHA:
				return base64_encode(random_bytes(SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES));
			case self::AES_GCM:
			case self::AES_CBC:
				return base64_encode(random_bytes(SODIUM_CRYPTO_AEAD_AES256GCM_KEYBYTES));
			case self::AES_CBC_IV:
			case self::AES_GCM_NONCE:
				return base64_encode(random_bytes(SODIUM_CRYPTO_AEAD_AES256GCM_NPUBBYTES));
		}

		throw new EncryptionKeyException('unknown key type');
	}


	/**
	 * @return bool
	 */
	private function useSodiumCryptoAead(): bool {
		if ($this->configService->getAppValueBool(ConfigService::FORCE_CBC)) {
			return false;
		}

		return sodium_crypto_aead_aes256gcm_is_available();
	}
}
