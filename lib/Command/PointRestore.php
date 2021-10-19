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
use Exception;
use OC\Core\Command\Base;
use OCA\Backup\Exceptions\ArchiveCreateException;
use OCA\Backup\Exceptions\ArchiveFileNotFoundException;
use OCA\Backup\Exceptions\ArchiveNotFoundException;
use OCA\Backup\Exceptions\RestoreChunkException;
use OCA\Backup\Exceptions\RestoringChunkNotFoundException;
use OCA\Backup\Exceptions\RestoringDataNotFoundException;
use OCA\Backup\Exceptions\RestoringPointNotFoundException;
use OCA\Backup\Exceptions\SqlDumpException;
use OCA\Backup\Exceptions\SqlImportException;
use OCA\Backup\Model\ChangedFile;
use OCA\Backup\Model\RestoringData;
use OCA\Backup\Model\RestoringHealth;
use OCA\Backup\Model\RestoringPoint;
use OCA\Backup\Service\ActivityService;
use OCA\Backup\Service\ChunkService;
use OCA\Backup\Service\ConfigService;
use OCA\Backup\Service\FilesService;
use OCA\Backup\Service\OutputService;
use OCA\Backup\Service\PointService;
use OCA\Backup\Service\RestoreService;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * Class PointRestore
 *
 * @package OCA\Backup\Command
 */
class PointRestore extends Base {
	use TStringTools;
	use TArrayTools;


	/** @var PointService */
	private $pointService;

	/** @var ChunkService */
	private $chunkService;

	/** @var FilesService */
	private $filesService;

	/** @var RestoreService */
	private $restoreService;

	/** @var ActivityService */
	private $activityService;

	/** @var ConfigService */
	private $configService;

	/** @var OutputService */
	private $outputService;


	/** @var OutputInterface */
	private $output;

	/** @var InputInterface */
	private $input;


	/**
	 * PointRestore constructor.
	 *
	 * @param PointService $pointService
	 * @param ChunkService $chunkService
	 * @param FilesService $filesService
	 * @param RestoreService $restoreService
	 * @param ActivityService $activityService
	 * @param ConfigService $configService
	 * @param OutputService $outputService
	 */
	public function __construct(
		PointService $pointService,
		ChunkService $chunkService,
		FilesService $filesService,
		RestoreService $restoreService,
		ActivityService $activityService,
		ConfigService $configService,
		OutputService $outputService
	) {
		parent::__construct();

		$this->pointService = $pointService;
		$this->chunkService = $chunkService;
		$this->filesService = $filesService;
		$this->restoreService = $restoreService;
		$this->activityService = $activityService;
		$this->configService = $configService;
		$this->outputService = $outputService;
	}


	/**
	 *
	 */
	protected function configure() {
		$this->setName('backup:point:restore')
			 ->setDescription('Restore a restoring point')
			 ->addArgument('pointId', InputArgument::REQUIRED, 'Id of the restoring point')
			 ->addOption('file', '', InputOption::VALUE_REQUIRED, 'restore only a specific file')
			 ->addOption('chunk', '', InputOption::VALUE_REQUIRED, 'location of the file')
			 ->addOption('data', '', InputOption::VALUE_REQUIRED, 'location of the file');
	}


	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @return int
	 * @throws ArchiveCreateException
	 * @throws ArchiveNotFoundException
	 * @throws RestoringChunkNotFoundException
	 * @throws ArchiveFileNotFoundException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws RestoringPointNotFoundException
	 * @throws RestoringDataNotFoundException
	 * @throws SqlDumpException
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$this->output = $output;
		$this->input = $input;

		$point = $this->pointService->getLocalRestoringPoint($input->getArgument('pointId'));

		if ($point->isStatus(RestoringPoint::STATUS_PACKED)) {
			throw new RestoringPointNotFoundException('the restoring point is packed, please unpack first');
		}
		$file = $input->getOption('file');
		$data = $input->getOption('data');
		$chunk = $input->getOption('chunk');

		if (!is_null($file) || !is_null($chunk)) {
			$this->restoreUniqueFile($point, $file, $data, $chunk);

			return 0;
		}

		$output->writeln('Restoring Point: <info>' . $point->getId() . '</info>');
		$output->writeln('Date: <info>' . date('Y-m-d H:i:s', $point->getDate()) . '</info>');

		$output->write('Checking Health status: ');
		$this->pointService->generateHealth($point);

		$output->writeln($this->outputService->displayHealth($point->getHealth()));
		$output->writeln('');

		$healthStatus = $point->getHealth()->getStatus();
		if ($healthStatus !== RestoringHealth::STATUS_OK) {
			$output->writeln('Some files from your restoring point might not be available');
			$output->writeln('You can run ./occ backup:point:details for more details on the affected files');
			$output->writeln('continue ? (not available yet)');
			$output->writeln('');

			return 0;
		}


		$output->writeln(
			'<error>WARNING! You are about to initiate the complete restoration of your instance!</error>'
		);

		try {
			$output->writeln(
				'Your instance will come back to a previous state from '
				. $this->getDateDiff($point->getDate(), time()) . ' ago.'
			);
		} catch (Exception $e) {
		}

		$output->writeln('');
		$question = new ConfirmationQuestion(
			'<comment>Do you really want to continue this operation ?</comment> (y/N) ',
			false,
			'/^(y|Y)/i'
		);

		$helper = $this->getHelper('question');
		if (!$helper->ask($input, $output, $question)) {
			$output->writeln('aborted.');

			return 0;
		}

		$this->configService->maintenanceMode(true);
		$this->restorePointComplete($point);
		$this->configService->maintenanceMode(false);

		$this->activityService->newActivity(
			ActivityService::RESTORE,
			[
				'id' => $point->getId(),
				'date' => $point->getDate(),
				'rewind' => time() - $point->getDate()
			]
		);

		return 0;
	}


	/**
	 * @param RestoringPoint $point
	 *
	 * @throws SqlDumpException
	 */
	public function restorePointComplete(RestoringPoint $point): void {
		$this->pointService->loadSqlDump();
		foreach ($point->getRestoringData() as $data) {
			$this->output->writeln('');
			$root = $data->getAbsolutePath();
			$this->output->writeln('- Found data pack: <info>' . $data->getName() . '</info>');

			if ($data->getType() === RestoringData::INTERNAL_DATA) {
				$this->output->writeln('  will be ignored');
				continue;
			}

			if ($data->getType() === RestoringData::SQL_DUMP) {
				$this->output->writeln('  will be imported in your current database');

				try {
					$this->importSqlDump($point, $data);
					$this->output->writeln('<info>ok</info>');
				} catch (SqlImportException $e) {
					$this->output->writeln('<error>' . $e->getMessage() . '</error>');
				}
				continue;
			}

			$this->output->writeln('  will be extracted in ' . $root);

			foreach ($data->getChunks() as $chunk) {
				$this->output->write(
					'   > Chunk: ' . $chunk->getPath() . $chunk->getFilename() . ' (' . $chunk->getCount()
					. ' files) '
				);

				try {
					$this->chunkService->restoreChunk($point, $chunk, $root);
					$this->output->writeln('<info>ok</info>');
				} catch (
				ArchiveCreateException
				| ArchiveNotFoundException
				| NotFoundException
				| NotPermittedException
				| RestoreChunkException $e) {
					$this->output->writeln('<error>' . $e->getMessage() . '</error>');
				}
			}
		}

		$this->restoreService->finalizeFullRestore();
	}


	/**
	 * @param RestoringPoint $point
	 * @param RestoringData $data
	 *
	 * @throws SqlImportException
	 * @throws SqlDumpException
	 */
	private function importSqlDump(RestoringPoint $point, RestoringData $data): void {
		$chunks = $data->getChunks();
		if (sizeof($chunks) !== 1) {
			throw new SqlImportException('sql dump contains no chunks');
		}

		$chunk = $chunks[0];
		try {
			$read = $this->chunkService->getStreamFromChunk(
				$point,
				$chunk,
				PointService::SQL_DUMP_FILE
			);
		} catch (ArchiveCreateException
		| ArchiveNotFoundException
		| RestoreChunkException
		| NotFoundException
		| NotPermittedException $e) {
			throw new SqlImportException($e->getMessage());
		}

//		$config = $this->extractDatabaseConfig();
		$sqlDump = $this->pointService->getSqlDump();
		$sqlDump->import($this->pointService->getSqlData(), $read);
	}


	/**
	 * @param RestoringPoint $point
	 * @param string|null $filename
	 * @param string|null $dataName
	 * @param string|null $chunkName
	 *
	 * @throws ArchiveCreateException
	 * @throws ArchiveNotFoundException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws RestoringChunkNotFoundException
	 * @throws ArchiveFileNotFoundException
	 * @throws RestoringDataNotFoundException
	 */
	private function restoreUniqueFile(
		RestoringPoint $point,
		?string $filename,
		?string $dataName,
		?string $chunkName
	): void {
		if (is_null($filename)) {
			throw new InvalidOptionException('must specify --file option');
		}

		if (is_null($chunkName) && is_null($dataName)) {
			throw new InvalidOptionException('must specify --chunk or --data option');
		}

		$this->pointService->initBaseFolder($point);
		if (!is_null($chunkName)) {
			if (is_null($dataName)) {
				$data = $this->chunkService->getDataWithChunk($point, $chunkName);
			} else {
				$data = $this->chunkService->getDataFromRP($point, $dataName);
			}

			$chunk = $this->chunkService->getChunkFromRP($point, $chunkName, $data->getName());
			$file = $this->chunkService->getArchiveFileFromChunk($point, $chunk, $filename);
		} else {
			$data = $this->chunkService->getDataFromRP($point, $dataName);
			$file = $this->chunkService->getArchiveFileFromData($point, $data, $filename);
		}

		$root = $data->getAbsolutePath();
		$chunk = $file->getRestoringChunk();
		$this->output->write(
			'   > restoring ' . $file->getName() . ' (' . $this->humanReadable($file->getFilesize()) .
			') from <info>' . $chunk->getPath() . $chunk->getName() . '</info>' .
			' (rewind: ' . $this->getDateDiff($point->getDate(), time()) . '): '
		);

		// TODO: display $root and add a confirmation step

		try {
			$this->chunkService->restoreUniqueFile($point, $chunk, $root, $file->getName());
			$this->output->writeln('<info>ok</info>');

			$this->activityService->newActivity(
				ActivityService::RESTORE_FILE,
				[
					'id' => $point->getId(),
					'file' => $file->getName(),
					'date' => $point->getDate(),
					'rewind' => time() - $point->getDate()
				]
			);

			// include restored file in next incremental backup
			$changedFile = new ChangedFile($file->getName());
			$this->filesService->changedFile($changedFile);

			// TODO: files:scan file ?
		} catch (ArchiveCreateException
		| ArchiveNotFoundException
		| NotFoundException
		| NotPermittedException
		| RestoreChunkException $e) {
			$this->output->writeln('<error>' . $e->getMessage() . '</error>');
		}
	}
}
