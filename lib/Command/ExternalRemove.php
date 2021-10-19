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
use OCA\Backup\Db\ExternalFolderRequest;
use OCA\Backup\Exceptions\ExternalFolderNotFoundException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class ExternalRemove
 *
 * @package OCA\Backup\Command
 */
class ExternalRemove extends Base {


	/** @var ExternalFolderRequest */
	private $externalFolderRequest;


	/**
	 * ExternalRemove constructor.
	 *
	 * @param ExternalFolderRequest $externalFolderRequest
	 */
	public function __construct(ExternalFolderRequest $externalFolderRequest) {
		$this->externalFolderRequest = $externalFolderRequest;

		parent::__construct();
	}


	/**
	 *
	 */
	protected function configure() {
		$this->setName('backup:external:remove')
			 ->setDescription('Removing external filesystem from database')
			 ->addArgument('storage_id', InputArgument::REQUIRED, 'storageId');
	}


	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @return int
	 * @throws ExternalFolderNotFoundException
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$storageId = (int)$input->getArgument('storage_id');

		try {
			$this->externalFolderRequest->getByStorageId($storageId);
		} catch (ExternalFolderNotFoundException $e) {
			throw new ExternalFolderNotFoundException('Unknown external folder');
		}

		$this->externalFolderRequest->remove($storageId);
		$output->writeln('external filesystem removed.');

		return 0;
	}
}
