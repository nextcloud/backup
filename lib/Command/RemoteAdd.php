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
use OCA\Backup\Db\RemoteRequest;
use OCA\Backup\Exceptions\RemoteInstanceDuplicateException;
use OCA\Backup\Exceptions\RemoteInstanceException;
use OCA\Backup\Exceptions\RemoteInstanceNotFoundException;
use OCA\Backup\Exceptions\RemoteInstanceUidException;
use OCA\Backup\Model\RemoteInstance;
use OCA\Backup\Service\ConfigService;
use OCA\Backup\Service\RemoteStreamService;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * Class RemoteAdd
 *
 * @package OCA\Backup\Command
 */
class RemoteAdd extends Base {
	use TNC23WellKnown;


	/** @var RemoteRequest */
	private $remoteRequest;

	/** @var RemoteStreamService */
	private $remoteStreamService;

	/** @var ConfigService */
	private $configService;


	/**
	 * RemoteAdd constructor.
	 *
	 * @param RemoteRequest $remoteRequest
	 * @param RemoteStreamService $remoteStreamService
	 * @param ConfigService $configService
	 */
	public function __construct(
		RemoteRequest $remoteRequest,
		RemoteStreamService $remoteStreamService,
		ConfigService $configService
	) {
		$this->remoteRequest = $remoteRequest;
		$this->remoteStreamService = $remoteStreamService;
		$this->configService = $configService;

		parent::__construct();
	}


	/**
	 *
	 */
	protected function configure() {
		if (!$this->configService->isRemoteEnabled()) {
			$this->setHidden(true);
		}

		$this->setName('backup:remote:add')
			 ->setDescription('Add remote instances to store your backups')
			 ->addArgument('address', InputArgument::REQUIRED, 'address of the remote instance of Nextcloud');
	}


	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @return int
	 * @throws RequestNetworkException
	 * @throws SignatureException
	 * @throws WellKnownLinkNotFoundException
	 * @throws SignatoryException
	 * @throws RemoteInstanceUidException
	 * @throws RemoteInstanceDuplicateException
	 * @throws RemoteInstanceException
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		if (!$this->configService->isRemoteEnabled()) {
			throw new RemoteInstanceException('not enabled');
		}

		$address = $input->getArgument('address');
		if (strtolower($address) === RemoteInstance::LOCAL || strtolower($address) === RemoteInstance::ALL) {
			throw new RemoteInstanceException($address . ' is a reserved name');
		}

		$resource = $this->getCurrentResourceFromAddress($output, $address);
		$this->remoteStreamService->getAppSignatory();

		$knownInstance = null;
		try {
			$knownInstance = $this->remoteRequest->getFromHref($resource->g('id'));
		} catch (RemoteInstanceNotFoundException $e) {
		}

		try {
			/** @var RemoteInstance $remoteSignatory */
			$remoteSignatory = $this->remoteStreamService->retrieveSignatory($resource->g('id'), true);
		} catch (SignatureException $e) {
			throw new SignatureException($address . ' cannot auth its identity: ' . $e->getMessage());
		}

		try {
			$duplicateInstance = $this->remoteRequest->getByInstance($address);
			if ($duplicateInstance->getId() !== $remoteSignatory->getId()) {
				throw new RemoteInstanceDuplicateException(
					'There is already a known instance with same ADDRESS but different HREF. Please remove it first!'
				);
			}
		} catch (RemoteInstanceNotFoundException $e) {
		}

		$remoteSignatory->setInstance($address);
		if (!is_null($knownInstance)) {
			if ($remoteSignatory->getInstance() !== $knownInstance->getInstance()) {
				throw new RemoteInstanceDuplicateException(
					'There is already a known instance with same HREF but different ADDRESS ('
					. $knownInstance->getInstance() . '). Please remove it first!'
				);
			}

			if ($remoteSignatory->getUid(true) !== $knownInstance->getUid()) {
				$output->writeln('');
				$output->writeln('<error>### WARNING ###</error>');
				$output->writeln(
					'<error>The instance on ' . $knownInstance->getInstance()
					. ' is already known under an other identity!</error>'
				);
				$output->writeln('<error>### WARNING ###</error>');

				$output->writeln('');
				$output->writeln('Continue with this process if you want to store the new identity.');
				$output->writeln('Doing so (and based on the given outgoing/incoming rights): ');
				$output->writeln('  - the remote instance with the old identity will loose access to');
				$output->writeln('    this service,');
				$output->writeln('  - the remote instance with the new identity will gain access to');
				$output->writeln(
					'    <options=underscore>all backups previously uploaded</> by the previous instance,'
				);
				$output->writeln(
					'  - your instance will now <options=underscore>upload your local backups</> to this new'
				);
				$output->writeln('    remote instance,');
				$output->writeln('  - your instance will not be able to browse any backup on the old');
				$output->writeln('    remote instance,');
				$output->writeln('  - your instance might be able to get access to previous uploaded');
				$output->writeln('    backup if available on the new remote instance');
				$output->writeln('');
				$output->writeln(
					'<error>Please CONFIRM with an Administrator from the remote instance before updating any identity.</error>'
				);
				$helper = $this->getHelper('question');
				$question = new ConfirmationQuestion(
					'Are you sure you want to continue with the process ? (y/N)',
					false,
					'/^(y|Y)/i'
				);

				if (!$helper->ask($input, $output, $question)) {
					return 0;
				}
			}
		}

		$this->configureRemoteInstance($input, $output, $remoteSignatory, $knownInstance);
		$this->saveRemoteInstance($input, $output, $remoteSignatory);

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
