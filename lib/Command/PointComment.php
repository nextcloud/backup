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
use OC\Core\Command\Base;
use OCA\Backup\Exceptions\RestoringPointNotFoundException;
use OCA\Backup\Service\MetadataService;
use OCA\Backup\Service\OutputService;
use OCA\Backup\Service\PointService;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


/**
 * Class PointComment
 *
 * @package OCA\Backup\Command
 */
class PointComment extends Base {


	/** @var PointService */
	private $pointService;

	/** @var MetadataService */
	private $metadataService;

	/** @var OutputService */
	private $outputService;


	/**
	 * PointComment constructor.
	 *
	 * @param PointService $pointService
	 * @param MetadataService $metadataService
	 * @param OutputService $outputService
	 */
	public function __construct(
		PointService $pointService,
		MetadataService $metadataService,
		OutputService $outputService
	) {
		parent::__construct();

		$this->pointService = $pointService;
		$this->metadataService = $metadataService;
		$this->outputService = $outputService;
	}


	/**
	 *
	 */
	protected function configure() {
		parent::configure();

		$this->setName('backup:point:comment')
			 ->setDescription('Add a description to a restoring point')
			 ->addArgument('pointId', InputArgument::REQUIRED, 'id of the restoring point to comment')
			 ->addArgument('comment', InputArgument::REQUIRED, 'comment');
	}


	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @return int
	 * @throws RestoringPointNotFoundException
	 * @throws SignatoryException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$this->outputService->setOutput($output);

		$point = $this->pointService->getLocalRestoringPoint($input->getArgument('pointId'));
		$point->setComment($input->getArgument('comment'));

		$this->pointService->update($point, true);
		$this->metadataService->globalUpdate($point);

		return 0;
	}


}

