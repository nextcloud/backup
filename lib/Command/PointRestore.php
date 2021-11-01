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
use OCA\Backup\Exceptions\RestoringPointNotInitiatedException;
use OCA\Backup\Exceptions\SqlDumpException;
use OCA\Backup\Exceptions\SqlImportException;
use OCA\Backup\ISqlDump;
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
use Symfony\Component\Console\Question\Question;

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
			 ->addOption('force', '', InputOption::VALUE_NONE, 'Force the restoring process')
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
	 * @throws SignatoryException
	 * @throws RestoringPointNotInitiatedException
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$this->output = $output;
		$this->input = $input;

		$point = $this->pointService->getLocalRestoringPoint($input->getArgument('pointId'));
		$force = $input->getOption('force');

		if ($point->isStatus(RestoringPoint::STATUS_PACKED)
			&& !$point->isStatus(RestoringPoint::STATUS_PACKING)) {
			throw new RestoringPointNotFoundException('the restoring point is packed, please unpack first');
		}
		if ($point->isStatus(RestoringPoint::STATUS_PACKING) && !$force) {
			throw new RestoringPointNotFoundException(
				'the restoring point does not seems to be fully unpacked, meaning not all data are available.'
				. "\n" .
				'Finish the unpacking process, or use --force to see how the restoring process goes and hope for the best'
			);
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
		if ($healthStatus !== RestoringHealth::STATUS_OK && !$force) {
			$output->writeln('Some files from your restoring point might not be available');
			$output->writeln('You can run ./occ backup:point:details for more details on the affected files');
			$output->writeln('or use --force to force the restoring process despite this warning');
			$output->writeln('');

			return 0;
		}

		$output->writeln('');
		$output->writeln(
			'<error>WARNING! You are about to initiate the complete restoration of your instance!</error>'
		);
		$output->writeln(
			'<error>All data generated since the creation of the selected backup will be lost...</error>'
		);
		$output->writeln('');

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

		$output->writeln('');
		$output->writeln('> Enabling <info>maintenance mode</info>');
		$this->configService->maintenanceMode(true);
		$this->restorePointComplete($point);

		$this->output->writeln('');
		$this->updateConfig($point);

		$output->writeln('> Finalization of the restoring process');
		$output->writeln('> <info>maintenance mode</info> disabled');
		$this->restoreService->finalizeFullRestore();

//		$this->configService->maintenanceMode(false);

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
	 * @throws RestoringPointNotInitiatedException
	 */
	public function restorePointComplete(RestoringPoint $point): void {
		$this->output->writeln('> Restoring <info>' . $point->getId() . '</info>');

		$this->pointService->loadSqlDump(); // load ISqlDump before rewriting the apps' files
		foreach ($point->getRestoringData() as $data) {
			$this->output->writeln('');
			$this->output->writeln(' > Found data pack: <info>' . $data->getName() . '</info>');

			if ($data->getType() === RestoringData::INTERNAL_DATA) {
				$this->output->writeln('  * ignoring');
				continue;
			}

			if ($data->getType() === RestoringData::FILE_SQL_DUMP) {
				$this->restorePointSqlDump($point, $data);
				continue;
			}

			$this->restorePointData($point, $data);
		}
	}


	/**
	 * @param RestoringPoint $point
	 * @param RestoringData $data
	 *
	 * @throws RestoringPointNotInitiatedException
	 */
	private function restorePointData(RestoringPoint $point, RestoringData $data): void {
		$root = $this->requestDataRoot($data);

		$this->output->writeln('   > extracting data to <info>' . $root . '</info>');
		foreach ($data->getChunks() as $chunk) {
			$this->output->write(
				'   > Chunk: <info>' . $chunk->getFilename()
				. '</info> (' . $chunk->getCount() . ' files) '
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

		$data->setRestoredRoot($root);
	}


	/**
	 * @param RestoringPoint $point
	 * @param RestoringData $data
	 *
	 * @throws SqlDumpException
	 */
	private function restorePointSqlDump(RestoringPoint $point, RestoringData $data): void {
		$sqlParams = $this->requestSqlParams();

		$this->output->write(
			'   > importing sqldump in <info>' . $this->displaySqlParams($sqlParams, true) . '</info>: '
		);
		try {
			$this->importSqlDump($point, $data, $sqlParams);
			$this->output->writeln('<info>ok</info>');
		} catch (SqlImportException $e) {
			$this->output->writeln('<error>' . $e->getMessage() . '</error>');
		}

		$data->setRestoredRoot(json_encode($sqlParams));
	}


	/**
	 * @param RestoringData $data
	 *
	 * @return string
	 */
	private function requestDataRoot(RestoringData $data): string {
		$root = $data->getAbsolutePath();
		while (true) {
			$this->output->writeln('   > will be extracted in <info>' . $root . '</info>');
			$helper = $this->getHelper('question');
			$question = new Question(
				'    - <comment>enter a new absolute path</comment> or press \'<info>enter</info>\' to use this location: ',
				$root
			);
			$question->setAutocompleterValues([$data->getAbsolutePath()]);
			$newRoot = trim($helper->ask($this->input, $this->output, $question));
			$newRoot = rtrim($newRoot, '/') . '/';
			if ($newRoot === $root) {
				break;
			}

			$root = $newRoot;
		}

		return $root;
	}


	/**
	 * @return array
	 */
	private function requestSqlParams(): array {
		$sqlParams = $this->pointService->getSqlParams();

		while (true) {
			$this->output->writeln('   > will be imported in ' . $this->displaySqlParams($sqlParams, true));

			$helper = $this->getHelper('question');
			$question = new ConfirmationQuestion(
				'<comment>    - Do you want to import the dump in another SQL server or database ?</comment> (y/N) ',
				false,
				'/^(y|Y)/i'
			);

			if (!$helper->ask($this->input, $this->output, $question)) {
				return $sqlParams;
			}

			$this->output->writeln('    - current configuration:');
			$this->displaySqlParams($sqlParams);

			$this->output->writeln('    - edit configuration:');
			while (true) {
				$question = new Question('      . Host: ', '');
				$newHost = trim($helper->ask($this->input, $this->output, $question));
				if ($newHost !== '') {
					break;
				}
			}

			$question = new Question('      . Port: ', '');
			$newPort = trim($helper->ask($this->input, $this->output, $question));

			while (true) {
				$question = new Question('      . Database: ', '');
				$newName = trim($helper->ask($this->input, $this->output, $question));
				if ($newName !== '') {
					break;
				}
			}

			while (true) {
				$question = new Question('      . Username: ', '');
				$newUser = trim($helper->ask($this->input, $this->output, $question));
				if ($newUser !== '') {
					break;
				}
			}

			while (true) {
				$question = new Question('      . Password: ', '');
				$question->setHidden(true);
				$newPass = trim($helper->ask($this->input, $this->output, $question));
				if ($newPass !== '') {
					break;
				}
			}

			$newParams = [
				ISqlDump::DB_NAME => $newName,
				ISqlDump::DB_HOST => $newHost,
				ISqlDump::DB_PORT => $newPort,
				ISqlDump::DB_USER => $newUser,
				ISqlDump::DB_PASS => $newPass
			];

			$this->output->writeln('    - new configuration:');
			$this->displaySqlParams($newParams);

			$question = new ConfirmationQuestion(
				'<comment>    - Do you want to use this configuration ?</comment> (y/N) ',
				false,
				'/^(y|Y)/i'
			);

			if ($helper->ask($this->input, $this->output, $question)) {
				return $newParams;
			}
		}
	}


	/**
	 * @param RestoringPoint $point
	 */
	private function updateConfig(RestoringPoint $point): void {
		$this->output->writeln('> Refreshing <info>config.php</info>');

		$sqlParams = [];
		$dataRoot = $configRoot = '';
		foreach ($point->getRestoringData() as $data) {
			if ($data->getType() === RestoringData::ROOT_DATA) {
				$dataRoot = $data->getRestoredRoot();
			}

			if ($data->getType() === RestoringData::FILE_CONFIG) {
				$configRoot = $data->getRestoredRoot();
			}

			if ($data->getType() === RestoringData::FILE_SQL_DUMP) {
				$sqlParams = json_decode($data->getRestoredRoot(), true);
				if (!is_array($sqlParams)) {
					$sqlParams = [];
				}
			}
		}

		$CONFIG = [];
		$configFile = rtrim($configRoot, '/') . '/config.php';
		include $configFile;

		$this->compareConfigDataRoot($CONFIG, $dataRoot);
		$this->compareConfigSqlParams($sqlParams, $CONFIG, ISqlDump::DB_HOST);
		$this->compareConfigSqlParams($sqlParams, $CONFIG, ISqlDump::DB_PORT);
		$this->compareConfigSqlParams($sqlParams, $CONFIG, ISqlDump::DB_NAME);
		$this->compareConfigSqlParams($sqlParams, $CONFIG, ISqlDump::DB_USER);
		$this->compareConfigSqlParams($sqlParams, $CONFIG, ISqlDump::DB_PASS);

		$CONFIG['maintenance'] = false;
		$this->output->writeln('  > Updating <info>config.php</info>');
		$this->output->writeln('');
		file_put_contents($configFile, '<?php' . "\n" . '$CONFIG = ' . var_export($CONFIG, true) . ';' . "\n");
	}


	/**
	 * @param array $CONFIG
	 * @param string $used
	 */
	private function compareConfigDataRoot(array &$CONFIG, string $used): void {
		$fromConfig = rtrim($this->get(ConfigService::DATA_DIRECTORY, $CONFIG), '/') . '/';

		if ($fromConfig !== $used) {
			$this->output->writeln('');
			$this->output->writeln(
				'   * <info>datadirectory</info> from the file <info>config/config.php</info> that was recently '
				. 'restored from your backup is different than the path you used to extract the backup of the <info>datadirectory</info>'
			);

			$this->output->writeln('     - from config/config.php: <info>' . $fromConfig . '</info>');
			$this->output->writeln('     - used during extraction: <info>' . $used . '</info>');
			$question = new ConfirmationQuestion(
				'     - <comment>Do you want to replace the <info>datadirectory</info> in <info>config/config.php</info> with this new path ?</comment> (y/N) ',
				false,
				'/^(y|Y)/i'
			);

			$helper = $this->getHelper('question');
			if ($helper->ask($this->input, $this->output, $question)) {
				$CONFIG[ConfigService::DATA_DIRECTORY] = $used;
			}
		}
	}


	/**
	 * @param array $sqlParams
	 * @param array $CONFIG
	 * @param string $key
	 */
	private function compareConfigSqlParams(array $sqlParams, array &$CONFIG, string $key): void {
		$fromConfig = $this->get($key, $CONFIG);
		$used = $this->get($key, $sqlParams);
		if ($used !== $fromConfig) {
			$this->output->writeln('');
			$this->output->writeln(
				'   * The <info>configuration of the database</info> from the file <info>config/config.php</info> that was recently '
				. 'restored from your backup is different from the configuration used to import the <info>sqldump</info>'
			);

			if ($key !== ISqlDump::DB_PASS) {
				$this->output->writeln(
					'     - <info>' . $key . '</info> from config/config.php: <info>' . $fromConfig
					. '</info>'
				);
				$this->output->writeln(
					'     - <info>' . $key . '</info> used during extraction: <info>' . $used . '</info>'
				);
			}

			$question = new ConfirmationQuestion(
				'     - <comment>Do you want to replace the configuration of the database in <info>config/config.php</info> with this new <info>'
				. $key . '</info> ?</comment> (y/N) ',
				false,
				'/^(y|Y)/i'
			);

			$helper = $this->getHelper('question');
			if ($helper->ask($this->input, $this->output, $question)) {
				$CONFIG[$key] = $used;
			}
		}
	}


	/**
	 * @param RestoringPoint $point
	 * @param RestoringData $data
	 * @param array $sqlParams
	 *
	 * @throws SqlDumpException
	 * @throws SqlImportException
	 */
	private function importSqlDump(RestoringPoint $point, RestoringData $data, array $sqlParams): void {
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
		$sqlDump->import($sqlParams, $read);
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


	/**
	 * ugly but it does it job.
	 *
	 * @param array $sql
	 * @param bool $oneLine
	 *
	 * @return string
	 */
	private function displaySqlParams(array $sql, bool $oneLine = false): string {
		if ($oneLine) {
			return '<info>' . $this->get(ISqlDump::DB_USER, $sql) . '</info>:****@<info>'
				   . $this->get(ISqlDump::DB_HOST, $sql) . ':' . $this->get(ISqlDump::DB_PORT, $sql)
				   . '</info>/<info>'
				   . $this->get(ISqlDump::DB_NAME, $sql) . '</info>';
		}

		$this->output->writeln('      . Host: <info>' . $this->get(ISqlDump::DB_HOST, $sql) . '</info>');
		$this->output->writeln('      . Port: <info>' . $this->get(ISqlDump::DB_PORT, $sql) . '</info>');
		$this->output->writeln('      . Database: <info>' . $this->get(ISqlDump::DB_NAME, $sql) . '</info>');
		$this->output->writeln('      . Username: <info>' . $this->get(ISqlDump::DB_USER, $sql) . '</info>');
		$this->output->writeln('      . Password: <info>*******</info>');

		return '';
	}
}
