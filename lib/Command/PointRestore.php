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
use OCA\Backup\Exceptions\RestoreChunkException;
use OCA\Backup\Exceptions\RestoringPointNotFoundException;
use OCA\Backup\Exceptions\SqlImportException;
use OCA\Backup\Model\RestoringData;
use OCA\Backup\Model\RestoringHealth;
use OCA\Backup\Model\RestoringPoint;
use OCA\Backup\Service\ArchiveService;
use OCA\Backup\Service\ConfigService;
use OCA\Backup\Service\OutputService;
use OCA\Backup\Service\PointService;
use OCA\Backup\SqlDump\SqlDumpMySQL;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
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

	/** @var ArchiveService */
	private $archiveService;

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
	 * @param ArchiveService $archiveService
	 * @param ConfigService $configService
	 * @param OutputService $outputService
	 */
	public function __construct(
		PointService $pointService,
		ArchiveService $archiveService,
		ConfigService $configService,
		OutputService $outputService
	) {
		parent::__construct();

		$this->archiveService = $archiveService;
		$this->pointService = $pointService;
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
			 ->addOption(
				 'files', 'f', InputOption::VALUE_REQUIRED, 'restore only a specific file', ''
			 );
	}


	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @return int
	 * @throws RestoringPointNotFoundException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$this->output = $output;
		$this->input = $input;

		$pointId = $input->getArgument('pointId');
		$file = $input->getOption('files');

		$point = $this->pointService->getRestoringPoint($pointId);
		$output->writeln('Restoring Point: <info>' . $point->getId() . '</info>');
		$output->writeln('Date: <info>' . date("Y-m-d H:i:s", $point->getDate()) . '</info>');

		$output->write('Checking Health status: ');
		$this->pointService->generateHealth($point);

		$output->writeln($this->outputService->displayHealth($point));
		$output->writeln('');

		$healthStatus = $point->getHealth()->getStatus();
		if ($healthStatus !== RestoringHealth::STATUS_OK) {
			$output->writeln('Some files from your restoring point might not be available');
			$output->writeln('You can run ./occ backup:point:health for more details on the affected files');
			$output->writeln('continue ? (not available yet)');
			$output->writeln('');

			return 0;
		}


		$output->writeln(
			'<error>WARNING! You are about to initiate the complete restoration of your instance!</error>'
		);

		$output->writeln(
			'Your instance will come back to a previous state from '
			. $this->getDateDiff($point->getDate(), time()) . ' ago.'
		);
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


		$this->restorePointComplete($point);

		return 0;
	}


	/**
	 * @param RestoringPoint $point
	 */
	public function restorePointComplete(RestoringPoint $point): void {
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
					'   > Chunk: ' . $chunk->getFilename() . ' (' . $chunk->getCount() . ' files) '
				);

				try {
					$this->archiveService->restoreChunk($point, $chunk, $root);
					$this->output->writeln('<info>ok</info>');
				} catch (ArchiveNotFoundException | RestoreChunkException $e) {
					$this->output->writeln('<error>' . $e->getMessage() . '</error>');
				}
			}

		}
	}


	/**
	 * @param RestoringPoint $point
	 * @param RestoringData $data
	 *
	 * @throws SqlImportException
	 */
	private function importSqlDump(RestoringPoint $point, RestoringData $data): void {
		$chunks = $data->getChunks();
		if (sizeof($chunks) !== 1) {
			throw new SqlImportException('sql dump contains no chunks');
		}

		$chunk = $chunks[0];
		try {
			$read = $this->archiveService->getStreamFromChunk(
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

		$config = $this->extractDatabaseConfig();
		$sqlDump = new SqlDumpMySQL();
		$sqlDump->import($config, $read);
	}


	/**
	 * @return array
	 */
	private function extractDatabaseConfig(): array {
		if ($this->configService->getSystemValue('dbtype') !== 'mysql') {
			return [];
		}

		return [
			'dbname' => $this->configService->getSystemValue('dbname'),
			'dbhost' => $this->configService->getSystemValue('dbhost'),
			'dbport' => $this->configService->getSystemValue('dbport'),
			'dbuser' => $this->configService->getSystemValue('dbuser'),
			'dbpassword' => $this->configService->getSystemValue('dbpassword')
		];

//		$CONFIG = [];
//		require($config);
//
//		$this->mustContains(['dbtype'], $CONFIG);
//		if ($CONFIG['dbtype'] === 'mysql') {
//			$this->mustContains(['dbname', 'dbport', 'dbhost', 'dbuser', 'dbpassword'], $CONFIG);
//			$data = [
//				'dbname' => $CONFIG['dbname'],
//				'dbhost' => $CONFIG['dbhost'],
//				'dbport' => $CONFIG['dbport'],
//				'dbuser' => $CONFIG['dbuser'],
//				'dbpassword' => $CONFIG['dbpassword']
//			];
//
//			return $data;
//		}
//
//		return [];
	}

}
