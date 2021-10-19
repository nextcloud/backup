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
use OCA\Backup\Exceptions\ExternalFolderNotFoundException;
use OCA\Backup\Service\ExternalFolderService;
use OCA\Files_External\Lib\InsufficientDataForMeaningfulAnswerException;
use OCP\Files\StorageNotAvailableException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;


/**
 * Class ExternalAdd
 *
 * @package OCA\Backup\Command
 */
class ExternalAdd extends Base {


	/** @var ExternalFolderService */
	private $externalFolderService;


	/**
	 * ExternalAdd constructor.
	 *
	 * @param ExternalFolderService $externalFolderService
	 */
	public function __construct(ExternalFolderService $externalFolderService) {
		parent::__construct();

		$this->externalFolderService = $externalFolderService;
	}


	/**
	 *
	 */
	protected function configure() {
		$this->setName('backup:external:add')
			 ->setDescription('Add external filesystem to store your backups');
	}


	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @return int
	 * @throws InsufficientDataForMeaningfulAnswerException
	 * @throws StorageNotAvailableException
	 * @throws ExternalFolderNotFoundException
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {

		$storageId = $this->selectStorage($input, $output);
		$output->writeln('');
		if ($storageId === 0) {
			$output->writeln('Operation cancelled');

			return 0;
		}

		$root = $this->requestingRoot($input, $output);
		echo '>> ' . json_encode($root);
		$output->writeln('');
		if ($root === '') {
			$output->writeln('Operation cancelled');

			return 0;
		}

		$external = $this->externalFolderService->getStorageById($storageId);
		$output->writeln('');
		if ($external->getRoot() !== '') {
			$output->writeln('This external filesystem is already used by the Backup App');

			return 0;
		}

		$external->setRoot($root);

		$output->writeln('Please confirm the creation of a new External Folder, based on this setup:');
		$output->writeln('');
		$output->writeln('Storage Id: <info>' . $external->getStorageId() . '</info>');
		$output->writeln('Storage Path: <info>' . $external->getStorage() . '</info>');
		$output->writeln('Localisation of backup files: <info>' . $external->getRoot() . '</info>');
		$output->writeln('');

		$question = new ConfirmationQuestion(
			'<comment>Do you really want to create and use this External Folder to store your backup ?</comment> (y/N) ',
			false,
			'/^(y|Y)/i'
		);

		$helper = $this->getHelper('question');
		if (!$helper->ask($input, $output, $question)) {
			$output->writeln('Operation cancelled');

			return 0;
		}
		
		$this->externalFolderService->save($external);

		$output->writeln(
			'<info>The generated External Folder will now be used to store your restoring points</info>'
		);

		return 0;
	}


	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @return int|mixed|string|null
	 * @throws InsufficientDataForMeaningfulAnswerException
	 * @throws StorageNotAvailableException
	 */
	private function selectStorage(InputInterface $input, OutputInterface $output): int {
		$availableStorage = [];
		foreach ($this->externalFolderService->getStorages() as $storage) {
			if ($storage->getRoot() !== '') {
				continue;
			}
			$availableStorage[$storage->getStorageId()] =
				$storage->getStorage() . ' (id:' . $storage->getStorageId() . ')';
		}

		if (empty($availableStorage)) {
			$output->writeln('There is no available external filesystem.');
			$output->writeln(
				'You can use <info>./occ backup:external:list</info> to see already configured external folders'
			);
			$output->writeln('You can use the <info>Files External</info> to add a new external filesystem');
			$output->writeln('');

			return 0;
		}

		$availableStorage[0] = 'exit';

		$output->writeln('');
		$helper = $this->getHelper('question');
		$question = new ChoiceQuestion(
			'Which external storage you want to use to store your backups ?',
			$availableStorage,
			0
		);
		$question->setErrorMessage('Select a valid filesystem');

		$result = $helper->ask($input, $output, $question);
		foreach ($availableStorage as $k => $v) {
			if ($v === $result) {
				return $k;
			}
		}

		return 0;
	}


	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @return string
	 */
	private function requestingRoot(InputInterface $input, OutputInterface $output): string {
		$helper = $this->getHelper('question');
		$default = 'backup/points/';

		$question = new Question(
			'Path to the right folder to store your backups (default="<info>' . $default . '</info>"): ',
			$default
		);

		return trim($helper->ask($input, $output, $question));
	}

}

