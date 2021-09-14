<?php

declare(strict_types=1);


/**
 * Nextcloud - Backup
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


use ArtificialOwl\MySmallPhpTools\Exceptions\InvalidItemException;
use ArtificialOwl\MySmallPhpTools\Model\Request;
use ArtificialOwl\MySmallPhpTools\Traits\Nextcloud\nc23\TNC23Deserialize;
use OC\Core\Command\Base;
use OCA\Backup\Db\RemoteRequest;
use OCA\Backup\Exceptions\RemoteInstanceException;
use OCA\Backup\Exceptions\RemoteInstanceNotFoundException;
use OCA\Backup\Exceptions\RemoteResourceNotFoundException;
use OCA\Backup\Model\RemoteInstance;
use OCA\Backup\Model\RestoringPoint;
use OCA\Backup\Service\RemoteStreamService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;


/**
 * Class RemoteList
 *
 * @package OCA\Backup\Command
 */
class PointBrowse extends Base {


	use TNC23Deserialize;


	/** @var RemoteRequest */
	private $remoteRequest;

	/** @var RemoteStreamService */
	private $remoteStreamService;


	public function __construct(RemoteRequest $remoteRequest, RemoteStreamService $remoteStreamService) {
		$this->remoteRequest = $remoteRequest;
		$this->remoteStreamService = $remoteStreamService;

		parent::__construct();
	}


	/**
	 *
	 */
	protected function configure() {
		$this->setName('backup:point:browse')
			 ->setDescription('Browse restoring point');
	}


	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @return int
	 * @throws RemoteInstanceException
	 * @throws RemoteInstanceNotFoundException
	 * @throws RemoteResourceNotFoundException
	 * @throws InvalidItemException
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$instance = $this->selectInstanceToBrowse($input, $output);

		$output->writeln('');
		$output->writeln('Browsing available Restoring Point on <info>' . $instance . '</info>');

		$rp = $this->getRestoringPoints($instance, ($instance === RemoteInstance::LOCAL));
		echo 'RP: ' . json_encode($rp, JSON_PRETTY_PRINT);

		return 0;
	}


	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @return string
	 */
	private function selectInstanceToBrowse(InputInterface $input, OutputInterface $output): string {
		$remote = array_filter(
			array_map(
				function (RemoteInstance $remoteInstance): ?string {
					if (!$remoteInstance->isOutgoing()) {
						return null;
					}

					return $remoteInstance->getInstance();
				}, $this->remoteRequest->getAll()
			)
		);

		$output->writeln('');
		$helper = $this->getHelper('question');
		$question = new ChoiceQuestion(
			'Which location to browse ?',
			array_merge([RemoteInstance::LOCAL], $remote),
			0
		);
		$question->setErrorMessage('Instance %s is not known.');

		return $helper->ask($input, $output, $question);
	}


	/**
	 * @param string $instance
	 * @param bool $local
	 *
	 * @return RestoringPoint[]
	 * @throws RemoteInstanceNotFoundException
	 * @throws RemoteInstanceException
	 * @throws RemoteResourceNotFoundException
	 * @throws InvalidItemException
	 */
	private function getRestoringPoints(string $instance, bool $local = false): array {
		if ($local) {
			return [];
		}

		$result = $this->remoteStreamService->resultRequestRemoteInstance(
			$instance,
			RemoteInstance::RP_LIST,
			Request::TYPE_GET
		);

	return 	$this->deserializeArray($result, RestoringPoint::class);
	}

}
