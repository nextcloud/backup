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


namespace OCA\Backup\Command;


use OC\Core\Command\Base;
use OCA\Backup\Db\ExternalFolderRequest;
use OCA\Backup\Model\ExternalFolder;
use OCA\Files_External\Service\GlobalStoragesService;
use OCA\Files_External\Service\UserStoragesService;
use OCP\Files\Mount\IMountManager;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


/**
 * Class ExternalAdd
 *
 * @package OCA\Backup\Command
 */
class ExternalAdd extends Base {


	/** @var IMountManager */
	private $mountManager;

	/** @var GlobalStoragesService */
	private $globalStoragesService;

	/** @var UserStoragesService */
	private $userStoragesService;

	/** @var ExternalFolderRequest */
	private $externalFolderRequest;


	/**
	 * ExternalAdd constructor.
	 *
	 * @param GlobalStoragesService $globalStoragesService
	 * @param UserStoragesService $userStoragesService
	 * @param ExternalFolderRequest $externalFolderRequest
	 */
	public function __construct(
		IMountManager $mountManager,
		GlobalStoragesService $globalStoragesService,
		UserStoragesService $userStoragesService,
		ExternalFolderRequest $externalFolderRequest
	) {
		parent::__construct();


		$this->mountManager = $mountManager;
		$this->globalStoragesService = $globalStoragesService;
		$this->userStoragesService = $userStoragesService;
		$this->externalFolderRequest = $externalFolderRequest;
	}


	/**
	 *
	 */
	protected function configure() {
		$this->setName('backup:external:add')
			 ->setDescription('Add external filesystem to store your backups')
			 ->addArgument('storage_id', InputArgument::REQUIRED, 'storage_id from oc_storage')
			 ->addArgument('root', InputArgument::REQUIRED, 'folder');
	}


	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @return int
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {


//		$storages = array_map(function (StorageConfig $storageConfig) use ($user) {
//			try {
//				$this->prepareStorageConfig($storageConfig, $user);
//				return $this->constructStorage($storageConfig);
//			} catch (\Exception $e) {
//				// propagate exception into filesystem
//				return new FailedStorage(['exception' => $e]);
//			}
//		}, $storageConfigs);


		$storageId = (int)$input->getArgument('storage_id');
		$root = $input->getArgument('root');

		$folder = new ExternalFolder();
		$folder->setStorageId($storageId);
		$folder->setRoot($root);

		$this->externalFolderRequest->save($folder);

		return 0;
	}

}

