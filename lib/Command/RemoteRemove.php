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
use OCA\Backup\Db\RemoteRequest;
use OCA\Backup\Exceptions\RemoteInstanceNotFoundException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


/**
 * Class RemoteRemove
 *
 * @package OCA\Backup\Command
 */
class RemoteRemove extends Base {


	/** @var RemoteRequest */
	private $remoteRequest;


	/**
	 * RemoteRemove constructor.
	 *
	 * @param RemoteRequest $remoteRequest
	 */
	public function __construct(RemoteRequest $remoteRequest) {
		$this->remoteRequest = $remoteRequest;

		parent::__construct();
	}


	/**
	 *
	 */
	protected function configure() {
		$this->setName('backup:remote:remove')
			 ->setDescription('Removing remote instances from database')
			 ->addArgument('address', InputArgument::REQUIRED, 'address of the remote instance of Nextcloud');
	}


	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @return int
	 * @throws RemoteInstanceNotFoundException
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$address = $input->getArgument('address');

		try {
			$this->remoteRequest->getFromInstance($address);
		} catch (RemoteInstanceNotFoundException $e) {
			throw new RemoteInstanceNotFoundException('Unknown address');
		}

		$this->remoteRequest->remove($address);
		$output->writeln('instance removed.');

		return 0;
	}

}
