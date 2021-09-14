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


namespace OCA\Backup\Model;


use ArtificialOwl\MySmallPhpTools\Traits\TArrayTools;
use ArtificialOwl\MySmallPhpTools\Traits\TPathTools;
use ArtificialOwl\MySmallPhpTools\Traits\TStringTools;
use JsonSerializable;
use OCA\Backup\Exceptions\VersionException;
use OCP\Files\SimpleFS\ISimpleFolder;


/**
 * Class Backup
 *
 * @package OCA\Backup\Model
 */
class Backup implements JsonSerializable {


	const STATUS_OK = 1;
	const STATUS_UPLOAD_OK = 2;
	const STATUS_UPLOAD_EVERYWHERE = 4;

	const STATUS_FAILED = 64;

	const FILE_CONFIG = 1;


	use TArrayTools;
	use TStringTools;
	use TPathTools;


	/** @var string */
	private $id = '';

	/** @var string */
	private $name = '';

	/** @var int */
	private $status = 0;

	/** @var int[] */
	private $version = [];

	/** @var string[] */
	private $apps = [];

	/** @var BackupChunk[] */
	private $chunks = [];

	/** @var ISimpleFolder */
	private $baseFolder = null;

	/** @var Error[] */
	private $errors = [];

	/** @var RemoteStorage[] */
	private $storages = [];

	/** @var int */
	private $creation = 0;

	/** @var bool */
	private $local = false;

	/** @var string */
	private $encryptionKey = '';

	/** @var BackupOptions */
	private $options = null;


	/**
	 * Backup constructor.
	 *
	 * @param bool $init
	 */
	public function __construct() {
		$this->options = new BackupOptions();
	}


	/**
	 * @return string
	 */
	public function getId(): string {
		return $this->id;
	}

	/**
	 * @param string $id
	 *
	 * @return Backup
	 */
	public function setId(string $id): Backup {
		$this->id = $id;

		return $this;
	}


	/**
	 * @return string
	 */
	public function getName(): string {
		return $this->name;
	}

	/**
	 * @param string $name
	 *
	 * @return Backup
	 */
	public function setName(string $name): Backup {
		$this->name = $name;

		return $this;
	}


	/**
	 * @return int
	 */
	public function getStatus(): int {
		return $this->status;
	}

	/**
	 * @param int $status
	 *
	 * @return Backup
	 */
	public function setStatus(int $status): Backup {
		$this->status = $status;

		return $this;
	}


	/**
	 * @return Error[]
	 */
	public function getErrors(): array {
		return $this->errors;
	}

	/**
	 * @param Error[] $errors
	 *
	 * @return Backup
	 */
	public function setErrors(array $errors): Backup {
		$this->errors = $errors;

		return $this;
	}


	/**
	 * @return RemoteStorage[]
	 */
	public function getStorages(): array {
		return $this->storages;
	}

	/**
	 * @param RemoteStorage[] $storages
	 *
	 * @return Backup
	 */
	public function setStorages(array $storages): Backup {
		$this->storages = $storages;

		return $this;
	}


	/**
	 * @return int[]
	 */
	public function getVersion(): array {
		return $this->version;
	}

	/**
	 * @param int[] $version
	 *
	 * @return Backup
	 */
	public function setVersion(array $version): Backup {
		$this->version = $version;

		return $this;
	}

	/**
	 * @param array $current
	 *
	 * @throws VersionException
	 */
	public function compareVersion(array $current): void {
		// TODO compare version !
		throw new VersionException(
			'Your nextcloud must be upgraded to at least v' . implode('.', $this->getVersion())
		);
	}


	/**
	 * @return string[]
	 */
	public function getApps(): array {
		return $this->apps;
	}

	/**
	 * @param string[] $apps
	 *
	 * @return Backup
	 */
	public function setApps(array $apps): Backup {
		$this->apps = $apps;

		return $this;
	}

	/**
	 * @param string $appId
	 *
	 * @return Backup
	 */
	public function addApp(string $appId): Backup {
		$this->apps[] = $appId;

		return $this;
	}


	/**
	 * @param bool $filtered
	 *
	 * @return BackupChunk[]
	 */
	public function getChunks($filtered = false): array {
		$options = $this->getOptions();
		if (!$filtered || $options->getChunk() === '') {
			return $this->chunks;
		}

		$options = $this->getOptions();
		foreach ($this->chunks as $chunk) {
			if ($chunk->getName() === $options->getChunk()) {
				return [$chunk];
			}
		}

		return [];
	}

	/**
	 * @param array $chunks
	 *
	 * @return Backup
	 */
	public function setChunks(array $chunks): Backup {
		$this->chunks = $chunks;

		return $this;
	}

	/**
	 * @param BackupChunk $chunk
	 *
	 * @return Backup
	 */
	public function addChunk(BackupChunk $chunk): Backup {
		$this->chunks[] = $chunk;

		return $this;
	}


	/**
	 * @return bool
	 */
	public function hasBaseFolder(): bool {
		return ($this->baseFolder !== null);
	}

	/**
	 * @param ISimpleFolder $folder
	 *
	 * @return Backup
	 */
	public function setBaseFolder(ISimpleFolder $folder): Backup {
		$this->baseFolder = $folder;

		return $this;
	}

	/**
	 * @return ISimpleFolder
	 */
	public function getBaseFolder(): ISimpleFolder {
		return $this->baseFolder;
	}


	/**
	 * @return int
	 */
	public function getCreation(): int {
		return $this->creation;
	}

	/**
	 * @param int $creation
	 *
	 * @return Backup
	 */
	public function setCreation(int $creation): Backup {
		$this->creation = $creation;

		return $this;
	}


	/**
	 * @return bool
	 */
	public function isLocal(): bool {
		return $this->local;
	}

	/**
	 * @param bool $local
	 *
	 * @return Backup
	 */
	public function setLocal(bool $local): Backup {
		$this->local = $local;

		return $this;
	}


	/**
	 * @return BackupOptions
	 */
	public function getOptions(): BackupOptions {
		return $this->options;
	}

	/**
	 * @param BackupOptions $options
	 *
	 * @return Backup
	 */
	public function setOptions(BackupOptions $options): Backup {
		$this->options = $options;

		return $this;
	}


	/**
	 * @return string
	 */
	public function getEncryptionKey(): string {
		return $this->encryptionKey;
	}

	/**
	 * @param string $encryptionKey
	 *
	 * @return Backup
	 */
	public function setEncryptionKey(string $encryptionKey): Backup {
		$this->encryptionKey = $encryptionKey;

		return $this;
	}


	/**
	 * @param array $data
	 *
	 * @return Backup
	 */
	public function import(array $data): Backup {
		$this->setId($this->get('id', $data, ''));
		$this->setStatus($this->getInt('status', $data));
		$this->setName($this->get('name', $data, ''));
		$this->setVersion($this->getArray('version', $data, []));
		$this->setApps($this->getArray('apps', $data, []));
		$this->setCreation($this->getInt('creation', $data, 0));
		$this->setChunks($this->getList('chunks', $data, [BackupChunk::class, 'import'], []));
		$this->setErrors($this->getList('errors', $data, [Error::class, 'import'], []));
		$this->setStorages($this->getList('remotes', $data, [RemoteStorage::class, 'import'], []));

		return $this;
	}


	/**
	 * @return array
	 */
	public function jsonSerialize() {
		return [
			'id'       => $this->getId(),
			'status'   => $this->getStatus(),
			'name'     => $this->getName(),
			'version'  => $this->getVersion(),
			'apps'     => $this->getApps(),
			'creation' => $this->getCreation(),
			'chunks'   => $this->getChunks(),
			'errors'   => $this->getErrors(),
			'storages' => $this->getStorages()
		];
	}

}

