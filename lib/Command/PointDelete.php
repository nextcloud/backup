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


use OC\Core\Command\Base;
use OCA\Backup\Exceptions\RestoringPointNotFoundException;
use OCA\Backup\Service\PointService;
use OCP\DB\Exception;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


/**
 * Class PointDelete
 *
 * @package OCA\Backup\Command
 */
class PointDelete extends Base {


	/** @var PointService */
	private $pointService;


	/**
	 * PointCreate constructor.
	 *
	 * @param PointService $pointService
	 */
	public function __construct(PointService $pointService) {
		parent::__construct();

		$this->pointService = $pointService;
	}


	/**
	 *
	 */
	protected function configure() {
		parent::configure();

		$this->setName('backup:point:delete')
			 ->setDescription('Locally delete a restoring point')
			 ->addArgument('pointId', InputArgument::REQUIRED, 'id of the restoring point to delete');
	}


	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @return int
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws RestoringPointNotFoundException
	 * @throws Exception
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$point = $this->pointService->getLocalRestoringPoint($input->getArgument('pointId'));

		$this->pointService->delete($point);

		return 0;
	}

}

