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
	public const NONE = 'none';

	public const STRING = 'string';
	public const STRING_NONCE = 'string-nonce';


	public static $EXPORT = [
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
	 * @param string $plain
	 * @param string $generatedKey
	 *
	 * @return string
	 * @throws EncryptionKeyException
	 * @throws PackEncryptException
	 */
	public function encryptString(string $plain, string &$generatedKey = ''): string {
		$key = $this->getEncryptionKey(self::STRING, false);
		$nonce = $this->getEncryptionKey(self::STRING_NONCE, false);
		$encrypted = openssl_encrypt(
			$plain,
			self::AES_CBC,
			$key,
			OPENSSL_RAW_DATA,
			$nonce
		);

		if (!$encrypted) {
			throw new PackEncryptException('data were not encrypted');
		}

		$generatedKey = base64_encode($key) . '.' . base64_encode($nonce);

		return base64_encode($encrypted);
	}


	/**
	 * @param string $encrypted
	 * @param string $key
	 *
	 * @return string
	 * @throws PackDecryptException
	 */
	public function decryptString(string $encrypted, string $key): string {
		[$k, $n] = explode('.', $key, 2);
		$key = base64_decode($k);
		$nonce = base64_decode($n);

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

		return $plain;
	}


	/**
	 * @param string $input
	 * @param string $output
	 * @param string $name
	 *
	 * @return string
	 * @throws EncryptionKeyException
	 * @throws PackEncryptException
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
	public function decryptFile(string $input, string $output, string $name, string &$algorithm = ''): void {
		if ($algorithm === '') {
			try {
				$this->decryptFileGCM($input, $output, $name);
				$algorithm = self::AES_GCM;

				return;
			} catch (Exception $e) {
			}

			try {
				$this->decryptFileCBC($input, $output);
				$algorithm = self::AES_CBC;

				return;
			} catch (Exception $e) {
			}

			$this->decryptFileNone($input, $output);
			$algorithm = self::NONE;

			return;
		}

		switch ($algorithm) {
			case self::CHACHA:
				$this->decryptFileChacha($input, $output);
				break;
			case self::AES_GCM:
				$this->decryptFileGCM($input, $output, $name);
				break;
			case self::AES_CBC:
				$this->decryptFileCBC($input, $output);
				break;
			case self::NONE:
				$this->decryptFileNone($input, $output);
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
	public function decryptFileCBC(string $input, string $output): void {
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
	 * @param string $input
	 * @param string $output
	 */
	public function decryptFileNone(string $input, string $output): void {
		$read = fopen($input, 'rb');
		$write = fopen($output, 'wb');
		while (($r = fgets($read, 4096)) !== false) {
			fputs($write, $r);
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
			foreach (self::$EXPORT as $item) {
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
	public function getEncryptionKey(string $type, bool $storeIt = true): string {
		$keys = $this->getEncryptionKeys();

		$key = $this->get($type, $keys);
		if ($key === '') {
			try {
				$key = $this->generateKey($type);
			} catch (Exception $e) {
				throw new EncryptionKeyException($e->getMessage());
			}

			if ($storeIt) {
				$keys[$type] = $key;
				$this->configService->setAppValue(ConfigService::ENCRYPTION_KEYS, json_encode($keys));
			}
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
			case self::AES_GCM:
			case self::AES_CBC:
			case self::STRING:
				return base64_encode(random_bytes(32));
			case self::AES_GCM_NONCE:
				return base64_encode(random_bytes(12));
			case self::AES_CBC_IV:
			case self::STRING_NONCE:
				return base64_encode(random_bytes(12));
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

		if (!$this->isSodiumAvailable()) {
			return false;
		}

		return sodium_crypto_aead_aes256gcm_is_available();
	}

	/**
	 * @return bool
	 */
	private function isSodiumAvailable(): bool {
		return extension_loaded('sodium');
	}
}
