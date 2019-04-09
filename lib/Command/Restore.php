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
use OCA\Backup\Exceptions\BackupNotFoundException;
use OCA\Backup\Exceptions\EncryptionKeyException;
use OCA\Backup\Service\BackupService;
use OCA\Backup\Service\MiscService;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


/**
 * Class Backup
 *
 * @package OCA\Backup\Command
 */
class Restore extends Base {


	/** @var BackupService */
	private $backupService;

	/** @var MiscService */
	private $miscService;


	/**
	 * Backup constructor.
	 *
	 * @param BackupService $backupService
	 * @param MiscService $miscService
	 */
	public function __construct(BackupService $backupService, MiscService $miscService) {
		$this->backupService = $backupService;
		$this->miscService = $miscService;

		parent::__construct();
	}


	/**
	 *
	 */
	protected function configure() {
		$this->setName('backup:restore')
			 ->addArgument('token', InputArgument::REQUIRED, 'token of the backup to restore')
			 ->addOption('key', 'k', InputOption::VALUE_REQUIRED, 'specify encryption key')
			 ->addOption(
				 'files', 'f', InputOption::VALUE_REQUIRED, 'restore only a specific bunch of files'
			 )
			 ->setDescription('Restore a backup of the instance');
	}


	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @throws NotPermittedException
	 * @throws NotFoundException
	 * @throws BackupNotFoundException
	 * @throws EncryptionKeyException
	 */
	protected function execute(InputInterface $input, OutputInterface $output) {
		$token = $input->getArgument('token');
		$key = ($input->getOption('key') === null) ? '' : $input->getOption('key');
		$files = ($input->getOption('files') === null) ? '' : $input->getOption('files');

		$output->writeln('not available (yet) from the occ.');
//		$backup = $this->backupService->restore($token, $pass, $files);

//				echo '> ' . json_encode($backup, JSON_PRETTY_PRINT);
	}

}

