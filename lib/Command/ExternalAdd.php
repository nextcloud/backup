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


use ArtificialOwl\MySmallPhpTools\Exceptions\RequestNetworkException;
use ArtificialOwl\MySmallPhpTools\Exceptions\SignatoryException;
use ArtificialOwl\MySmallPhpTools\Exceptions\SignatureException;
use ArtificialOwl\MySmallPhpTools\Exceptions\WellKnownLinkNotFoundException;
use ArtificialOwl\MySmallPhpTools\Model\SimpleDataStore;
use ArtificialOwl\MySmallPhpTools\Traits\Nextcloud\nc23\TNC23WellKnown;
use OC\Core\Command\Base;
use OCA\Backup\AppInfo\Application;
use OCA\Backup\Db\ExternalFolderRequest;
use OCA\Backup\Exceptions\RemoteInstanceDuplicateException;
use OCA\Backup\Exceptions\RemoteInstanceException;
use OCA\Backup\Exceptions\RemoteInstanceUidException;
use OCA\Backup\Model\ExternalFolder;
use OCA\Backup\Model\RemoteInstance;
use OCA\Backup\Service\RemoteStreamService;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;


/**
 * Class ExternalAdd
 *
 * @package OCA\Backup\Command
 */
class ExternalAdd extends Base {


	use TNC23WellKnown;


	/** @var ExternalFolderRequest */
	private $externalFolderRequest;

	/** @var RemoteStreamService */
	private $remoteStreamService;


	public function __construct(ExternalFolderRequest $externalFolderRequest) {
		parent::__construct();

		$this->externalFolderRequest = $externalFolderRequest;
	}


	/**
	 *
	 */
	protected function configure() {
		$this->setName('backup:external:add')
			 ->setDescription('Add external filesystem to store your backups')
			 ->addArgument('storage_id', InputArgument::REQUIRED, 'storage_id from oc_storage')
			 ->addArgument('root', InputArgument::REQUIRED, 'folder');
	}


	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @return int
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$storageId = (int)$input->getArgument('storage_id');
		$root = $input->getArgument('root');

		$folder = new ExternalFolder();
		$folder->setStorageId($storageId);
		$folder->setRoot($root);

		$this->externalFolderRequest->save($folder);

		return 0;
	}


	/**
	 * @param OutputInterface $output
	 * @param string $address
	 *
	 * @return SimpleDataStore
	 * @throws RequestNetworkException
	 * @throws WellKnownLinkNotFoundException
	 */
	private function getCurrentResourceFromAddress(
		OutputInterface $output,
		string $address
	): SimpleDataStore {
		try {
			$webfinger = $this->getWebfinger($address, Application::APP_SUBJECT);
		} catch (RequestNetworkException $e) {
			throw new RequestNetworkException(
				$address
				. ' is not reachable or is not a instance of Nextcloud or do not have the Backup App installed'
			);
		}
		try {
			$backupLink = $this->extractLink(Application::APP_REL, $webfinger);
		} catch (WellKnownLinkNotFoundException $e) {
			throw new WellKnownLinkNotFoundException(
				$address
				. ' is not a instance of Nextcloud or do not have the Backup App installed and configured'
			);
		}

		$output->writeln(
			'Remote instance <info>' . $address . '</info> is using <info>' . $backupLink->getProperty('name')
			. ' v' . $backupLink->getProperty('version') . '</info>'
		);

		$resource = $this->getResourceFromLink($backupLink);
		$output->writeln('Authentication key: <info>' . $resource->g('uid') . '</info>');

		return $resource;
	}


	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @param RemoteInstance $remoteInstance
	 * @param RemoteInstance|null $knownInstance
	 */
	private function configureRemoteInstance(
		InputInterface $input,
		OutputInterface $output,
		RemoteInstance $remoteInstance,
		?RemoteInstance $knownInstance
	): void {
		$outgoing = !(is_null($knownInstance)) && $knownInstance->isOutgoing();
		$incoming = !(is_null($knownInstance)) && $knownInstance->isIncoming();

		$output->writeln('');
		$helper = $this->getHelper('question');
		$question = new ConfirmationQuestion(
			'Do you want to use <info>' . $remoteInstance->getInstance()
			. '</info> as a remote instance to store your backup files ? '
			. ($outgoing ? '(Y/n)' : '(y/N)'),
			$outgoing,
			'/^(y|Y)/i'
		);

		$remoteInstance->setOutgoing($helper->ask($input, $output, $question));

		$question = new ConfirmationQuestion(
			'Do you want to allow <info>' . $remoteInstance->getInstance()
			. '</info> to store its backup files on your own instance ? '
			. ($incoming ? '(Y/n)' : '(y/N)'),
			$incoming,
			'/^(y|Y)/i'
		);

		$remoteInstance->setIncoming($helper->ask($input, $output, $question));
	}


	/**
	 * @throws RemoteInstanceUidException
	 */
	private function saveRemoteInstance(
		InputInterface $input,
		OutputInterface $output,
		RemoteInstance $remoteInstance
	): void {
		$output->writeln('');
		$output->writeln(
			'Using remote instance to store local backups: ' . ($remoteInstance->isOutgoing(
			) ? '<info>yes</info>' : '<comment>no</comment>')
		);
		$output->writeln(
			'Locally storing backups from remote instance: ' . ($remoteInstance->isIncoming(
			) ? '<info>yes</info>' : '<comment>no</comment>')
		);

		$helper = $this->getHelper('question');
		$question = new ConfirmationQuestion(
			'Please confirm those settings <info>(y/N)</info> ',
			false,
			'/^(y|Y)/i'
		);

		if (!$helper->ask($input, $output, $question)) {
			return;
		}

		$this->remoteRequest->insertOrUpdate($remoteInstance);

		$output->writeln('');
		$output->writeln('<error>Important note</error>: ');
		$output->writeln('Uploaded backup are encrypted which is good, don\'t you think ?');
		$output->writeln(
			'However, it also means that <options=bold>if you loose the Encryption Key, your backup will be totally useless</>'
		);
		$output->writeln('');
		$output->writeln('It is advised to export the setup of the Backup App in the file of your choice.');
		$output->writeln(
			'Keep in mind that with this file, any installation of Nextcloud can access your backup,'
		);
		$output->writeln('restore them and access the data of your users');
		$output->writeln(
			'While this is an option, ts is also advised to force the creation of a key to encrypt the content of the file:'
		);
		$output->writeln('');
		$output->writeln('   ./occ backup:setup:export [--key] > ~/backup_setup.json');
		$output->writeln('');
	}

}

