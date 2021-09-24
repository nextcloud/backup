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
use OCA\Backup\Exceptions\ArchiveNotFoundException;
use OCA\Backup\Exceptions\RestoringPointNotFoundException;
use OCA\Backup\Model\RestoringData;
use OCA\Backup\Service\ArchiveService;
use OCA\Backup\Service\PointService;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;


/**
 * Class PointDetails
 *
 * @package OCA\Backup\Command
 */
class PointDetails extends Base {


	use TArrayTools;
	use TStringTools;


	/** @var PointService */
	private $pointService;

	/** @var ArchiveService */
	private $chunkService;


	/**
	 * PointDetails constructor.
	 *
	 * @param PointService $pointService
	 * @param ArchiveService $chunkService
	 */
	public function __construct(PointService $pointService, ArchiveService $chunkService) {
		parent::__construct();

		$this->pointService = $pointService;
		$this->chunkService = $chunkService;
	}


	/**
	 *
	 */
	protected function configure() {
		parent::configure();

		$this->setName('backup:point:details')
			 ->setDescription('Details on a restoring point')
			 ->addArgument('pointId', InputArgument::REQUIRED, 'Id of the restoring point');
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
		$point = $this->pointService->getLocalRestoringPoint($input->getArgument('pointId'));
		$this->pointService->initBaseFolder($point);

		if ($input->getOption('output') === 'json') {
			$this->pointService->generateHealth($point);
			$output->writeln(json_encode($point) . "\n");
			$output->writeln(json_encode($point, JSON_PRETTY_PRINT));

			return 0;
		}

		$output = new ConsoleOutput();
		$output = $output->section();

		$output->writeln('<info>Restoring Point ID</info>: ' . $point->getId());
		$output->writeln('<info>Date</info>: ' . date('Y-m-d H:i:s', $point->getDate()));
		$output->writeln('<info>Version</info>: ' . $point->getNCVersion());
		$output->writeln(
			'<info>Parent</info>: ' . ($point->getParent() === '' ? '(none)' : $point->getParent())
		);

		foreach ($point->getRestoringData() as $data) {
			$type = $this->get((string)$data->getType(), RestoringData::$DEF, (string)$data->getType());

			$output->writeln('');
			$output->writeln('<info>Data</info>: ' . $data->getName());
			$output->writeln('<info>Type</info>: ' . $type);
			if ($data->getAbsolutePath() !== '') {
				$output->writeln('<info>Absolute Path</info>: ' . $data->getAbsolutePath());
			}

			$table = new Table($output);
			$table->setHeaders(['Id', 'Size', 'Count', 'Checksum', 'verified']);
			$table->render();


			foreach ($data->getChunks() as $chunk) {
				try {
					$checked = $this->chunkService->getChecksum($point, $chunk);
				} catch (ArchiveNotFoundException $e) {
					$checked = '<error>missing chunk</error>';
				}

				$color = ($checked === $chunk->getChecksum()) ? 'info' : 'error';
				$checked = '<' . $color . '>' . $checked . '</' . $color . '>';

				$table->appendRow(
					[
						$chunk->getName(),
						$this->humanReadable($chunk->getSize()),
						$chunk->getCount(),
						$chunk->getChecksum(),
						$checked
					]
				);
			}
		}

		return 0;
	}

}

