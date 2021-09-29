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


use ArtificialOwl\MySmallPhpTools\Exceptions\SignatoryException;
use ArtificialOwl\MySmallPhpTools\Exceptions\SignatureException;
use ArtificialOwl\MySmallPhpTools\Traits\Nextcloud\nc23\TNC23Deserialize;
use ArtificialOwl\MySmallPhpTools\Traits\TArrayTools;
use OC\Core\Command\Base;
use OCA\Backup\Exceptions\RemoteInstanceException;
use OCA\Backup\Exceptions\RemoteInstanceNotFoundException;
use OCA\Backup\Exceptions\RemoteResourceNotFoundException;
use OCA\Backup\Model\RemoteInstance;
use OCA\Backup\Model\RestoringHealth;
use OCA\Backup\Model\RestoringPoint;
use OCA\Backup\Service\PointService;
use OCA\Backup\Service\RemoteService;
use OCA\Backup\Service\RemoteStreamService;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;


/**
 * Class PointList
 *
 * @package OCA\Backup\Command
 */
class PointList extends Base {


	use TArrayTools;
	use TNC23Deserialize;


	/** @var PointService */
	private $pointService;

	/** @var RemoteService */
	private $remoteService;

	/** @var RemoteStreamService */
	private $remoteStreamService;


	/**
	 * PointList constructor.
	 *
	 * @param PointService $pointService
	 * @param RemoteService $remoteService
	 * @param RemoteStreamService $remoteStreamService
	 */
	public function __construct(
		PointService $pointService,
		RemoteService $remoteService,
		RemoteStreamService $remoteStreamService
	) {
		$this->pointService = $pointService;
		$this->remoteService = $remoteService;
		$this->remoteStreamService = $remoteStreamService;

		parent::__construct();
	}


	/**
	 *
	 */
	protected function configure() {
		$this->setName('backup:point:list')
			 ->setDescription('List restoring point');
	}


	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @return int
	 * @throws RemoteInstanceException
	 * @throws RemoteInstanceNotFoundException
	 * @throws RemoteResourceNotFoundException
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$instance = $this->selectInstanceToBrowse($input, $output);

		if ($instance === RemoteInstance::ALL) {
			$this->displayAllInstances($output);

			return 0;
		}

		$output->writeln('');
		$output->writeln('Available Restoring Point on <info>' . $instance . '</info>:');

		$points = $this->getRestoringPoints($instance, ($instance === RemoteInstance::LOCAL));

		$output = new ConsoleOutput();
		$output = $output->section();
		$table = new Table($output);
		$table->setHeaders(['Id', 'Parent', 'Date']);
		$table->render();

		foreach ($points as $point) {
			$table->appendRow(
				[
					$point->getId(),
					$point->getParent(),
					date('Y-m-d H:i:s', $point->getDate())
				]
			);
		}

//		echo 'RP: ' . json_encode($rp, JSON_PRETTY_PRINT);

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
					return $remoteInstance->getInstance();
				}, $this->remoteService->getOutgoing()
			)
		);

		$output->writeln('');
		$helper = $this->getHelper('question');
		$question = new ChoiceQuestion(
			'Which location to browse ?',
			array_merge([RemoteInstance::LOCAL], $remote, [RemoteInstance::ALL]),
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
	 * @throws RemoteInstanceException
	 * @throws RemoteInstanceNotFoundException
	 * @throws RemoteResourceNotFoundException
	 */
	private function getRestoringPoints(string $instance, bool $local = false): array {
		if ($local) {
			return $this->pointService->getRPLocal();
		}

		return $this->remoteService->getRestoringPoints($instance);
	}


	/**
	 *
	 */
	private function displayAllInstances(OutputInterface $output): void {
		$rp = $this->getRPFromAllInstances($output);

		$output = new ConsoleOutput();
		$output = $output->section();

		$table = new Table($output);
		$table->setHeaders(
			['Restoring Point', 'Date', 'Parent', 'Instance', 'Health']
		);
		$table->render();

		foreach ($rp as $pointId => $item) {
			$fresh = true;
			foreach ($item as $instance => $data) {
				$point = $data['rp'];
				$issue = $data['issue'];

				/** @var RestoringPoint $point */
				$displayPointId = $pointId;
				if ($point->getParent() === '') {
					$displayPointId = '<options=bold>' . $pointId . '</>';
				}

				$table->appendRow(
					[
						($fresh) ? $displayPointId : '',
						($fresh) ? date('Y-m-d H:i:s', $point->getDate()) : '',
						($fresh) ? $point->getParent() : '',
						$instance,
						$this->displayStyleHealth($point),
						'<error>' . $issue . '</error>'
					]
				);

				$fresh = false;
			}
		}
	}


	/**
	 * @param OutputInterface $output
	 *
	 * @return array
	 */
	private function getRPFromAllInstances(OutputInterface $output): array {
		$instances = array_merge(
			[RemoteInstance::LOCAL],
			array_map(
				function (RemoteInstance $remoteInstance): ?string {
					return $remoteInstance->getInstance();
				}, $this->remoteService->getOutgoing()
			)
		);

		$points = $dates = [];
		foreach ($instances as $instance) {
			$output->writeln('- retreiving data from <info>' . $instance . '</info>');

			try {
				if ($instance === RemoteInstance::LOCAL) {
					$list = $this->pointService->getRPLocal();
				} else {
					$list = $this->remoteService->getRestoringPoints($instance);
				}
			} catch (RemoteInstanceException
			| RemoteInstanceNotFoundException
			| RemoteResourceNotFoundException $e) {
				continue;
			}
			foreach ($list as $item) {
				$output->writeln(' > found RestoringPoint <info>' . $item->getId() . '</info>');
				if (!array_key_exists($item->getId(), $points)) {
					$points[$item->getId()] = [];
				}

				$issue = '';
				if ($instance !== RemoteInstance::LOCAL) {
					$storedDate = $this->getInt($item->getId(), $dates);
					if ($storedDate > 0 && $storedDate !== $item->getDate()) {
						$output->writeln('  <error>! different date</error>');
						$issue = 'different date';
					}

					try {
						$this->remoteStreamService->verifyPoint($item);
					} catch (SignatoryException | SignatureException $e) {
						$output->writeln('  <error>! cannot confirm integrity</error>');
						$issue = 'cannot confirm integrity';
					}
				}

				$points[$item->getId()][$instance] = [
					'rp' => $item,
					'issue' => $issue
				];

				$dates[$item->getId()] = $item->getDate();
			}
		}

		return $this->orderByDate($points, $dates);
	}


	/**
	 * @param array $points
	 * @param array $dates
	 *
	 * @return array
	 */
	private function orderByDate(array $points, array $dates): array {
		asort($dates);

		$result = [];
		foreach ($dates as $pointId => $date) {
			$result[$pointId] = $points[$pointId];
		}

		return $result;
	}


	/**
	 * @param RestoringPoint $point
	 *
	 * @return string
	 */
	private function displayStyleHealth(RestoringPoint $point): string {
		if ($point->getInstance() === '') {
			return 'not checked';
		}

		$status = $point->getHealth()->getStatus();
		$embed = '';
		switch ($status) {
			case RestoringHealth::STATUS_ISSUE:
				$embed = 'error';
				break;
			case RestoringHealth::STATUS_ORPHAN:
				$embed = 'comment';
				break;
			case RestoringHealth::STATUS_OK:
				$embed = 'info';
				break;
		}

		return '<' . $embed . '>' . RestoringHealth::$DEF[$status] . '</' . $embed . '>';
	}

}
