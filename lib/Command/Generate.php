<?php
/**
 * @copyright Copyright (c) 2017 Frank Karlitschek <frank@karlitschek.de>
 *
 * @author Frank Karlitschek <frank@karlitschek.de>
 *
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

use OCA\Backup\Backup\Create;
use OCA\Backup\AppInfo\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Generate extends Command {

	/**
	 */
	public function __construct() {
		parent::__construct();
	}

	protected function configure() {
		$this
			->setName('backup:create')
			->setDescription('Generate a backup of the instance')
			->addArgument(
				'path',
				InputArgument::REQUIRED,
				'The path where the backup should be created'
			)
			->addOption(
				'password',
				'pass',
				InputOption::VALUE_REQUIRED,
				'Optionally password for an encrypted backup',
				''
			)
		;
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return int
	 */
	protected function execute(InputInterface $input, OutputInterface $output) {

		$path = $input->getArgument('path');

		if ($path == '') {
			$output->writeln('Backup path not defined');
			return 1;
		}

		$password = $input->getOption('password');
		if ($password == '') {
			$output->writeln('Password not specified');
			return 1;
		}

		$backup = new \OCA\Backup\Backup\Create($path);
		$backup -> password($password);
		$backup -> create();

		return 0;
	}
}
