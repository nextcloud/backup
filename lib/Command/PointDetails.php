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

use ArtificialOwl\MySmallPhpTools\Traits\TArrayTools;
use ArtificialOwl\MySmallPhpTools\Traits\TStringTools;
use OC\Core\Command\Base;
use OCA\Backup\Exceptions\ArchiveNotFoundException;
use OCA\Backup\Exceptions\ExternalFolderNotFoundException;
use OCA\Backup\Exceptions\RemoteInstanceException;
use OCA\Backup\Exceptions\RemoteInstanceNotFoundException;
use OCA\Backup\Exceptions\RemoteResourceNotFoundException;
use OCA\Backup\Exceptions\RestoringChunkPartNotFoundException;
use OCA\Backup\Exceptions\RestoringPointException;
use OCA\Backup\Exceptions\RestoringPointNotFoundException;
use OCA\Backup\Exceptions\RestoringPointPackException;
use OCA\Backup\Model\ChunkPartHealth;
use OCA\Backup\Model\RestoringChunk;
use OCA\Backup\Model\RestoringData;
use OCA\Backup\Model\RestoringHealth;
use OCA\Backup\Model\RestoringPoint;
use OCA\Backup\Service\ChunkService;
use OCA\Backup\Service\ExternalFolderService;
use OCA\Backup\Service\PackService;
use OCA\Backup\Service\PointService;
use OCA\Backup\Service\RemoteService;
use OCP\Files\GenericFileException;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
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


	/** @var RemoteService */
	private $remoteService;

	/** @var PointService */
	private $pointService;

	/** @var ExternalFolderService */
	private $externalFolderService;

	/** @var ChunkService */
	private $chunkService;

	/** @var PackService */
	private $packService;


	/**
	 * PointDetails constructor.
	 *
	 * @param RemoteService $remoteService
	 * @param PointService $pointService
	 * @param ExternalFolderService $externalFolderService
	 * @param ChunkService $chunkService
	 * @param PackService $packService
	 */
	public function __construct(
		RemoteService         $remoteService,
		PointService          $pointService,
		ExternalFolderService $externalFolderService,
		ChunkService          $chunkService,
		PackService           $packService
	) {
		parent::__construct();

		$this->remoteService = $remoteService;
		$this->pointService = $pointService;
		$this->externalFolderService = $externalFolderService;
		$this->chunkService = $chunkService;
		$this->packService = $packService;
	}


	/**
	 *
	 */
	protected function configure() {
		parent::configure();

		$this->setName('backup:point:details')
			 ->setDescription('Details on a restoring point')
			 ->addArgument('pointId', InputArgument::REQUIRED, 'Id of the restoring point')
			 ->addOption('remote', '', InputOption::VALUE_REQUIRED, 'address of the remote instance')
			 ->addOption('external', '', InputOption::VALUE_REQUIRED, 'id of the external folder');
	}


	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @return int
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws RemoteInstanceException
	 * @throws RemoteInstanceNotFoundException
	 * @throws RemoteResourceNotFoundException
	 * @throws RestoringPointNotFoundException
	 * @throws ExternalFolderNotFoundException
	 * @throws RestoringChunkPartNotFoundException
	 * @throws RestoringPointException
	 * @throws RestoringPointPackException
	 * @throws GenericFileException
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$pointId = $input->getArgument('pointId');
		$remote = $input->getOption('remote');
		$external = $input->getOption('external');

		if ($remote) {
			$point = $this->remoteService->getRestoringPoint($remote, $pointId, true);
		} elseif ($external) {
			$externalFolder = $this->externalFolderService->getByStorageId((int)$external);
			$point = $this->externalFolderService->getRestoringPoint($externalFolder, $pointId, true);
		} else {
			$point = $this->pointService->getLocalRestoringPoint($pointId);
			$this->pointService->generateHealth($point, true);
//			$this->pointService->initBaseFolder($point);
		}

		if ($input->getOption('output') === 'json') {
//			$output->writeln(json_encode($point) . "\n");
			$output->writeln(json_encode($point, JSON_PRETTY_PRINT)) . "\n";

			return 0;
		}

		$output = new ConsoleOutput();
		$output = $output->section();

		$output->writeln('<info>Restoring Point ID</info>: ' . $point->getId());
		$output->writeln('<info>Date</info>: ' . date('Y-m-d H:i:s', $point->getDate()));
		$output->writeln('<info>Version</info>: ' . $point->getNCVersion());
		$output->writeln('<info>Maintenance Duration</info>: ' . $this->getDateDiff($point->getDuration()));
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
			$table->setHeaders(['Chunk Id', 'Size', 'Count', 'Part Id', 'Checksum', 'Algorithm', '']);
			$table->render();

			foreach ($data->getChunks() as $chunk) {
				if ($chunk->hasParts()) {
					$this->displayDetailsPacked($table, $point, $chunk);
					continue;
				}

				try {
					$checked = $this->chunkService->getChecksum($point, $chunk);
				} catch (ArchiveNotFoundException $e) {
					$checked = '<error>missing chunk</error>';
				}

				$checked =
					($checked === $chunk->getChecksum()) ? '<info>ok</info>' : '<error>checksum</error>';

				$table->appendRow(
					[
						$chunk->getName(),
						$this->humanReadable($chunk->getSize()),
						$chunk->getCount(),
						'not packed',
						$chunk->getChecksum(),
						'',
						$checked
					]
				);
			}
		}

		$source = '';
		if ($remote) {
			$source = ' on <info>' . $remote . '</info>';
		} elseif ($external) {
			$source = ' at <info>' . $externalFolder->getStorageId() . '</info>:<info>' .
					  $externalFolder->getRoot() . '</info>';
		}
		$output->writeln('');

		$color = 'info';
		if ($point->getHealth()->getStatus() !== RestoringHealth::STATUS_OK) {
			$color = 'error';
		}

		$output->writeln(
			'Status of the restoring point ' . $source . ': <' . $color . '>' .
			RestoringHealth::$DEF[$point->getHealth()->getStatus()] . '</' . $color . '>'
		);


		return 0;
	}


	/**
	 * @param Table $table
	 * @param RestoringPoint $point
	 * @param RestoringChunk $chunk
	 */
	private function displayDetailsPacked(
		Table          $table,
		RestoringPoint $point,
		RestoringChunk $chunk
	): void {
		$fresh = true;
		$health = $point->getHealth();
		foreach ($chunk->getParts() as $part) {
			$partHealth = $health->getPart($chunk->getName(), $part->getName());
			$status = ChunkPartHealth::$DEF_STATUS[$partHealth->getStatus()];
//
//			try {
//				$checked = $this->packService->getChecksum($point, $chunk, $part);
//			} catch (ArchiveNotFoundException $e) {
//				$checked = '<error>missing chunk</error>';
//			}

			$color = ($partHealth->getStatus() === ChunkPartHealth::STATUS_OK) ? 'info' : 'error';
			$status = '<' . $color . '>' . $status . '</' . $color . '>';

			$table->appendRow(
				[
					($fresh) ? $chunk->getName() : '',
					($fresh) ? $this->humanReadable($chunk->getSize()) : '',
					($fresh) ? $chunk->getCount() : '',
					$part->getName(),
					$part->getCurrentChecksum(),
					$part->getAlgorithm(),
					$status
				]
			);

			$fresh = false;
		}
	}
}
