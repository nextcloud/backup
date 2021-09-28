<?php
declare(strict_types=1);


/**
 * Nextcloud - Backup
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Maxence Lange <maxence@artificial-owl.com>
 * @copyright 2019, Maxence Lange <maxence@artificial-owl.com>
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


namespace OCA\Backup\Service;


use ArtificialOwl\MySmallPhpTools\Exceptions\MalformedArrayException;
use ArtificialOwl\MySmallPhpTools\Traits\TArrayTools;
use Exception;
use OCA\Backup\Exceptions\ArchiveDeleteException;
use OCA\Backup\Exceptions\ArchiveNotFoundException;
use OCA\Backup\Exceptions\BackupFolderException;
use OCA\Backup\Exceptions\RestoringChunkNotFoundException;
use OCA\Backup\Exceptions\EncryptionKeyException;
use OCA\Backup\Model\ArchiveFile;
use OCA\Backup\Model\Backup;
use OCA\Backup\Model\RestoringChunk;
use OCA\Backup\Model\BackupChunk;
use OCA\Backup\Model\BackupOptions;
use OCA\Backup\SqlDump\SqlDumpMySQL;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;


/**
 * Class CliService
 *
 * @package OCA\Backup\Service
 */
class CliService {


	use TArrayTools;


	/** @var ArchiveService */
	private $archiveService;

	/** @var BackupService */
	private $backupService;

	/** @var MiscService */
	private $miscService;


	/** @var InputInterface */
	private $input;

	/** @var OutputInterface */
	private $output;


	/**
	 * CliService constructor.
	 *
	 * @param ArchiveService $archiveService
	 * @param BackupService $backupService
	 * @param MiscService $miscService
	 */
	public function __construct(
		ArchiveService $archiveService, BackupService $backupService, MiscService $miscService
	) {
		$this->archiveService = $archiveService;
		$this->backupService = $backupService;
		$this->miscService = $miscService;
	}


	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 */
	public function init(InputInterface $input, OutputInterface $output): void {
		$this->input = $input;
		$this->output = $output;
	}


	/**
	 * @param Backup $backup
	 */
	public function displayBackupResume(Backup $backup): void {
		$version = $backup->getVersion();
		array_pop($version);

		$this->output->writeln('<info>Token:</info> ' . $backup->getId());
		$this->output->writeln('<info>Name:</info> ' . $backup->getName());
		$this->output->writeln('<info>Date:</info> ' . date('Y-m-d H:i:s', $backup->getCreation()));
		$this->output->writeln('<info>Nextcloud</info> ' . implode('.', $version));

		$root = $this->backupService->getAbsoluteRoot($backup);
		$options = $backup->getOptions();
		$root .= ($options->getNewRoot() !== '') ? ' -> ' . $options->getNewRoot() : '';

		$this->output->writeln('<info>Root:</info> ' . $root);
		$this->output->writeln('');
	}


	/**
	 * @param Backup $backup
	 *
	 * @throws EncryptionKeyException
	 * @throws ArchiveDeleteException
	 */
	public function displayBackupDetails(Backup $backup): void {
		foreach ($backup->getChunks(true) as $backupChunk) {
			$this->displayBackupChunk($backup, $backupChunk);
			$this->displayBackupArchives($backup, $backupChunk->getArchives());

			$this->output->writeln('');
		}
	}


	/**
	 * @param Backup $backup
	 * @param BackupChunk $chunk
	 */
	public function displayBackupChunk(Backup $backup, BackupChunk $chunk): void {
		$root = $chunk->getRoot();
//		$options = $backup->getOptions();
		if ($backup->getOptions()
				   ->getNewRoot()) {
			$root .= ' -> ' . $this->backupService->getExtractRoot($backup, $chunk);
		}

		$path = ($chunk->getPath() === '') ? '.' : $chunk->getPath();
		$this->output->writeln('- <info>Chunk:</info> ' . $chunk->getName());

		if ($chunk->getRoot() === '') {
			return;
		}

		$this->output->writeln('- <info>Root:</info> ' . $root);
		$this->output->writeln('- <info>Path:</info> ' . $path);
	}


	/**
	 * @param Backup $backup
	 *
	 * @throws ArchiveDeleteException
	 * @throws ArchiveNotFoundException
	 * @throws EncryptionKeyException
	 * @throws BackupFolderException
	 */
	public function displayBackupRestore(Backup $backup): void {

		$options = $backup->getOptions();
		foreach ($backup->getChunks(true) as $chunk) {

			$this->displayBackupChunk($backup, $chunk);

			// TODO: pre-manage archives (continue on Exception)
			if ($chunk->getType() >= BackupChunk::SQL_DUMP) {
				$this->manageNonFileBackup($backup, $chunk);
				continue;
			}

			$root = $this->backupService->getExtractRoot($backup, $chunk);

			$helper = new QuestionHelper();
			$question = '- Extract this chunk in <info>' . $root . '</info> [Y/n]?';
			if (!$helper->ask($this->input, $this->output, new ConfirmationQuestion($question))) {
				continue;
			}

			foreach ($chunk->getArchives() as $archive) {
				$this->output->write(' > ' . $archive->getName());

				if ($options->isAll()) {
					$this->archiveService->extractAll($backup, $archive, $root);
					continue;
				}

				$this->archiveService->decryptArchive($backup, $archive);
				$zip = $this->archiveService->openZipArchive($archive);
				$this->archiveService->listFilesFromZip($archive, $zip);
				foreach ($archive->getFiles() as $file) {
					$this->output->write('   * ' . $file->getName(), false);
					$this->archiveService->extractFilesFromZip($zip, $root, [$file]);
				}
				$this->archiveService->deleteArchive($backup, $archive, 'zip');

				$this->output->writeln('');
			}

			if ($options->isAll()) {
				$this->output->writeln('');
			}

			// TODO: post manage archives
			if ($chunk->getType() === BackupChunk::FILE_CONFIG) {
				$this->manageConfigBackup($backup, $chunk);
			}
		}
	}


	/**
	 * @param Backup $backup
	 *
	 * @throws ArchiveDeleteException
	 * @throws ArchiveNotFoundException
	 * @throws EncryptionKeyException
	 */
	public function displayFilesList(Backup $backup): void {

		foreach ($backup->getChunks(true) as $backupChunk) {

			$this->displayBackupChunk($backup, $backupChunk);
			foreach ($backupChunk->getArchives() as $archive) {
				$this->archiveService->decryptArchive($backup, $archive);
				$zip = $this->archiveService->openZipArchive($archive);
				$this->archiveService->listFilesFromZip($archive, $zip);

				foreach ($archive->getFiles() as $file) {
					if ($this->fitSearch($backup->getOptions(), $file)) {
						echo '- ' . $archive->getName() . ': ' . $file->getName() . "\n";
					}
				}

				$this->archiveService->deleteArchive($backup, $archive, 'zip');
			}
		}
	}


	/**
	 * @param BackupOptions $options
	 * @param ArchiveFile $file
	 *
	 * @return bool
	 */
	private function fitSearch(BackupOptions $options, ArchiveFile $file) {
		$fitSearch = $fitPath = false;

		$search = $options->getSearch();
		$path = $options->getPath();
		if ($search === '' || strpos($file->getName(), $search)) {
			$fitSearch = true;
		}

		if ($path === '' || strpos($file->getName(), $path) === 0) {
			$fitPath = true;
		}

		return ($fitPath && $fitSearch);
	}


	/**
	 * @param Backup $backup
	 * @param array $backupArchives
	 *
	 * @throws EncryptionKeyException
	 * @throws ArchiveDeleteException
	 */
	private function displayBackupArchives(Backup $backup, array $backupArchives): void {

		$table = new Table($this->output);
		$table->setHeaders(
			['', 'Name', 'Files', 'Size', 'Encrypted Checksum', 'Archive Checksum'], []
		);
		$table->render();
		$this->output->writeln('');

		$c = 0;
		foreach ($backupArchives as $archive) {
			$checksum = $this->generateEncryptedChecksum($backup, $archive);
			$decryptedChecksum = $this->generateArchiveChecksum($backup, $archive);
			$table->appendRow(
				[
					++$c . '/' . count($backupArchives),
					$archive->getName(),
					$archive->getCount(),
					$archive->getSize(),
					$checksum,
					$decryptedChecksum
				]
			);
		}
	}


	/**
	 * @param Backup $backup
	 * @param RestoringChunk $archive
	 *
	 * @return string
	 */
	private function generateEncryptedChecksum(Backup $backup, RestoringChunk $archive): string {
		if (!$this->input->getOption('check')) {
			return $archive->getEncryptedChecksum();
		}

		try {
			if ($this->archiveService->verifyChecksum($backup, $archive, true)) {
				return '<info>' . $archive->getEncryptedChecksum() . '</info>';
			}

			return '<error>' . $archive->getEncryptedChecksum() . '</error>';
		} catch (Exception $e) {
			return '<error>' . $e->getMessage() . '</error>';
		}
	}


	/**
	 * @param Backup $backup
	 * @param RestoringChunk $archive
	 *
	 * @return string
	 * @throws EncryptionKeyException
	 * @throws ArchiveDeleteException
	 */
	private function generateArchiveChecksum(Backup $backup, RestoringChunk $archive): string {
		if ($backup->getEncryptionKey() === '') {
			return $archive->getChecksum();
		}

		try {
			$this->archiveService->decryptArchive($backup, $archive);
			$check = $this->archiveService->verifyChecksum($backup, $archive, false);
			$this->archiveService->deleteArchive($backup, $archive, 'zip');

			if ($check) {
				return '<info>' . $archive->getChecksum() . '</info>';
			}

			return '<error>' . $archive->getChecksum() . '</error>';
		} catch (ArchiveNotFoundException $e) {
			return '<error>' . $e->getMessage() . '</error>';
		}
	}


	/**
	 * @param Backup $backup
	 * @param BackupChunk $chunk
	 */
	private function manageNonFileBackup(Backup $backup, BackupChunk $chunk): void {
		switch ($chunk->getType()) {
			case BackupChunk::SQL_DUMP:
				$this->manageSqlDump($backup, $chunk);
				break;
		}
	}


	/**
	 * @param Backup $backup
	 * @param BackupChunk $chunk
	 */
	private function manageSqlDump(Backup $backup, BackupChunk $chunk) {
		$helper = new QuestionHelper();

		$data = [];
		try {
			$configChunk = $this->backupService->getChunk($backup, 'config');
			$config = $this->backupService->getExtractRoot($backup, $configChunk) . 'config.php';

			$question = '- Using database configured in <info>' . $config . '</info> [Y/n]?';
			if (file_exists($config)
				&& $helper->ask($this->input, $this->output, new ConfirmationQuestion($question))) {
				$data = $this->extractDatabaseConfig($config);
			} else {
				$this->output->writeln(' <!> File <comment>' . $config . '</comment> not found');
			}
		} catch (RestoringChunkNotFoundException | MalformedArrayException $e) {
		}

		if (empty($data)) {
			// TODO: ask for credentials
		}

		$question = '- Restore your database in <info>' . $data['dbname'] . '</info> [Y/n]?';
		if ($helper->ask($this->input, $this->output, new ConfirmationQuestion($question))) {
			try {
				$this->importDumpFromChunk($backup, $chunk, $data);
			} catch (ArchiveDeleteException $e) {
			} catch (ArchiveNotFoundException $e) {
			} catch (EncryptionKeyException $e) {
			}
		}

	}




	/**
	 * @param Backup $backup
	 * @param BackupChunk $chunk
	 *
	 * @param array $data
	 *
	 * @throws ArchiveDeleteException
	 * @throws ArchiveNotFoundException
	 * @throws EncryptionKeyException
	 */
	private function importDumpFromChunk(Backup $backup, BackupChunk $chunk, array $data) {
		foreach ($chunk->getArchives() as $archive) {
			$this->archiveService->decryptArchive($backup, $archive);
			$zip = $this->archiveService->openZipArchive($archive);
			$read = $zip->getStream(BackupService::SQL_DUMP_FILE);

			$sqlDump = new SqlDumpMySQL();
			$sqlDump->import($data, $read);

			$this->archiveService->deleteArchive($backup, $archive, 'zip');
		}

	}


	/**
	 * @param Backup $backup
	 * @param BackupChunk $chunk
	 */
	private function manageConfigBackup(Backup $backup, BackupChunk $chunk) {
		$options = $backup->getOptions();

		if (empty($options->getConfig()) && !$options->isFixDataDir()) {
			return;
		}

		$configFile = $this->backupService->getExtractRoot($backup, $chunk) . 'config.php';
		$CONFIG = [];
		require($configFile);

		if ($options->isFixDataDir()) {
			try {
				$dataChunk = $this->backupService->getChunk($backup, 'data');
				$CONFIG['datadirectory'] =
					$this->backupService->getExtractRoot($backup, $dataChunk);
			} catch (RestoringChunkNotFoundException $e) {
			}
		}

		$CONFIG = array_merge($CONFIG, $options->getConfig());

		file_put_contents($configFile, "<?php\n" . '$CONFIG = ' . var_export($CONFIG, true) . ';');
	}

}

