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

use Exception;
use OC\Core\Command\Base;
use OCA\Backup\Exceptions\RestoringDataNotFoundException;
use OCA\Backup\Service\ChunkService;
use OCA\Backup\Service\PointService;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class NodeHistory
 *
 * @package OCA\Backup\Command
 */
class FileHistory extends Base {


	/** @var PointService */
	private $pointService;

	/** @var ChunkService */
	private $chunkService;


	/**
	 * PointHistory constructor.
	 *
	 * @param PointService $pointService
	 * @param ChunkService $chunkService
	 */
	public function __construct(PointService $pointService, ChunkService $chunkService) {
		parent::__construct();

		$this->pointService = $pointService;
		$this->chunkService = $chunkService;
	}


	/**
	 *
	 */
	protected function configure() {
		parent::configure();

		$this->setName('backup:file:history')
			 ->setDescription('Get the history of a file')
			 ->addArgument('data', InputArgument::REQUIRED, 'name of the data pack')
			 ->addArgument('filename', InputArgument::REQUIRED, 'full path of the file')
			 ->addOption('since', '', InputOption::VALUE_REQUIRED, 'start at a specific date')
			 ->addOption('until', '', InputOption::VALUE_REQUIRED, 'end at a specific date');
	}


	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @return int
	 * @throws RestoringDataNotFoundException
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$dataName = $input->getArgument('data');
		$filename = $input->getArgument('filename');

		$output = new ConsoleOutput();
		$output = $output->section();
		$table = new Table($output);
		$table->setHeaders(['Date', 'Restoring Point', 'Data', 'Chunk', 'Filesize']);
		$table->render();

		$since = ($input->getOption('since')) ? (int)strtotime($input->getOption('since')) : 0;
		$until = ($input->getOption('until')) ? (int)strtotime($input->getOption('until')) : 0;
		$points = $this->pointService->getLocalRestoringPoints($since, $until);

		foreach ($points as $point) {
			try {
				$this->pointService->initBaseFolder($point);
			} catch (NotFoundException | NotPermittedException $e) {
				continue;
			}

			$data = $this->chunkService->getDataFromRP($point, $dataName);

			foreach ($data->getChunks() as $chunk) {
				try {
					$file = $this->chunkService->getArchiveFileFromChunk($point, $chunk, $filename);
					$table->appendRow(
						[
							date('Y-m-d H:i:s', $point->getDate()),
							$point->getId(),
							$data->getName(),
							$chunk->getName(),
							$file->getFileSize()
						]
					);
				} catch (Exception $e) {
				}
			}
		}

//		$output->writeln('Restoring Point ID: <info>' . $point->getId() . '</info>');

		return 0;
	}
}
