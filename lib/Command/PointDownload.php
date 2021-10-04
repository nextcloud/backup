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
use OC\Core\Command\Base;
use OCA\Backup\Db\PointRequest;
use OCA\Backup\Exceptions\RemoteInstanceException;
use OCA\Backup\Exceptions\RemoteInstanceNotFoundException;
use OCA\Backup\Exceptions\RemoteResourceNotFoundException;
use OCA\Backup\Exceptions\RestoringChunkNotFoundException;
use OCA\Backup\Exceptions\RestoringPointNotFoundException;
use OCA\Backup\Model\RestoringChunkHealth;
use OCA\Backup\Model\RestoringHealth;
use OCA\Backup\Model\RestoringPoint;
use OCA\Backup\Service\ChunkService;
use OCA\Backup\Service\OutputService;
use OCA\Backup\Service\PointService;
use OCA\Backup\Service\RemoteService;
use OCA\Backup\Service\RemoteStreamService;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


/**
 * Class PointDownload
 *
 * @package OCA\Backup\Command
 */
class PointDownload extends Base {


	/** @var PointRequest */
	private $pointRequest;

	/** @var PointService */
	private $pointService;

	/** @var ChunkService */
	private $chunkService;

	/** @var RemoteStreamService */
	private $remoteStreamService;

	/** @var RemoteService */
	private $remoteService;

	/** @var OutputService */
	private $outputService;


	/**
	 * PointDownload constructor.
	 *
	 * @param PointRequest $pointRequest
	 * @param PointService $pointService
	 * @param ChunkService $chunkService
	 * @param RemoteStreamService $remoteStreamService
	 * @param RemoteService $remoteService
	 * @param OutputService $outputService
	 */
	public function __construct(
		PointRequest $pointRequest,
		PointService $pointService,
		ChunkService $chunkService,
		RemoteStreamService $remoteStreamService,
		RemoteService $remoteService,
		OutputService $outputService
	) {
		parent::__construct();

		$this->pointRequest = $pointRequest;
		$this->pointService = $pointService;
		$this->chunkService = $chunkService;
		$this->remoteStreamService = $remoteStreamService;
		$this->remoteService = $remoteService;
		$this->outputService = $outputService;
	}


	/**
	 *
	 */
	protected function configure() {
		$this->setName('backup:point:download')
			 ->setDescription('Download restoring point from remote instance')
			 ->addArgument('instance', InputArgument::REQUIRED, 'address of the remote instance')
			 ->addArgument('pointId', InputArgument::REQUIRED, 'Id of the restoring point')
			 ->addOption('no-check', '', InputOption::VALUE_NONE, 'do not check integrity of restoring point');
	}


	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws RemoteInstanceException
	 * @throws RemoteInstanceNotFoundException
	 * @throws RemoteResourceNotFoundException
	 * @throws RestoringChunkNotFoundException
	 * @throws RestoringPointNotFoundException
	 * @throws SignatureException
	 * @throws SignatoryException
	 */
	protected function execute(InputInterface $input, OutputInterface $output) {
		$instance = $input->getArgument('instance');
		$pointId = $input->getArgument('pointId');

		try {
			$point = $this->pointService->getRestoringPoint($pointId);
			$output->writeln('> found a local restoring point');
		} catch (RestoringPointNotFoundException $e) {
			$output->writeln('> downloading metadata');

			$point = $this->remoteService->getRestoringPoint($instance, $pointId);
			if (!$input->getOption('no-check')) {
				try {
					$this->remoteStreamService->verifyPoint($point);
				} catch (SignatureException $e) {
					throw new SignatureException(
						'Cannot confirm restoring point integrity.' . "\n"
						. 'You can bypass this verification using --no-check'
					);
				}
			}

			$point->unsetHealth()
				  ->setInstance('');

			$this->pointRequest->save($point);
			$this->pointService->saveMetadata($point);
		}


		$output->write('check health status: ');
		$this->pointService->generateHealth($point);
		$output->writeln($this->outputService->displayHealth($point));
		$this->downloadMissingFiles($instance, $point, $point->getHealth(), $output);


//		echo json_encode($point->getHealth());

//		$checks = $this->remoteService->verifyPoint($point);
//
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
	 * @param string $instance
	 * @param RestoringPoint $point
	 * @param RestoringHealth $health
	 * @param OutputInterface $output
	 *
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws RemoteInstanceException
	 * @throws RemoteInstanceNotFoundException
	 * @throws RemoteResourceNotFoundException
	 * @throws RestoringChunkNotFoundException
	 */
	private function downloadMissingFiles(
		string $instance,
		RestoringPoint $point,
		RestoringHealth $health,
		OutputInterface $output
	): void {
		foreach ($health->getChunks() as $chunk) {
			if ($chunk->getStatus() === RestoringChunkHealth::STATUS_OK) {
				continue;
			}

			$output->write('  * Downloading ' . $chunk->getDataName() . '/' . $chunk->getChunkName() . ': ');
			$restoringChunk = $this->pointService->getChunkContent(
				$point,
				$chunk->getDataName(),
				$chunk->getChunkName()
			);

			$chunk = $this->remoteService->downloadChunk($instance, $point, $restoringChunk);
			$this->chunkService->saveChunkContent($point, $chunk);
			$output->writeln('<info>ok</info>');
		}
	}
}

