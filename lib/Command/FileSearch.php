<?php

declare(strict_types=1);


/**
 * Nextcloud - Backup
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


use ArtificialOwl\MySmallPhpTools\Traits\TArrayTools;
use ArtificialOwl\MySmallPhpTools\Traits\TStringTools;
use OC\Core\Command\Base;
use OCA\Backup\Exceptions\ArchiveCreateException;
use OCA\Backup\Exceptions\ArchiveNotFoundException;
use OCA\Backup\Exceptions\RestoringPointNotFoundException;
use OCA\Backup\Service\ChunkService;
use OCA\Backup\Service\PointService;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


/**
 * Class NodeSearch
 *
 * @package OCA\Backup\Command
 */
class FileSearch extends Base {


	use TArrayTools;
	use TStringTools;


	/** @var PointService */
	private $pointService;

	/** @var ChunkService */
	private $chunkService;


	/**
	 * NodeSearch constructor.
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

		$this->setName('backup:file:search')
			 ->setDescription('Search a specific file in your restoring points')
			 ->addArgument('search', InputArgument::REQUIRED, 'path/name to search')
			 ->addOption(
				 'point', '', InputOption::VALUE_REQUIRED, 'Id of a restoring point for targeted search'
			 )
			 ->addOption('since', '', InputOption::VALUE_REQUIRED, 'search in a specific timeline')
			 ->addOption('until', '', InputOption::VALUE_REQUIRED, 'search in a specific timeline');
	}


	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @return int
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws RestoringPointNotFoundException
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$search = $input->getArgument('search');
		$since = ($input->getOption('since')) ? (int)strtotime($input->getOption('since')) : 0;
		$until = ($input->getOption('until')) ? (int)strtotime($input->getOption('until')) : 0;

		if ($input->getOption('point')) {
			$points = [$this->pointService->getLocalRestoringPoint($input->getOption('point'))];
		} else {
			$points = $this->pointService->getRPLocal($since, $until);
		}

		foreach ($points as $point) {
			$this->pointService->initBaseFolder($point);

			$output->writeln('');
			$output->writeln(
				'- searching in <info>' . $point->getId() . '</info> ('
				. date('Y-m-d H:i:s', $point->getDate()) . ')'
			);
			$empty = true;

			foreach ($point->getRestoringData() as $data) {
				$chunks = $data->getChunks();
				$progressBar = new ProgressBar($output, sizeof($chunks));
				$progressBar->start();

				foreach ($chunks as $chunk) {
					$progressBar->advance();
					try {
						$files = $this->chunkService->searchFileInChunk($point, $chunk, $search);
						if (empty($files)) {
							continue;
						}

						$empty = false;
						foreach ($files as $file) {
							$output->writeln(
								'   found <info>' . $file->getName() . '</info> ('
								. $this->humanReadable($file->getFilesize()) . ') in <info>'
								. $data->getName() . '</info>/<info>' . $chunk->getName() . '</info>'
							);
						}
					} catch
					(ArchiveCreateException
					| ArchiveNotFoundException
					| NotFoundException
					| NotPermittedException $e) {
					}
				}

				$progressBar->finish();
			}

			if ($empty) {
				$output->writeln('   <comment>no result</comment>');
			} else {
				$output->writeln('');
			}

			$this->pointService->initBaseFolder($point);
		}
		$output->writeln('');

		return 0;
	}

}

