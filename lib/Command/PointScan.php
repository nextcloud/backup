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

use ArtificialOwl\MySmallPhpTools\Traits\TStringTools;
use OC\Core\Command\Base;
use OCA\Backup\Db\PointRequest;
use OCA\Backup\Exceptions\ExternalFolderNotFoundException;
use OCA\Backup\Service\ChunkService;
use OCA\Backup\Service\OutputService;
use OCA\Backup\Service\PointService;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class PointScan
 *
 * @package OCA\Backup\Command
 */
class PointScan extends Base {
	use TStringTools;


	/** @var PointRequest */
	private $pointRequest;

	/** @var PointService */
	private $pointService;

	/** @var ChunkService */
	private $chunkService;

	/** @var OutputService */
	private $outputService;


	/** @var OutputInterface */
	private $output;

	/** @var InputInterface */
	private $input;


	/**
	 * PointScan constructor.
	 *
	 * @param PointRequest $pointRequest
	 * @param PointService $pointService
	 * @param ChunkService $chunkService
	 */
	public function __construct(
		PointRequest $pointRequest,
		PointService $pointService,
		ChunkService $chunkService
	) {
		parent::__construct();

		$this->pointRequest = $pointRequest;
		$this->chunkService = $chunkService;
		$this->pointService = $pointService;
	}


	/**
	 *
	 */
	protected function configure() {
		$this->setName('backup:point:scan')
			 ->setDescription(
				 'Scan a folder containing the data of a restoring point to add it in the list of available restoring point'
			 )
			 ->addOption('owner', '', InputOption::VALUE_REQUIRED, 'owner of the metadata file')
			 ->addOption('file', '', InputOption::VALUE_REQUIRED, 'file_id of the metadata file');
	}


	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @return int
	 * @throws ExternalFolderNotFoundException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$fileId = (int)$input->getOption('file');

		if ($fileId > 0) {
			$owner = $input->getOption('owner');
			if (!$owner) {
				throw new InvalidArgumentException('use --owner to specify the owner of the file');
			}
			$points = [$this->pointService->generatePointFromFolder($fileId, $owner)];
		} else {
			$points = $this->pointService->scanFoldersFromAppData();
		}

		foreach ($points as $point) {
			$output->writeln($point->getId() . ' added to the list');
		}

		return 0;
	}
}
