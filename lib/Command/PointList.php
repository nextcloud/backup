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

use ArtificialOwl\MySmallPhpTools\Exceptions\SignatoryException;
use ArtificialOwl\MySmallPhpTools\Exceptions\SignatureException;
use ArtificialOwl\MySmallPhpTools\Traits\TStringTools;
use OC\Core\Command\Base;
use OCA\Backup\Model\RemoteInstance;
use OCA\Backup\Model\RestoringData;
use OCA\Backup\Model\RestoringHealth;
use OCA\Backup\Model\RestoringPoint;
use OCA\Backup\Service\OutputService;
use OCA\Backup\Service\PointService;
use OCA\Backup\Service\RemoteStreamService;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class PointList
 *
 * @package OCA\Backup\Command
 */
class PointList extends Base {
	use TStringTools;


	/** @var PointService */
	private $pointService;

	/** @var OutputService */
	private $outputService;

	/** @var RemoteStreamService */
	private $remoteStreamService;


	/**
	 * PointList constructor.
	 *
	 * @param OutputService $outputService
	 * @param PointService $pointService
	 * @param RemoteStreamService $remoteStreamService
	 */
	public function __construct(
		OutputService $outputService,
		PointService $pointService,
		RemoteStreamService $remoteStreamService
	) {
		$this->outputService = $outputService;
		$this->pointService = $pointService;
		$this->remoteStreamService = $remoteStreamService;

		parent::__construct();
	}


	/**
	 *
	 */
	protected function configure() {
		$this->setName('backup:point:list')
			 ->setDescription('List restoring point')
			 ->addOption(
				 'local', '', InputOption::VALUE_NONE,
				 'list restoring point from local only'
			 )
			 ->addOption(
				 'remote', '', InputOption::VALUE_REQUIRED,
				 'list restoring point from a remote instance (or local)', ''
			 )
			 ->addOption(
				 'external', '', InputOption::VALUE_REQUIRED,
				 'list restoring point from an external folder', ''
			 );
	}


	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @return int
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$this->outputService->setOutput($output);
		$rp = $this->pointService->getRPFromInstances(
			$input->getOption('local'),
			$input->getOption('remote'),
			$input->getOption('external')
		);

		$output = new ConsoleOutput();
		$output = $output->section();

		$table = new Table($output);
		$table->setHeaders(
			['Restoring Point', 'Date', 'NC', 'Database', 'Parent', 'Comment', 'Status', 'Instance', 'Health']
		);
		$table->render();

		foreach ($rp as $pointId => $item) {
			$fresh = true;
			foreach ($item as $instance => $data) {
				$point = $data['point'];
				$issue = $data['issue'];

				/** @var RestoringPoint $point */
				$displayPointId = $pointId;
				if ($point->getParent() === '') {
					$displayPointId = '<options=bold>' . $pointId . '</>';
				}

				$status = [];
				if ($point->getStatus() === 0) {
					$status[] = 'not packed';
				} else {
					foreach (RestoringPoint::$DEF_STATUS as $k => $v) {
						if ($point->isStatus($k)) {
							$status[] = $v;
						}
					}
				}

				$comment = $point->getComment();
				try {
					$this->remoteStreamService->verifySubSign($point);
				} catch (SignatoryException | SignatureException $e) {
					$comment = '';
				}

				$table->appendRow(
					[
						'<comment>' . (($point->isLocked()) ? 'L' : '') .
						(($point->isArchive()) ? 'A' : '') . '</comment> '
						. (($fresh) ? $displayPointId : ''),
						($fresh) ? date('Y-m-d H:i:s', $point->getDate()) : '',
						($fresh) ? $point->getNCVersion() : '',
						($fresh) ? $this->getDatabaseType($point) : '',
						($fresh) ? $point->getParent() : '',
						$comment,
						implode(',', $status),
						($instance === RemoteInstance::LOCAL) ? '<info>' . $instance . '</info>' : $instance,
						$this->displayStyleHealth($point),
						'<error>' . $issue . '</error>'
					]
				);

				$fresh = false;
			}
		}

		return 0;
	}


	/**
	 * @param RestoringPoint $point
	 *
	 * @return string
	 */
	private function displayStyleHealth(RestoringPoint $point): string {
		if (!$point->hasHealth()) {
			return 'not checked';
		}

		$embed = '';
		$health = $point->getHealth();
		$def = RestoringHealth::$DEF[$health->getStatus()];
		switch ($health->getStatus()) {
			case RestoringHealth::STATUS_ISSUE:
				$embed = 'error';
				break;
			case RestoringHealth::STATUS_ORPHAN:
				$embed = 'comment';
				break;
			case RestoringHealth::STATUS_OK:
				$embed = 'info';

				$def = $this->getDateDiff($health->getChecked(), time(), true) . ' ago';
				break;
		}

		return '<' . $embed . '>' . $def . '</' . $embed . '>';
	}


	/**
	 * @param RestoringPoint $point
	 *
	 * @return string
	 */
	private function getDatabaseType(RestoringPoint $point): string {
		foreach ($point->getRestoringData() as $data) {
			if ($data->getType() === RestoringData::FILE_SQL_DUMP) {
				$chunks = $data->getChunks();
				if (sizeof($chunks) === 1) {
					$chunk = array_shift($chunks);

					return $chunk->getType();
				}
			}
		}

		return 'sqlite';
	}
}
