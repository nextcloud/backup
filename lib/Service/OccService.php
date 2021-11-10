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


namespace OCA\Backup\Service;

use ArtificialOwl\MySmallPhpTools\Exceptions\SignatoryException;
use OCA\Backup\Exceptions\ExternalFolderNotFoundException;
use OCA\Backup\Exceptions\MetadataException;
use OCA\Backup\Exceptions\RemoteInstanceException;
use OCA\Backup\Exceptions\RemoteInstanceNotFoundException;
use OCA\Backup\Exceptions\RemoteResourceNotFoundException;
use OCA\Backup\Exceptions\RestoringChunkPartNotFoundException;
use OCA\Backup\Exceptions\RestoringPointException;
use OCA\Backup\Exceptions\RestoringPointNotFoundException;
use OCA\Backup\Exceptions\RestoringPointPackException;
use OCA\Backup\Model\RestoringPoint;
use OCP\Files\GenericFileException;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Class OccService
 *
 * @package OCA\Backup\Service
 */
class OccService {


	/** @var PointService */
	private $pointService;

	/** @var RemoteService */
	private $remoteService;

	/** @var RemoteStreamService */
	private $remoteStreamService;

	/** @var ExternalFolderService */
	private $externalFolderService;

	/** @var MetadataService */
	private $metadataService;


	/**
	 * OccService constructor.
	 *
	 * @param PointService $pointService
	 * @param RemoteService $remoteService
	 * @param RemoteStreamService $remoteStreamService
	 * @param ExternalFolderService $externalFolderService
	 * @param MetadataService $metadataService
	 */
	public function __construct(
		PointService $pointService,
		RemoteService $remoteService,
		RemoteStreamService $remoteStreamService,
		ExternalFolderService $externalFolderService,
		MetadataService $metadataService
	) {
		$this->pointService = $pointService;
		$this->remoteService = $remoteService;
		$this->externalFolderService = $externalFolderService;

		$this->remoteStreamService = $remoteStreamService;
		$this->metadataService = $metadataService;
	}


	/**
	 * @param InputInterface $input
	 *
	 * @return RestoringPoint
	 * @throws ExternalFolderNotFoundException
	 * @throws GenericFileException
	 * @throws NotPermittedException
	 * @throws RemoteInstanceException
	 * @throws RemoteInstanceNotFoundException
	 * @throws RemoteResourceNotFoundException
	 * @throws RestoringChunkPartNotFoundException
	 * @throws RestoringPointException
	 * @throws RestoringPointNotFoundException
	 * @throws RestoringPointPackException
	 */
	public function getRestoringPointBasedOnParams(InputInterface $input): RestoringPoint {
		$pointId = $input->getArgument('pointId');
		$remote = $input->getOption('remote');
		$external = $input->getOption('external');

		if ($remote) {
			return $this->remoteService->getRestoringPoint($remote, $pointId, true);
		}

		if ($external) {
			$externalFolder = $this->externalFolderService->getByStorageId((int)$external);

			return $this->externalFolderService->getRestoringPoint($externalFolder, $pointId, true);
		}

		return $this->pointService->getLocalRestoringPoint($pointId);
	}


	/**
	 * @param RestoringPoint $point
	 * @param InputInterface $input
	 *
	 * @throws ExternalFolderNotFoundException
	 * @throws NotPermittedException
	 * @throws RemoteInstanceNotFoundException
	 * @throws SignatoryException
	 * @throws MetadataException
	 * @throws NotFoundException
	 */
	public function updatePointBasedOnParams(RestoringPoint $point, InputInterface $input): void {
		if ($input->getOption('all-storages')) {
			$this->pointService->updateSubInfos($point);
			$this->metadataService->globalUpdate($point);

			return;
		}

		$this->remoteStreamService->subSignPoint($point);

		$remote = $input->getOption('remote');
		$external = $input->getOption('external');

		if ($remote) {
			$remoteInstance = $this->remoteService->getByInstance($remote);
			$this->remoteService->updateMetadata($point, $remoteInstance);

			return;
		}

		if ($external) {
			$externalFolder = $this->externalFolderService->getByStorageId((int)$external);
			$this->externalFolderService->updateMetadata($point, $externalFolder);

			return;
		}

		$this->pointService->updateSubInfos($point);
	}
}
