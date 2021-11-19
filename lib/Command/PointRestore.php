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
use OCA\Backup\Exceptions\SqlParamsException;
use OCA\Backup\ISqlDump;
use OCA\Backup\Model\ChangedFile;
use OCA\Backup\Model\RestoringChunk;
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
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutput;
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
			 ->addOption('do-not-ask-data', '', InputOption::VALUE_NONE, 'Do not ask for path on data')
			 ->addOption('do-not-ask-sql', '', InputOption::VALUE_NONE, 'Do not ask for params on sqldump')
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
		$this->forceDisableMaintenanceMode();
		$this->restoreService->finalizeFullRestore();

		$this->activityService->newActivity(
			ActivityService::RESTORE,
			[
				'id' => $point->getId(),
				'date' => $point->getDate(),
				'rewind' => time() - $point->getDate()
			]
		);


		$this->displayResume($point);

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
				$this->output->writeln('   * ignoring');
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
		try {
			$root = $this->requestDataRoot($data);
		} catch (RestoringDataNotFoundException $e) {
			$this->output->writeln('   * ignoring data pack');

			return;
		}

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
	 * @throws SqlImportException
	 */
	private function restorePointSqlDump(RestoringPoint $point, RestoringData $data): void {
		$fromConfig = $this->getSqlParamsFromConfig($point);

		// get unique chunk from data
		$chunks = $data->getChunks();
		if (sizeof($chunks) !== 1) {
			throw new SqlImportException('sql dump contains no chunks');
		}
		$chunk = $chunks[0];

		while (true) {
			try {
				$sqlParams = $this->requestSqlParams(strtolower($chunk->getType()), $fromConfig);
			} catch (RestoringDataNotFoundException $e) {
				$this->output->writeln('   * ignoring sqldump');

				return;
			}

			$this->output->write(
				'   > importing sqldump in <info>' . $this->displaySqlParams($sqlParams, true) . '</info>: '
			);
			try {
				$this->importSqlDump($point, $chunk, $sqlParams);
				$this->output->writeln('<info>ok</info>');
			} catch (SqlParamsException $e) {
				$this->output->writeln('<error>' . $e->getMessage() . '</error>');
				continue;
			} catch (SqlImportException $e) {
				$this->output->writeln('<error>' . $e->getMessage() . '</error>');
			}

			break;
		}

		$data->setRestoredRoot(json_encode($sqlParams));
	}


	/**
	 * @param RestoringData $data
	 *
	 * @return string
	 * @throws RestoringDataNotFoundException
	 */
	private function requestDataRoot(RestoringData $data): string {
		$root = $data->getAbsolutePath();
		if ($this->input->getOption('do-not-ask-data')) {
			return $root;
		}

		while (true) {
			$this->output->writeln('   > will be extracted in <info>' . $root . '</info>');
			$helper = $this->getHelper('question');
			$question = new Question(
				'    - <comment>enter a new absolute path</comment>, or type <info>yes</info> to confirm '
				. 'this location or <info>no</info> to ignore this part of the backup: ',
				''
			);
			$question->setAutocompleterValues([$data->getAbsolutePath(), 'yes', 'no']);
			$newRoot = trim($helper->ask($this->input, $this->output, $question));

			if ($newRoot === '') {
				continue;
			}

			if ($newRoot === 'yes') {
				break;
			}

			if ($newRoot === 'no') {
				throw new  RestoringDataNotFoundException('ignoring');
			}

			$newRoot = rtrim($newRoot, '/') . '/';
			if ($newRoot === $root) {
				break;
			}

			$root = $newRoot;
		}

		return $root;
	}


	/**
	 * @param string $type
	 * @param array $fromConfig
	 *
	 * @return array
	 * @throws RestoringDataNotFoundException
	 * @throws SqlImportException
	 */
	private function requestSqlParams(string $type = '', array $fromConfig = []): array {
		$sqlParams = $this->pointService->getSqlParams();
		if ($this->input->getOption('do-not-ask-sql')) {
			return $sqlParams;
		}

		$options = ['cancel', 'yes', 'edit'];
		if (!empty($fromConfig)) {
			array_push($options, 'load');
		}

		while (true) {
			$this->output->writeln('   > will be imported in ' . $this->displaySqlParams($sqlParams, true));

			$this->output->writeln('');
			$this->output->writeln('    * <comment>Do you want to</comment>:');
			$this->output->writeln('      - use this settings and start importing the sqldump (yes)');
			$this->output->writeln('      - manually editing the Sql settings (edit)');
			if (!empty($fromConfig)) {
				$this->output->writeln(
					'      - load the settings from the config/config.php found in the backup (load)'
				);
				$displayLoad = 'load/';
			} else {
				$displayLoad = '';
			}
			$this->output->writeln(
				'      - cancel the import of the sqldump, and go on with the restoring process (cancel)'
			);
			$this->output->writeln('');

			$helper = $this->getHelper('question');
			$question = new Question(
				'    * <comment>Do you want to import the sqldump using those settings?</comment> (yes/Edit/'
				. $displayLoad . 'cancel) ',
				'edit',
			);
			$question->setAutocompleterValues($options);

			$ret = strtolower($helper->ask($this->input, $this->output, $question));

			if ($ret === 'cancel') {
				throw new RestoringDataNotFoundException();
			}
			if ($ret === 'load') {
				$sqlParams = $fromConfig;
				continue;
			}

			if ($ret === 'yes') {
				$dbType = strtolower($this->get(ISqlDump::DB_TYPE, $sqlParams));
				if ($type !== '' && $type !== $dbType) {
					$this->output->writeln(
						'<error>cannot import sqldump from \'' . $type . '\' into \'' . $dbType . '\'</error>'
					);
					continue;
				}

				return $sqlParams;
			}

			$this->output->writeln('    - current configuration:');
			$this->displaySqlParams($sqlParams);

			$this->output->writeln('    - edit configuration:  (enter \'.\' to skip this step)');
			$this->output->writeln('      . Type: <info>' . $type . '</info>');

			while (true) {
				$question = new Question('      . Host: ', '');
				$newHost = trim($helper->ask($this->input, $this->output, $question));
				if ($newHost !== '') {
					break;
				}
			}
			if ($newHost === '.') {
				continue;
			}

			$question = new Question('      . Port: ', '');
			$newPort = trim($helper->ask($this->input, $this->output, $question));
			if ($newPort === '.') {
				continue;
			}

			while (true) {
				$question = new Question('      . Database: ', '');
				$newName = trim($helper->ask($this->input, $this->output, $question));
				if ($newName !== '') {
					break;
				}
			}
			if ($newName === '.') {
				continue;
			}

			while (true) {
				$question = new Question('      . Username: ', '');
				$newUser = trim($helper->ask($this->input, $this->output, $question));
				if ($newUser !== '') {
					break;
				}
			}
			if ($newUser === '.') {
				continue;
			}

			while (true) {
				$question = new Question('      . Password: ', '');
				$question->setHidden(true);
				$newPass = trim($helper->ask($this->input, $this->output, $question));
				if ($newPass !== '') {
					break;
				}
			}
			if ($newPass === '.') {
				continue;
			}

			$newParams = [
				ISqlDump::DB_TYPE => $type,
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
	 *
	 * @return array
	 */
	private function getSqlParamsFromConfig(RestoringPoint $point): array {
		$CONFIG = $this->getConfigDirect($point);
		if (empty($CONFIG)) {
			return [];
		}

		return [
			ISqlDump::DB_TYPE => $this->get(ISqlDump::DB_TYPE, $CONFIG),
			ISqlDump::DB_NAME => $this->get(ISqlDump::DB_NAME, $CONFIG),
			ISqlDump::DB_HOST => $this->get(ISqlDump::DB_HOST, $CONFIG),
			ISqlDump::DB_PORT => $this->get(ISqlDump::DB_PORT, $CONFIG),
			ISqlDump::DB_USER => $this->get(ISqlDump::DB_USER, $CONFIG),
			ISqlDump::DB_PASS => $this->get(ISqlDump::DB_PASS, $CONFIG)
		];
	}


	/**
	 * @param RestoringPoint $point
	 */
	private function updateConfig(RestoringPoint $point): void {
		$this->output->writeln('> Refreshing <info>config.php</info>');

		$sqlParams = [];
		$dataRoot = '';
		foreach ($point->getRestoringData() as $data) {
			if ($data->getType() === RestoringData::ROOT_DATA) {
				if ($data->getRestoredRoot() === '') {
					continue;
				}
				$dataRoot = $data->getRestoredRoot();
			}

			if ($data->getType() === RestoringData::FILE_SQL_DUMP) {
				if ($data->getRestoredRoot() === '') {
					continue;
				}
				$sqlParams = json_decode($data->getRestoredRoot(), true);
				if (!is_array($sqlParams)) {
					$sqlParams = [];
				}
			}
		}

		$configFile = '';
		$CONFIG = $this->getConfigDirect($point, $configFile);
		if (empty($CONFIG)) {
			$this->output->writeln(
				'  * do not refresh as <info>config/config.php</info> were not restored'
			);
			$this->configService->maintenanceMode(false);

			return;
		}

		if ($dataRoot !== '') {
			$this->compareConfigDataRoot($CONFIG, $dataRoot);
		}
		if (!empty($sqlParams)) {
			$this->compareConfigSqlParams($sqlParams, $CONFIG, ISqlDump::DB_HOST);
			$this->compareConfigSqlParams($sqlParams, $CONFIG, ISqlDump::DB_PORT);
			$this->compareConfigSqlParams($sqlParams, $CONFIG, ISqlDump::DB_NAME);
			$this->compareConfigSqlParams($sqlParams, $CONFIG, ISqlDump::DB_USER);
			$this->compareConfigSqlParams($sqlParams, $CONFIG, ISqlDump::DB_PASS);
		}

		$CONFIG[ConfigService::MAINTENANCE] = false;
		$this->output->writeln('  > Updating <info>config.php</info>');
		$this->output->writeln('');
		file_put_contents(
			$configFile, '<?php' . "\n" . '$CONFIG = ' . var_export($CONFIG, true) . ';' . "\n"
		);
	}


	/**
	 * @param RestoringPoint $point
	 * @param string $configFile
	 *
	 * @return array
	 */
	private function getConfigDirect(RestoringPoint $point, string &$configFile = ''): array {
		$dataRoot = $configRoot = '';
		foreach ($point->getRestoringData() as $data) {
			if ($data->getType() === RestoringData::FILE_CONFIG) {
				if ($data->getRestoredRoot() === '') {
					return [];
				}
				$configRoot = $data->getRestoredRoot();
			}
		}

		$CONFIG = [];
		$configFile = rtrim($configRoot, '/') . '/config.php';
		include $configFile;

		return $CONFIG;
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



//	private function getConfigSqlParams();

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
	 * @param RestoringChunk $chunk
	 * @param array $sqlParams
	 *
	 * @throws SqlDumpException
	 * @throws SqlImportException
	 * @throws SqlParamsException
	 */
	private function importSqlDump(RestoringPoint $point, RestoringChunk $chunk, array $sqlParams): void {
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
		$sqlDump = $this->pointService->getSqlDump($sqlParams);
		$sqlDump->setup($sqlParams);

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

			// include restored file in next differential backup
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
			return '<info>' . $this->get(ISqlDump::DB_TYPE, $sql) . '</info>://<info>'
				   . $this->get(ISqlDump::DB_USER, $sql) . '</info>:****@<info>'
				   . $this->get(ISqlDump::DB_HOST, $sql) . ':' . $this->get(ISqlDump::DB_PORT, $sql)
				   . '</info>/<info>'
				   . $this->get(ISqlDump::DB_NAME, $sql) . '</info>';
		}

		$this->output->writeln('      . Type: <info>' . $this->get(ISqlDump::DB_TYPE, $sql) . '</info>');
		$this->output->writeln('      . Host: <info>' . $this->get(ISqlDump::DB_HOST, $sql) . '</info>');
		$this->output->writeln('      . Port: <info>' . $this->get(ISqlDump::DB_PORT, $sql) . '</info>');
		$this->output->writeln('      . Database: <info>' . $this->get(ISqlDump::DB_NAME, $sql) . '</info>');
		$this->output->writeln('      . Username: <info>' . $this->get(ISqlDump::DB_USER, $sql) . '</info>');
		$this->output->writeln('      . Password: <info>*******</info>');

		return '';
	}


	/**
	 * @param RestoringPoint $point
	 */
	private function displayResume(RestoringPoint $point): void {
		$this->output->writeln('');
		$this->output->writeln('');

		$output = new ConsoleOutput();
		$output = $output->section();
		$table = new Table($output);
		$table->setHeaders(['Data', 'Status', 'Path']);
		$table->render();

		foreach ($point->getRestoringData() as $data) {
			if ($data->getType() === RestoringData::INTERNAL_DATA) {
				continue;
			}

			$path = '';
			if ($data->getRestoredRoot() === '') {
				$status = 'ignored';
			} else {
				if ($data->getType() === RestoringData::FILE_SQL_DUMP) {
					$status = '<info>imported</info>';
					$path = $this->displaySqlParams(
						json_decode($data->getRestoredRoot(), true),
						true
					);
				} else {
					$status = '<info>extracted</info>';
					$path = $data->getRestoredRoot();
				}
			}

			$table->appendRow(
				[
					'<info>' . $data->getName() . '</info>',
					$status,
					$path
				]
			);
		}
	}


	/**
	 *
	 */
	private function forceDisableMaintenanceMode() {
		$CONFIG = [];
		$cwd = getcwd();
		if (is_bool($cwd)) {
			return;
		}

		$configFile = $cwd . '/config/config.php';
		include $configFile;

		if (empty($CONFIG) || $this->getBool(ConfigService::MAINTENANCE, $CONFIG) === false) {
			return;
		}

		$CONFIG[ConfigService::MAINTENANCE] = false;
		file_put_contents(
			$configFile, '<?php' . "\n" . '$CONFIG = ' . var_export($CONFIG, true) . ';' . "\n"
		);
	}
}
