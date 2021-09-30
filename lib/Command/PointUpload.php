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
use ArtificialOwl\MySmallPhpTools\Exceptions\SignatoryException;
use Exception;
use OC\Core\Command\Base;
use OCA\Backup\Exceptions\RemoteInstanceException;
use OCA\Backup\Exceptions\RemoteInstanceNotFoundException;
use OCA\Backup\Exceptions\RemoteResourceNotFoundException;
use OCA\Backup\Exceptions\RestorationPointUploadException;
use OCA\Backup\Exceptions\RestoringChunkNotFoundException;
use OCA\Backup\Exceptions\RestoringPointNotFoundException;
use OCA\Backup\Model\RestoringChunkHealth;
use OCA\Backup\Model\RestoringHealth;
use OCA\Backup\Model\RestoringPoint;
use OCA\Backup\Service\OutputService;
use OCA\Backup\Service\PointService;
use OCA\Backup\Service\RemoteService;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


/**
 * Class PointUpload
 *
 * @package OCA\Backup\Command
 */
class PointUpload extends Base {


	/** @var PointService */
	private $pointService;

	/** @var RemoteService */
	private $remoteService;

	/** @var OutputService */
	private $occService;


	/**
	 * PointUpload constructor.
	 *
	 * @param PointService $pointService
	 * @param RemoteService $remoteService
	 * @param OutputService $occService
	 */
	public function __construct(
		PointService $pointService,
		RemoteService $remoteService,
		OutputService $occService
	) {
		parent::__construct();

		$this->pointService = $pointService;
		$this->remoteService = $remoteService;
		$this->occService = $occService;
	}


	/**
	 *
	 */
	protected function configure() {
		$this->setName('backup:point:upload')
			 ->setDescription('Upload a local restoring point on others instances')
			 ->addArgument('point', InputArgument::REQUIRED, 'Id of the restoring point');
	}


	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @throws RestoringPointNotFoundException
	 */
	protected function execute(InputInterface $input, OutputInterface $output) {
		$point = $this->pointService->getLocalRestoringPoint($input->getArgument('point'));

		$this->verifyPointFromInstances($output, $point);
	}


	/**
	 * @param OutputInterface $output
	 * @param RestoringPoint $point
	 */
	private function verifyPointFromInstances(OutputInterface $output, RestoringPoint $point): void {
		foreach ($this->remoteService->getOutgoing() as $remoteInstance) {
			$instance = $remoteInstance->getInstance();
			$output->writeln('');
			$output->writeln('- checking remote instance <info>' . $instance . '</info>');
			try {
				$stored = $this->remoteService->getRestoringPoint($instance, $point->getId());
				$output->writeln('  > restoring point found');
			} catch (RemoteInstanceException $e) {
				$output->writeln('  ! <error>check configuration on remote instance</error>');
				continue;
			} catch (
			RemoteInstanceNotFoundException
			| RemoteResourceNotFoundException $e) {
				$output->writeln('  ! <error>cannot communicate with remote instance</error>');
				continue;
			} catch (RestoringPointNotFoundException $e) {
				$output->writeln('  > <comment>restoring point not found</comment>');
				try {
					$stored = $this->createRemotePoint($output, $point, $instance);
					$output->writeln('  > restoring point created');
				} catch (Exception $e) {
					$output->writeln('  ! <error>cannot create restoring point</error>');
					continue;
				}
			}

			if (!$stored->hasHealth()) {
				try {
					$output->writeln('  > <comment>no health status attached</comment>');
					$stored = $this->getCurrentHealth($output, $point, $instance);
				} catch (Exception $e) {
					continue;
				}
			}

			$health = $stored->getHealth();
			$output->writeln('  > Health status: ' . $this->occService->displayHealth($stored));
			$this->uploadMissingFiles($instance, $point, $health, $output);

//			echo $output->writeln('  * '$stored->getId();
		}


//		foreach ($checks as $instance => $item) {
//			$output->writeln('');
//			$output->writeln('- <info>' . $instance . '</info>: ' . $this->displayHealth($item));
//
//			if (is_null($item)) {
//				$item = $this->createRemotePoint($instance, $point, $output);
//			}
//
//			if (is_null($item)) {
//				continue;
//			}
//
//			if (!$item->hasHealth()) {
//				$item = $this->getCurrentHealth($instance, $item, $output);
//				if ($item !== null && $item->hasHealth()) {
//					$output->write('  * Refreshed health status:' . $this->displayHealth($item));
//				} else {
//					continue;
//				}
//			}
//
//			$health = $item->getHealth();
//			$this->uploadMissingFiles($instance, $point, $health, $output);
//			if ($health->getStatus() === RestoringHealth::STATUS_OK) {
//				$output->writeln('  > RestoringPoint is fully uploaded to ' . $instance);
//			}
//
//		}
	}

	/**
	 * @param OutputInterface $output
	 *
	 * @param RestoringPoint $point
	 * @param string $instance
	 *
	 * @return RestoringPoint
	 * @throws InvalidItemException
	 * @throws RemoteInstanceException
	 * @throws RemoteInstanceNotFoundException
	 * @throws RemoteResourceNotFoundException
	 * @throws SignatoryException
	 */
	private function createRemotePoint(
		OutputInterface $output,
		RestoringPoint $point,
		string $instance
	): RestoringPoint {
		$output->write('  * Creating Restoring Point on remote instance: ');

		try {
			$stored = $this->remoteService->createPoint($instance, $point);
			$output->writeln('<info>ok</info>');

			return $stored;
		} catch (SignatoryException
		| InvalidItemException
		| RemoteInstanceException
		| RemoteInstanceNotFoundException
		| RemoteResourceNotFoundException $e) {
			$output->writeln('<error>' . $e->getMessage() . '</error>');
			throw $e;
		}
	}


	/**
	 * @param OutputInterface $output
	 *
	 * @param RestoringPoint $point
	 * @param string $instance
	 *
	 * @return RestoringPoint
	 * @throws RemoteInstanceException
	 * @throws RemoteInstanceNotFoundException
	 * @throws RemoteResourceNotFoundException
	 * @throws RestoringPointNotFoundException
	 * @throws RestorationPointUploadException
	 */
	private function getCurrentHealth(
		OutputInterface $output,
		RestoringPoint $point,
		string $instance
	): RestoringPoint {
		$output->write('  * Requesting detailed Health status: ');

		try {
			$stored = $this->remoteService->getRestoringPoint($instance, $point->getId(), true);

			if (!$stored->hasHealth()) {
				throw new RestorationPointUploadException('no health status attached');
			}
			$output->writeln('<info>ok</info>');

			return $stored;
		} catch (RestoringPointNotFoundException
		| RemoteInstanceException
		| RemoteInstanceNotFoundException
		| RemoteResourceNotFoundException $e) {
			$output->writeln('<error>' . $e->getMessage() . '</error>');
			throw $e;
		}
	}


	private function uploadMissingFiles(
		string $instance,
		RestoringPoint $point,
		RestoringHealth $health,
		OutputInterface $output
	): void {
		foreach ($health->getChunks() as $chunk) {
			if ($chunk->getStatus() === RestoringChunkHealth::STATUS_OK) {
				continue;
			}

			$output->write('  * Uploading ' . $chunk->getDataName() . '/' . $chunk->getChunkName() . ': ');
			try {
				$restoringChunk = $this->pointService->getChunkContent(
					$point,
					$chunk->getDataName(),
					$chunk->getChunkName()
				);
				$this->remoteService->uploadChunk($instance, $point, $restoringChunk);
				$output->writeln('<info>ok</info>');
			} catch (
			RestoringChunkNotFoundException
			| NotFoundException
			| NotPermittedException
			| RemoteInstanceException
			| RemoteInstanceNotFoundException
			| RemoteResourceNotFoundException $e) {
				$output->writeln('<error>' . $e->getMessage() . '</error>');
			}

		}
	}
}

