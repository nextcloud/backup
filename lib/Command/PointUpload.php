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
use OCA\Backup\Exceptions\ExternalFolderNotFoundException;
use OCA\Backup\Exceptions\RemoteInstanceNotFoundException;
use OCA\Backup\Exceptions\RestoringPointLockException;
use OCA\Backup\Exceptions\RestoringPointNotFoundException;
use OCA\Backup\Exceptions\RestoringPointPackException;
use OCA\Backup\Service\OutputService;
use OCA\Backup\Service\PointService;
use OCA\Backup\Service\RemoteService;
use OCA\Backup\Service\UploadService;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Lock\LockedException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class PointUpload
 *
 * @package OCA\Backup\Command
 */
class PointUpload extends Base {


	/** @var PointService */
	private $pointService;

	/** @var RemoteService */
	private $remoteService;

	/** @var UploadService */
	private $uploadService;

	/** @var OutputService */
	private $outputService;


	/**
	 * PointUpload constructor.
	 *
	 * @param PointService $pointService
	 * @param RemoteService $remoteService
	 * @param UploadService $uploadService
	 * @param OutputService $outputService
	 */
	public function __construct(
		PointService $pointService,
		RemoteService $remoteService,
		UploadService $uploadService,
		OutputService $outputService
	) {
		parent::__construct();

		$this->pointService = $pointService;
		$this->remoteService = $remoteService;
		$this->uploadService = $uploadService;
		$this->outputService = $outputService;
	}


	/**
	 *
	 */
	protected function configure() {
		$this->setName('backup:point:upload')
			 ->setDescription('Upload a local restoring point on others instances')
			 ->addArgument('point', InputArgument::REQUIRED, 'Id of the restoring point')
			 ->addOption('remote', '', InputOption::VALUE_REQUIRED, 'address of the remote instance', '')
			 ->addOption('external', '', InputOption::VALUE_REQUIRED, 'id of the external folder', '')
			 ->addOption('generate-log', '', InputOption::VALUE_NONE, 'generate a log file');
	}


	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @return int
	 * @throws ExternalFolderNotFoundException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws RemoteInstanceNotFoundException
	 * @throws RestoringPointNotFoundException
	 * @throws RestoringPointPackException
	 * @throws RestoringPointLockException
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$point = $this->pointService->getLocalRestoringPoint($input->getArgument('point'));

		if ($input->getOption('generate-log')) {
			try {
				$this->pointService->initBaseFolder($point);
				$this->outputService->openFile($point, 'occ backup:point:upload');
			} catch (NotPermittedException | LockedException $e) {
			}
		}

		$this->outputService->setOutput($output);
		if ($input->getOption('external')) {
			$this->uploadService->initUpload($point);
			$this->uploadService->uploadToExternalFolder($point, (int)$input->getOption('external'));
			$this->uploadService->closeUpload($point);

			return 0;
		}

		if ($input->getOption('remote')) {
			$this->uploadService->initUpload($point);
			$this->uploadService->uploadToRemoteInstances($point, $input->getOption('remote'));
			$this->uploadService->closeUpload($point);

			return 0;
		}

		$this->uploadService->uploadPoint($point);

		return 0;
	}
}
