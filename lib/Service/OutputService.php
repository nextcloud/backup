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


namespace OCA\Backup\Service;


use OCA\Backup\Model\RestoringChunkHealth;
use OCA\Backup\Model\RestoringHealth;
use OCA\Backup\Model\RestoringPoint;
use Symfony\Component\Console\Output\OutputInterface;


/**
 * Class OccService
 *
 * @package OCA\Backup\Service
 */
class OutputService {


	/** @var OutputInterface */
	private $output;


	/**
	 * @param string $line
	 */
	public function o(string $line): void {
		if ($this->hasOutput()) {
			$this->output->writeln($line);
		}
	}


	/**
	 * @return bool
	 */
	private function hasOutput(): bool {
		return !is_null($this->output);
	}

	public function setOutput(OutputInterface $output): void {
		$this->output = $output;
	}


	/**
	 * @param RestoringPoint $point
	 *
	 * @return string
	 */
	public function displayHealth(RestoringPoint $point): string {
		$health = $point->getHealth();
		if ($health->getStatus() === RestoringHealth::STATUS_OK) {
			return '<info>ok</info>';
		}

		if ($health->getStatus() === RestoringHealth::STATUS_ORPHAN) {
			return '<comment>orphan</comment>';
		}

		$unknown = $good = $missing = $faulty = 0;
		foreach ($health->getChunks() as $chunk) {
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
			   . $good . ' correct, '
			   . $missing . ' missing and '
			   . $faulty . ' faulty files</comment>';
	}

}
