<?php

declare(strict_types=1);


/**
 * Nextcloud - Backup
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
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


use ArtificialOwl\MySmallPhpTools\Exceptions\InvalidItemException;
use OC\Core\Command\Base;
use OCA\Backup\Exceptions\RemoteInstanceException;
use OCA\Backup\Exceptions\RemoteInstanceNotFoundException;
use OCA\Backup\Exceptions\RemoteResourceNotFoundException;
use OCA\Backup\Model\RestoringChunkHealth;
use OCA\Backup\Model\RestoringHealth;
use OCA\Backup\Model\RestoringPoint;
use OCA\Backup\Service\ConfigService;
use OCA\Backup\Service\RemoteService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


/**
 * Class SetupExport
 *
 * @package OCA\Backup\Command
 */
class SetupExport extends Base {


	/** @var RemoteService */
	private $remoteService;

	/** @var ConfigService */
	private $configService;


	/**
	 * SetupExport constructor.
	 *
	 * @param RemoteService $remoteService
	 * @param ConfigService $configService
	 */
	public function __construct(RemoteService $remoteService, ConfigService $configService) {
		parent::__construct();

		$this->configService = $configService;
		$this->remoteService = $remoteService;
	}


	/**
	 *
	 */
	protected function configure() {
		$this->setName('backup:setup:export')
			 ->setDescription('Export your setup for easier restoration');
	}


	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 */
	protected function execute(InputInterface $input, OutputInterface $output) {
		$setup = [
			'signatory' => $this->configService->getAppValue('key_pairs'),
			'remote' => $this->remoteService->getAll()
		];

		$output->writeln(json_encode($setup, JSON_PRETTY_PRINT));
	}


	/**
	 * @param string $instance
	 * @param RestoringPoint $point
	 * @param OutputInterface $output
	 *
	 * @return RestoringPoint
	 */
	private function createRemotePoint(
		string $instance,
		RestoringPoint $point,
		OutputInterface $output
	): ?RestoringPoint {
		$output->write('  * Creating Restoring Point on remote instance: ');

		try {
			$stored = $this->remoteService->createPoint($instance, $point);
			$output->writeln('<info>ok</info>');

			return $stored;
		} catch (InvalidItemException
		| RemoteInstanceException
		| RemoteInstanceNotFoundException
		| RemoteResourceNotFoundException $e) {
			$output->writeln('<error>' . $e->getMessage() . '</error>');
		}

		return null;
	}


	/**
	 * @param string $instance
	 * @param RestoringPoint $point
	 * @param OutputInterface $output
	 *
	 * @return RestoringPoint|null
	 */
	private function getCurrentHealth(
		string $instance,
		RestoringPoint $point,
		OutputInterface $output
	): ?RestoringPoint {
		$output->write('  * Generating Health Status on remote instance: ');

		try {
			$stored = $this->remoteService->verifyRemotePoint($instance, $point, true);

			if (is_null($stored)) {
				$output->writeln('<error>RestoringPoint not found on remote instance</error>');
			} else {
				if (!$stored->hasHealth()) {
					$output->writeln('<error>no health status attached</error>');
				} else {
					$output->writeln('<info>ok</info>');
				}
			}

			return $stored;
		} catch
		(RemoteInstanceException
		| RemoteInstanceNotFoundException
		| RemoteResourceNotFoundException $e) {
			$output->writeln('<error>' . $e->getMessage() . '</error>');
		}

		return null;
	}


	/**
	 * @param RestoringPoint|null $point
	 *
	 * @return string
	 */
	private function displayHealth(?RestoringPoint $point): string {
		if (is_null($point)) {
			return '<comment>not found</comment>';
		}

		if (!$point->hasHealth()) {
			return '<error>unknown health status</error>';
		}

		$health = $point->getHealth();
		if ($health->getStatus() === RestoringHealth::STATUS_OK) {
			return '<info>ok</info>';
		}

		$chunks = $health->getChunks();
		$unknown = $good = $missing = $faulty = 0;
		foreach ($chunks as $chunk) {
			switch ($chunk->getStatus()) {
				case RestoringChunkHealth::STATUS_UNKNOWN:
					$unknown++;
					break;
				case RestoringChunkHealth::STATUS_OK:
					$good++;
					break;
				case RestoringChunkHealth::STATUS_MISSING:
					$missing++;
					break;
				case RestoringChunkHealth::STATUS_CHECKSUM:
					$faulty++;
					break;
			}
		}

		return '<comment>'
			   . $good . ' uploaded, '
			   . $missing . ' missing and '
			   . $faulty . ' faulty files</comment>';
	}


	private function uploadMissingFiles(
		string $instance,
		RestoringPoint $point,
		RestoringHealth $health,
		OutputInterface $output
	): void {
		$chunks = $health->getChunks();
		foreach ($chunks as $chunk) {
			if ($chunk->getStatus() === RestoringChunkHealth::STATUS_OK) {
				continue;
			}

			$output->write('  * Uploading ' . $chunk->getDataName() . '/' . $chunk->getChunkName() . ': ');
			$restoringChunk =
				$this->pointService->getChunkContent($point, $chunk->getDataName(), $chunk->getChunkName());
			$this->remoteService->uploadChunk($instance, $point, $restoringChunk);
			$output->writeln('<info>ok</info>');

		}
	}
}

