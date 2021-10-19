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

use ArtificialOwl\MySmallPhpTools\Traits\Nextcloud\nc23\TNC23Logger;
use OC\Core\Command\Base;
use OCA\Backup\Service\ChunkService;
use OCA\Backup\Service\PointService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class PointHistory
 *
 * @package OCA\Backup\Command
 */
class PointHistory extends Base {
	use TNC23Logger;


	/** @var PointService */
	private $pointService;

	/** @var ChunkService */
	private $chunkService;


	/**
	 * PointHistory constructor.
	 *
	 * @param PointService $pointService
	 * @param ChunkService $chunkService
	 */
	public function __construct(PointService $pointService, ChunkService $chunkService) {
		parent::__construct();

		$this->pointService = $pointService;
		$this->chunkService = $chunkService;
	}


	/**
	 *
	 */
	protected function configure() {
		parent::configure();

		$this->setName('backup:point:history')
			 ->setDescription('Get the history of your restoring point');
	}


	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @return int
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$output->writeln('not yet available.');
		$output->writeln(
			'visit the Activity App or use ./occ backup:point:list to get available restoring point'
		);


		return 0;
	}
}
