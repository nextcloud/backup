<?php
declare(strict_types=1);


/**
 * Nextcloud - Backup
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Frank Karlitschek <frank@karlitschek.de>
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


namespace OCA\Backup\Command;


use OC\Core\Command\Base;
use OCA\Backup\Exceptions\ArchiveDeleteException;
use OCA\Backup\Exceptions\BackupNotFoundException;
use OCA\Backup\Exceptions\EncryptionKeyException;
use OCA\Backup\Service\ArchiveService;
use OCA\Backup\Service\BackupService;
use OCA\Backup\Service\CliService;
use OCA\Backup\Service\MiscService;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;


/**
 * Class Backup
 *
 * @package OCA\Backup\Command
 */
class Details extends Base {


	/** @var IAppData */
	private $appData;

	/** @var CliService */
	private $cliService;

	/** @var BackupService */
	private $backupService;

	/** @var ArchiveService */
	private $archiveService;

	/** @var MiscService */
	private $miscService;


	/**
	 * Backup constructor.
	 *
	 * @param IAppData $appData
	 * @param CliService $cliService
	 * @param BackupService $backupService
	 * @param ArchiveService $archiveService
	 * @param MiscService $miscService
	 */
	public function __construct(
		IAppData $appData, CliService $cliService, BackupService $backupService,
		ArchiveService $archiveService, MiscService $miscService
	) {
		$this->appData = $appData;
		$this->cliService = $cliService;
		$this->backupService = $backupService;
		$this->archiveService = $archiveService;
		$this->miscService = $miscService;

		parent::__construct();
	}


	/**
	 *
	 */
	protected function configure() {
		$this->setName('backup:details')
			 ->addArgument('token', InputArgument::REQUIRED, 'token of the backup')
			 ->addOption('source', 's', InputOption::VALUE_REQUIRED, 'specify source')
			 ->addOption(
				 'check', 'c', InputOption::VALUE_NONE, 'verify the integrity of encrypted files'
			 )
			 ->addOption(
				 'key', 'k', InputOption::VALUE_REQUIRED,
				 'verify the integrity of the decrypted archive files'
			 )
			 ->addOption('json', 'j', InputOption::VALUE_NONE, 'display json')
			 ->setDescription('Details about a backup');
	}


	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @throws BackupNotFoundException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws EncryptionKeyException
	 * @throws ArchiveDeleteException
	 */
	protected function execute(InputInterface $input, OutputInterface $output) {

		$output = new ConsoleOutput();
		$output = $output->section();
		$this->cliService->init($input, $output);

		/** TODO: manage sources:
		 * - default is appdata: we assume the files are in oc_cache and the world is a nice place.
		 * - source could be in the format files://user@path/ if backups files are available in
		 *   the files app
		 * - it would also be nice to add a PHP script at the root of the folder that contains
		 *   the backup. The script would:
		 *    > download the right version of nextcloud (from backup.json),
		 *    > auto-extract the archives files at the right place.
		 */
		$token = $input->getArgument('token');
		$backup = $this->backupService->getBackup($token);

		if ($input->getOption('json')) {
			echo json_encode($backup, JSON_PRETTY_PRINT) . "\n";

			return;
		}

		$this->cliService->displayBackupResume($backup);
		$this->cliService->displayBackupDetails($backup);
	}


}

