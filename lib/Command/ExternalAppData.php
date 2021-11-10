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

use ArtificialOwl\MySmallPhpTools\Traits\Nextcloud\nc23\TNC23Deserialize;
use OC\Core\Command\Base;
use OCA\Backup\Exceptions\ExternalAppdataException;
use OCA\Backup\Exceptions\ExternalFolderNotFoundException;
use OCA\Backup\Service\ConfigService;
use OCA\Backup\Service\ExternalFolderService;
use OCA\Backup\Service\PointService;
use OCA\Files_External\Lib\InsufficientDataForMeaningfulAnswerException;
use OCP\Files\StorageNotAvailableException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

/**
 * Class ExternalAppData
 *
 * @package OCA\Backup\Command
 */
class ExternalAppData extends Base {
	use TNC23Deserialize;


	/** @var PointService */
	private $pointService;

	/** @var ExternalFolderService */
	private $externalFolderService;

	/** @var ConfigService */
	private $configService;


	/**
	 * ExternalAppData constructor.
	 *
	 * @param PointService $pointService
	 * @param ExternalFolderService $externalFolderService
	 * @param ConfigService $configService
	 */
	public function __construct(
		PointService $pointService,
		ExternalFolderService $externalFolderService,
		ConfigService $configService
	) {
		parent::__construct();

		$this->pointService = $pointService;
		$this->externalFolderService = $externalFolderService;
		$this->configService = $configService;
	}


	/**
	 *
	 */
	protected function configure() {
		$this->setName('backup:external:appdata')
			 ->setDescription('Add external filesystem to store the app\'s data')
			 ->addOption('unset', '', InputOption::VALUE_NONE, 'Unset the current external appdata');
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
		$unset = $input->getOption('unset');

		try {
			$external = $this->pointService->getExternalAppData();
			$output->writeln('Your <info>appdata</info> is currently on an external storage:');
			$output->writeln('Storage Id: <info>' . $external->getStorageId() . '</info>');
			$output->writeln('Storage: <info>' . $external->getStorage() . '</info>');
			$output->writeln('Root: <info>' . $external->getRoot() . '</info>');
			$output->writeln('');

			if (!$unset) {
				return 0;
			}
		} catch (ExternalFolderNotFoundException $e) {
			if (!$unset) {
				throw $e;
			}
		} catch (ExternalAppdataException $e) {
			$unset = false;
		}

		if (!$unset) {
			$output->writeln(
				'This configuration tool will help you set the <info>Appdata</info> folder '
				. ' of the Backup App on an <info>external storage</info>'
			);
		}

		$output->writeln('');
		$output->writeln('');
		$output->writeln('<error>All previous Restoring Point will be lost during this process</error>');
		$output->writeln('');
		$output->writeln('');

		if ($unset) {
			$question = new ConfirmationQuestion(
				'<comment>Do you really want to not use this External Folder appdata anymore ?</comment> (y/N) ',
				false,
				'/^(y|Y)/i'
			);

			$helper = $this->getHelper('question');
			if (!$helper->ask($input, $output, $question)) {
				$output->writeln('Operation cancelled');
			}

			$this->pointService->setExternalAppData(0);

			return 0;
		}


		$storageId = $this->selectStorage($input, $output);

		$output->writeln('');
		if ($storageId === 0) {
			$output->writeln('Operation cancelled');

			return 0;
		}

		$root = $this->requestingRoot($input, $output);
		$output->writeln('');
		if ($root === '') {
			$output->writeln('Operation cancelled');

			return 0;
		}

		$external = $this->externalFolderService->getStorageById($storageId);
		$output->writeln('');

		$external->setRoot($root);

		$output->writeln('Please confirm the creation of a new External Folder, based on this setup:');
		$output->writeln('');
		$output->writeln('Storage Id: <info>' . $external->getStorageId() . '</info>');
		$output->writeln('Storage Path: <info>' . $external->getStorage() . '</info>');
		$output->writeln('Localisation of backup files: <info>' . $external->getRoot() . '</info>');
		$output->writeln('');

		$question = new ConfirmationQuestion(
			'<comment>Do you really want to create and use this External Folder as appdata ?</comment> (y/N) ',
			false,
			'/^(y|Y)/i'
		);

		$helper = $this->getHelper('question');
		if (!$helper->ask($input, $output, $question)) {
			$output->writeln('Operation cancelled');

			return 0;
		}

		$this->pointService->setExternalAppData($storageId, $root);
		$output->writeln('done');

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
			$availableStorage[$storage->getStorageId()] =
				$storage->getStorage() . ' (id:' . $storage->getStorageId() . ')';
		}

		if (empty($availableStorage)) {
			$output->writeln('There is no available external filesystem.');
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
		$default = 'backups/';

		$question = new Question(
			'Path to the right folder to store your backups (default="<info>' . $default . '</info>"): ',
			$default
		);

		return trim($helper->ask($input, $output, $question));
	}
}
