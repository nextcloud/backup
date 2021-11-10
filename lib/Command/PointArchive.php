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

use ArtificialOwl\MySmallPhpTools\Exceptions\SignatoryException;
use OC\Core\Command\Base;
use OCA\Backup\Exceptions\ExternalFolderNotFoundException;
use OCA\Backup\Exceptions\MetadataException;
use OCA\Backup\Exceptions\RemoteInstanceException;
use OCA\Backup\Exceptions\RemoteInstanceNotFoundException;
use OCA\Backup\Exceptions\RemoteResourceNotFoundException;
use OCA\Backup\Exceptions\RestoringChunkPartNotFoundException;
use OCA\Backup\Exceptions\RestoringPointException;
use OCA\Backup\Exceptions\RestoringPointNotFoundException;
use OCA\Backup\Exceptions\RestoringPointPackException;
use OCA\Backup\Service\OccService;
use OCA\Backup\Service\OutputService;
use OCP\Files\GenericFileException;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class PointArchive
 *
 * @package OCA\Backup\Command
 */
class PointArchive extends Base {


	/** @var OccService */
	private $occService;

	/** @var OutputService */
	private $outputService;


	/**
	 * PointArchive constructor.
	 *
	 * @param OccService $occService
	 * @param OutputService $outputService
	 */
	public function __construct(
		OccService $occService,
		OutputService $outputService
	) {
		parent::__construct();

		$this->occService = $occService;
		$this->outputService = $outputService;
	}


	/**
	 *
	 */
	protected function configure() {
		parent::configure();

		$this->setName('backup:point:archive')
			 ->setDescription('Archive a restoring point')
			 ->addArgument('pointId', InputArgument::REQUIRED, 'id of the restoring point to comment')
			 ->addOption('remote', '', InputOption::VALUE_REQUIRED, 'address of the remote instance')
			 ->addOption('external', '', InputOption::VALUE_REQUIRED, 'id of the external folder')
			 ->addOption('all-storages', '', InputOption::VALUE_NONE, 'duplicate action on all storages');
	}


	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @return int
	 * @throws MetadataException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws RestoringPointNotFoundException
	 * @throws SignatoryException
	 * @throws ExternalFolderNotFoundException
	 * @throws RemoteInstanceException
	 * @throws RemoteInstanceNotFoundException
	 * @throws RemoteResourceNotFoundException
	 * @throws RestoringChunkPartNotFoundException
	 * @throws RestoringPointException
	 * @throws RestoringPointPackException
	 * @throws GenericFileException
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$this->outputService->setOutput($output);
		$point = $this->occService->getRestoringPointBasedOnParams($input);

		$point->setInstance()
			  ->setArchive(true);

		$this->occService->updatePointBasedOnParams($point, $input);

		return 0;
	}
}
