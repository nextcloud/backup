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
use OCA\Backup\Exceptions\RestoringPointLockException;
use OCA\Backup\Exceptions\RestoringPointNotFoundException;
use OCA\Backup\Exceptions\RestoringPointPackException;
use OCA\Backup\Service\OutputService;
use OCA\Backup\Service\PackService;
use OCA\Backup\Service\PointService;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Lock\LockedException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class PointPack
 *
 * @package OCA\Backup\Command
 */
class PointPack extends Base {


	/** @var PointService */
	private $pointService;

	/** @var PackService */
	private $packService;

	/** @var OutputService */
	private $outputService;


	/**
	 * PointPack constructor.
	 *
	 * @param PointService $pointService
	 * @param PackService $packService
	 * @param OutputService $outputService
	 */
	public function __construct(
		PointService $pointService,
		PackService $packService,
		OutputService $outputService
	) {
		parent::__construct();

		$this->pointService = $pointService;
		$this->packService = $packService;
		$this->outputService = $outputService;
	}


	/**
	 *
	 */
	protected function configure() {
		parent::configure();

		$this->setName('backup:point:pack')
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
	 * @throws RestoringPointPackException
	 * @throws RestoringPointNotFoundException
	 * @throws RestoringPointLockException
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$point = $this->pointService->getLocalRestoringPoint($input->getArgument('pointId'));
		$this->pointService->initBaseFolder($point);

		if ($input->getOption('generate-log')) {
			try {
				$this->outputService->openFile($point, 'occ backup:point:pack');
			} catch (NotPermittedException | LockedException $e) {
			}
		}

		$this->outputService->setOutput($output);
		$this->packService->packPoint($point, true);

		return 0;
	}
}
