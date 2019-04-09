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


use daita\MySmallPhpTools\Traits\TArrayTools;
use daita\MySmallPhpTools\Traits\TPathTools;
use JsonSerializable;


/**
 * Class BackupOptions
 *
 * @package OCA\Backup\Model
 */
class BackupOptions implements JsonSerializable {


	use TArrayTools;
	use TPathTools;


	/** @var string */
	private $newRoot = '';

	/** @var string */
	private $path = '';

	/** @var string */
	private $search = '';

	/** @var array */
	private $config = [];

	/** @var string */
	private $chunk = '';

	/** @var string */
	private $archive = '';

	/** @var bool */
	private $all = false;

	/** @var bool */
	private $fixDataDir = false;


	/**
	 * BackupOptions constructor.
	 */
	public function __construct() {
	}


	/**
	 * @return string
	 */
	public function getNewRoot(): string {
		return $this->newRoot;
	}

	/**
	 * @param string $root
	 *
	 * @return BackupOptions
	 */
	public function setNewRoot(string $root): BackupOptions {
		if ($root !== '') {
			$root = $this->withEndSlash($root);
		}

		$this->newRoot = $root;

		return $this;
	}


	/**
	 * @return string
	 */
	public function getPath(): string {
		return $this->path;
	}

	/**
	 * @param string $path
	 *
	 * @return BackupOptions
	 */
	public function setPath(string $path): BackupOptions {
		$this->path = $path;

		return $this;
	}


	/**
	 * @return string
	 */
	public function getSearch(): string {
		return $this->search;
	}

	/**
	 * @param string $search
	 *
	 * @return BackupOptions
	 */
	public function setSearch(string $search): BackupOptions {
		$this->search = $search;

		return $this;
	}


	/**
	 * @return array
	 */
	public function getConfig(): array {
		return $this->config;
	}

	/**
	 * @param array $config
	 *
	 * @return BackupOptions
	 */
	public function setConfig(array $config): BackupOptions {
		$this->config = $config;

		return $this;
	}

	/**
	 * @param string $json
	 *
	 * @return BackupOptions
	 */
	public function setConfigRaw(string $json): BackupOptions {
		$config = json_decode($json, true);
		if (is_array($config)) {
			$this->setConfig($config);
		}

		return $this;
	}


	/**
	 * @return string
	 */
	public function getChunk(): string {
		return $this->chunk;
	}

	/**
	 * @param string $chunk
	 *
	 * @return BackupOptions
	 */
	public function setChunk(string $chunk): BackupOptions {
		$this->chunk = $chunk;

		return $this;
	}


	/**
	 * @return string
	 */
	public function getArchive(): string {
		return $this->archive;
	}

	/**
	 * @param string $archive
	 *
	 * @return BackupOptions
	 */
	public function setArchive(string $archive): BackupOptions {
		$this->archive = $archive;

		return $this;
	}


	/**
	 * @return bool
	 */
	public function isAll(): bool {
		return $this->all;
	}

	/**
	 * @param bool $all
	 *
	 * @return BackupOptions
	 */
	public function setAll(bool $all): BackupOptions {
		$this->all = $all;

		return $this;
	}


	/**
	 * @return bool
	 */
	public function isFixDataDir(): bool {
		return $this->fixDataDir;
	}

	/**
	 * @param bool $fixDataDir
	 */
	public function setFixDataDir(bool $fixDataDir): void {
		$this->fixDataDir = $fixDataDir;
	}


	/**
	 * @param array $data
	 *
	 * @return BackupOptions
	 */
	public function import(array $data): BackupOptions {
		$this->setNewRoot($this->get('newRoot', $data, ''));
		$this->setPath($this->get('path', $data, ''));
		$this->setSearch($this->get('search', $data, ''));
		$this->setArchive($this->get('archive', $data, ''));
		$this->setChunk($this->get('chunk', $data, ''));
		$this->setAll($this->getBool('all', $data, false));

		return $this;
	}


	/**
	 * @return array
	 */
	public function jsonSerialize() {
		return [
			'newRoot' => $this->getNewRoot(),
			'search'  => $this->getSearch(),
			'path'    => $this->getPath(),
			'archive' => $this->getArchive(),
			'chunk'   => $this->getChunk(),
			'all'     => $this->isAll()
		];
	}

}

