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
use OCA\Backup\Exceptions\ExternalFolderNotFoundException;
use OCA\Backup\Exceptions\RemoteInstanceException;
use OCA\Backup\Exceptions\RemoteInstanceNotFoundException;
use OCA\Backup\Exceptions\RemoteResourceNotFoundException;
use OCA\Backup\Exceptions\RestoringChunkNotFoundException;
use OCA\Backup\Exceptions\RestoringChunkPartNotFoundException;
use OCA\Backup\Exceptions\RestoringPointException;
use OCA\Backup\Exceptions\RestoringPointNotFoundException;
use OCA\Backup\Exceptions\RestoringPointNotInitiatedException;
use OCA\Backup\Exceptions\RestoringPointPackException;
use OCA\Backup\Model\ChunkPartHealth;
use OCA\Backup\Model\RestoringPoint;
use OCA\Backup\Service\ChunkService;
use OCA\Backup\Service\ConfigService;
use OCA\Backup\Service\ExternalFolderService;
use OCA\Backup\Service\OutputService;
use OCA\Backup\Service\PackService;
use OCA\Backup\Service\PointService;
use OCA\Backup\Service\RemoteService;
use OCA\Backup\Service\RemoteStreamService;
use OCP\Files\GenericFileException;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Lock\LockedException;
use Symfony\Component\Console\Exception\InvalidOptionException;
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

	/** @var PackService */
	private $packService;

	/** @var RemoteStreamService */
	private $remoteStreamService;

	/** @var RemoteService */
	private $remoteService;

	/** @var ExternalFolderService */
	private $externalFolderService;

	/** @var OutputService */
	private $outputService;

	/** @var ConfigService */
	private $configService;


	/**
	 * PointDownload constructor.
	 *
	 * @param PointRequest $pointRequest
	 * @param PointService $pointService
	 * @param ChunkService $chunkService
	 * @param PackService $packService
	 * @param RemoteStreamService $remoteStreamService
	 * @param RemoteService $remoteService
	 * @param ExternalFolderService $externalFolderService
	 * @param OutputService $outputService
	 * @param ConfigService $configService
	 */
	public function __construct(
		PointRequest $pointRequest,
		PointService $pointService,
		ChunkService $chunkService,
		PackService $packService,
		RemoteStreamService $remoteStreamService,
		RemoteService $remoteService,
		ExternalFolderService $externalFolderService,
		OutputService $outputService,
		ConfigService $configService
	) {
		parent::__construct();

		$this->pointRequest = $pointRequest;
		$this->pointService = $pointService;
		$this->chunkService = $chunkService;
		$this->packService = $packService;
		$this->remoteStreamService = $remoteStreamService;
		$this->remoteService = $remoteService;
		$this->externalFolderService = $externalFolderService;
		$this->outputService = $outputService;
		$this->configService = $configService;
	}


	/**
	 *
	 */
	protected function configure() {
		$this->setName('backup:point:download')
			 ->setDescription('Download restoring point from remote instance')
			 ->addArgument('pointId', InputArgument::REQUIRED, 'Id of the restoring point')
			 ->addOption('remote', '', InputOption::VALUE_REQUIRED, 'address of the remote instance')
			 ->addOption('external', '', InputOption::VALUE_REQUIRED, 'storageId of the external storage')
			 ->addOption(
				 'no-check', '', InputOption::VALUE_NONE, 'do not check integrity of restoring point'
			 );
	}


	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @return int
	 * @throws ExternalFolderNotFoundException
	 * @throws GenericFileException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws RemoteInstanceException
	 * @throws RemoteInstanceNotFoundException
	 * @throws RemoteResourceNotFoundException
	 * @throws RestoringChunkNotFoundException
	 * @throws RestoringChunkPartNotFoundException
	 * @throws RestoringPointException
	 * @throws RestoringPointNotFoundException
	 * @throws RestoringPointPackException
	 * @throws SignatoryException
	 * @throws SignatureException
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$pointId = $input->getArgument('pointId');
		$remote = $input->getOption('remote');
		$external = (int)$input->getOption('external');

		if (!$remote && !$external) {
			$msg = 'use --external';
			if ($this->configService->isRemoteEnabled()) {
				$msg = 'use --remote or --external';
			}
			throw new InvalidOptionException($msg);
		}

		try {
			$point = $this->pointService->getRestoringPoint($pointId);
			$output->writeln('> found a local restoring point');
		} catch (RestoringPointNotFoundException $e) {
			$output->writeln('> downloading metadata');

			$point = $this->getRestoringPoint($remote, $external, $pointId);
//			$point = $this->remoteService->getRestoringPoint($instance, $pointId);
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

			$point->setInstance()
				  ->unsetHealth();

			$this->pointRequest->save($point);
			$this->pointService->saveMetadata($point);
		}

		$this->metadataService->isLock($point);
		$this->metadataService->lock($point);
		$output->write('check health status: ');
		$this->pointService->generateHealth($point);
		$output->writeln($this->outputService->displayHealth($point->getHealth()));
		$this->downloadMissingFiles($output, $remote, $external, $point);

		$this->pointService->generateHealth($point);

		// set Archive flag up after download
		$point->setArchive(true);
		$this->remoteStreamService->subSignPoint($point);

		$this->pointRequest->update($point);
		$this->pointService->saveMetadata($point);
		$this->metadataService->unlock($point);

		return 0;
//		$this->downloadMissingFiles($instance, $point, $point->getHealth(), $output);

//		$point = $this->getRestoringPoint($remote, $external, $pointId);


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
	 * @param OutputInterface $output
	 * @param string|null $remote
	 * @param int|null $external
	 * @param RestoringPoint $point
	 *
	 * @throws ExternalFolderNotFoundException
	 * @throws GenericFileException
	 * @throws NotPermittedException
	 * @throws RemoteInstanceException
	 * @throws RemoteInstanceNotFoundException
	 * @throws RemoteResourceNotFoundException
	 * @throws RestoringChunkNotFoundException
	 * @throws RestoringChunkPartNotFoundException
	 * @throws RestoringPointException
	 * @throws RestoringPointNotFoundException
	 * @throws RestoringPointNotInitiatedException
	 * @throws LockedException
	 */
	private function downloadMissingFiles(
		OutputInterface $output,
		?string $remote,
		?int $external,
		RestoringPoint $point
	): void {
		$health = $point->getHealth();
		foreach ($health->getParts() as $partHealth) {
			if ($partHealth->getStatus() === ChunkPartHealth::STATUS_OK) {
				continue;
			}

			$output->write(
				'  * Downloading ' . $partHealth->getDataName() .
				'/' . $partHealth->getChunkName() . '/' . $partHealth->getPartName() . ': '
			);

			$chunk = $this->chunkService->getChunkFromRP(
				$point,
				$partHealth->getChunkName(),
				$partHealth->getDataName()
			);

			$part = clone $this->packService->getPartFromChunk($chunk, $partHealth->getPartName());

			if (!is_null($remote)) {
				$this->remoteService->downloadPart($remote, $point, $chunk, $part);
			} elseif ($external > 0) {
				$externalFolder = $this->externalFolderService->getByStorageId($external);
				$this->externalFolderService->downloadPart(
					$externalFolder,
					$point,
					$chunk,
					$part
				);
			} else {
				$msg = 'use --external';
				if ($this->configService->isRemoteEnabled()) {
					$msg = 'use --remote or --external';
				}
				throw new InvalidOptionException($msg);
			}

//			$chunk = $this->remoteService->downloadChunk($instance, $point, $restoringChunk);
			$this->packService->saveChunkPartContent($point, $chunk, $part);
			$output->writeln('<info>ok</info>');
		}
	}


	/**
	 * @param string|null $remote
	 * @param int|null $external
	 * @param string $pointId
	 *
	 * @return RestoringPoint
	 * @throws NotPermittedException
	 * @throws RemoteInstanceException
	 * @throws RemoteInstanceNotFoundException
	 * @throws RemoteResourceNotFoundException
	 * @throws RestoringPointNotFoundException
	 * @throws ExternalFolderNotFoundException
	 * @throws RestoringChunkPartNotFoundException
	 * @throws RestoringPointException
	 * @throws RestoringPointPackException
	 * @throws GenericFileException
	 */
	private function getRestoringPoint(
		?string $remote,
		?int $external,
		string $pointId
	): RestoringPoint {
		if (!is_null($remote)) {
			return $this->remoteService->getRestoringPoint($remote, $pointId);
		}

		if ($external > 0) {
			$externalFolder = $this->externalFolderService->getByStorageId($external);

			return $this->externalFolderService->getRestoringPoint($externalFolder, $pointId);
		}

		$msg = 'use --external';
		if ($this->configService->isRemoteEnabled()) {
			$msg = 'use --remote or --external';
		}
		throw new InvalidOptionException($msg);
	}
}
