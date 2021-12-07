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

use OC\Core\Command\Base;
use OCA\Backup\Exceptions\ArchiveCreateException;
use OCA\Backup\Exceptions\ArchiveNotFoundException;
use OCA\Backup\Exceptions\BackupAppCopyException;
use OCA\Backup\Exceptions\BackupScriptNotFoundException;
use OCA\Backup\Exceptions\RestoringPointException;
use OCA\Backup\Exceptions\SqlDumpException;
use OCA\Backup\Service\OutputService;
use OCA\Backup\Service\PointService;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Class PointCreate
 *
 * @package OCA\Backup\Command
 */
class PointCreate extends Base {


	/** @var PointService */
	private $pointService;

	/** @var OutputService */
	private $outputService;


	/**
	 * PointCreate constructor.
	 *
	 * @param PointService $pointService
	 * @param OutputService $outputService
	 */
	public function __construct(PointService $pointService, OutputService $outputService) {
		parent::__construct();

		$this->pointService = $pointService;
		$this->outputService = $outputService;
	}


	/**
	 *
	 */
	protected function configure() {
		parent::configure();

		$this->setName('backup:point:create')
			 ->setDescription('Generate a restoring point of the instance (complete or incremental)')
			 ->addArgument('comment', InputArgument::OPTIONAL, 'set a comment to the restoring point', '')
			 ->addOption('generate-log', '', InputOption::VALUE_NONE, 'generate a log file')
			 ->addOption(
				 'differential', '', InputOption::VALUE_NONE, 'create an differential restoring point'
			 );
	}


	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @return int
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws ArchiveCreateException
	 * @throws ArchiveNotFoundException
	 * @throws BackupAppCopyException
	 * @throws BackupScriptNotFoundException
	 * @throws SqlDumpException
	 * @throws RestoringPointException
	 * @throws Throwable
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$o = $input->getOption('output');
		if ($o !== 'none' && $o !== 'json') {
			$this->outputService->setOutput($output);
		}

		$point = $this->pointService->create(
			!$input->getOption('differential'),
			$input->getArgument('comment'),
			($input->getOption('generate-log')) ? 'occ backup:point:create' : '',
		);

		if ($o === 'none') {
			return 0;
		}

		if ($o === 'json') {
			$output->writeln(json_encode($point, JSON_PRETTY_PRINT));

			return 0;
		}

		$output->writeln('');
		$output->writeln('Restoring Point ID: <info>' . $point->getId() . '</info>');

		return 0;
	}
}
