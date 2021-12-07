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
use OCA\Backup\Exceptions\RestoringPointNotFoundException;
use OCA\Backup\Service\OutputService;
use OCA\Backup\Service\PackService;
use OCA\Backup\Service\PointService;
use OCA\Backup\Service\RemoteStreamService;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Lock\LockedException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Class PointUnpack
 *
 * @package OCA\Backup\Command
 */
class PointUnpack extends Base {


	/** @var PointService */
	private $pointService;

	/** @var PackService */
	private $packService;

	/** @var RemoteStreamService */
	private $remoteStreamService;

	/** @var OutputService */
	private $outputService;


	/**
	 * PointUnpack constructor.
	 *
	 * @param PointService $pointService
	 * @param PackService $packService
	 * @param RemoteStreamService $remoteStreamService
	 * @param OutputService $outputService
	 */
	public function __construct(
		PointService $pointService,
		PackService $packService,
		RemoteStreamService $remoteStreamService,
		OutputService $outputService
	) {
		parent::__construct();

		$this->pointService = $pointService;
		$this->packService = $packService;
		$this->remoteStreamService = $remoteStreamService;
		$this->outputService = $outputService;
	}


	/**
	 *
	 */
	protected function configure() {
		parent::configure();

		$this->setName('backup:point:unpack')
			 ->setDescription('Increase compression of a restoring point and prepare for upload')
			 ->addArgument('pointId', InputArgument::REQUIRED, 'Id of the restoring point')
			 ->addOption('generate-log', '', InputOption::VALUE_NONE, 'generate a log file');
	}


	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @return int
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws RestoringPointNotFoundException
	 * @throws SignatoryException
	 * @throws Throwable
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$this->outputService->setOutput($output);
		$point = $this->pointService->getLocalRestoringPoint($input->getArgument('pointId'));
		$this->pointService->initBaseFolder($point);

		if ($input->getOption('generate-log')) {
			try {
				$this->outputService->openFile($point, 'occ backup:point:unpack');
			} catch (NotPermittedException | LockedException $e) {
			}
		}

		$this->packService->unpackPoint($point);


		// set Archive flag up after unpack
		$point->setArchive(true);

		$this->remoteStreamService->subSignPoint($point);
		$this->pointService->updateSubInfos($point);

		return 0;
	}
}
