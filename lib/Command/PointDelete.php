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
use OCA\Backup\Exceptions\RemoteResourceNotFoundException;
use OCA\Backup\Exceptions\RestoringChunkPartNotFoundException;
use OCA\Backup\Exceptions\RestoringPointException;
use OCA\Backup\Exceptions\RestoringPointPackException;
use OCA\Backup\Service\ExternalFolderService;
use OCA\Backup\Service\PointService;
use OCA\Backup\Service\RemoteService;
use OCP\Files\GenericFileException;
use OCP\Files\InvalidPathException;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class PointDelete
 *
 * @package OCA\Backup\Command
 */
class PointDelete extends Base {


	/** @var PointService */
	private $pointService;

	/** @var RemoteService */
	private $remoteService;

	/** @var ExternalFolderService */
	private $externalFolderService;


	/**
	 * PointCreate constructor.
	 *
	 * @param PointService $pointService
	 * @param RemoteService $remoteService
	 * @param ExternalFolderService $externalFolderService
	 */
	public function __construct(
		PointService $pointService,
		RemoteService $remoteService,
		ExternalFolderService $externalFolderService
	) {
		parent::__construct();

		$this->pointService = $pointService;
		$this->remoteService = $remoteService;
		$this->externalFolderService = $externalFolderService;
	}


	/**
	 *
	 */
	protected function configure() {
		parent::configure();

		$this->setName('backup:point:delete')
			 ->setDescription('Locally delete a restoring point')
			 ->addArgument('pointId', InputArgument::REQUIRED, 'id of the restoring point to delete')
			 ->addOption(
				 'all-storages', '', InputOption::VALUE_NONE, 'remove restoring point from all storage'
			 )
			 ->addOption(
				 'remote', '', InputOption::VALUE_REQUIRED,
				 'remove a restoring point from a remote instance (or local)', ''
			 )
			 ->addOption(
				 'external', '', InputOption::VALUE_REQUIRED,
				 'remove a restoring point from an external folder', ''
			 );
	}


	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @return int
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws ExternalFolderNotFoundException
	 * @throws RemoteInstanceNotFoundException
	 * @throws RemoteResourceNotFoundException
	 * @throws RestoringChunkPartNotFoundException
	 * @throws RestoringPointException
	 * @throws RestoringPointPackException
	 * @throws GenericFileException
	 * @throws InvalidPathException
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$pointId = $input->getArgument('pointId');

		if ($input->getOption('all-storages')) {
			$this->remoteService->deletePoint($pointId);
			$this->externalFolderService->deletePoint($pointId);
		}

		if ($input->getOption('remote')) {
			$remote = $this->remoteService->getByInstance($input->getOption('remote'));
			$this->remoteService->deletePointRemote($remote, $pointId);

			$output->writeln('Restoring point deleted');

			return 0;
		}

		if ($input->getOption('external')) {
			$external = $this->externalFolderService->getByStorageId((int)$input->getOption('external'));
			$this->externalFolderService->deletePointExternal($external, $pointId);

			$output->writeln('Restoring point deleted');

			return 0;
		}

		$point = $this->pointService->getLocalRestoringPoint($pointId);
		$this->pointService->delete($point);
		$output->writeln('Restoring point deleted');

		return 0;
	}
}
