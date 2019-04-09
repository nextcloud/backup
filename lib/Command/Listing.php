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
use OCA\Backup\Service\BackupService;
use OCA\Backup\Service\MiscService;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


/**
 * Class Backup
 *
 * @package OCA\Backup\Command
 */
class Listing extends Base {


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
		$this->setName('backup:list')
			 ->setDescription('get list of available backups');
	}


	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @throws NotPermittedException
	 * @throws NotFoundException
	 */
	protected function execute(InputInterface $input, OutputInterface $output) {
		$backups = $this->backupService->listing();

		foreach ($backups as $backup) {
			echo '   ' . date("Y-m-d H:i:s", $backup->getCreation()) . ' - ' . $backup->getId()
				 . "\n";
		}
	}

}

