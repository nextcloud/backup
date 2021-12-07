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
use OCA\Backup\Db\CoreRequestBuilder;
use OCA\Backup\Exceptions\ExternalFolderNotFoundException;
use OCA\Backup\Service\ConfigService;
use OCA\Backup\Service\PointService;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

/**
 * Class Reset
 *
 * @package OCA\Backup\Command
 */
class Reset extends Base {


	/** @var CoreRequestBuilder */
	private $coreRequestBuilder;

	/** @var PointService */
	private $pointService;

	/** @var ConfigService */
	private $configService;


	/**
	 * Reset constructor.
	 *
	 * @param CoreRequestBuilder $coreRequestBuilder
	 * @param PointService $pointService
	 * @param ConfigService $configService
	 */
	public function __construct(
		CoreRequestBuilder $coreRequestBuilder,
		PointService $pointService,
		ConfigService $configService
	) {
		parent::__construct();

		$this->coreRequestBuilder = $coreRequestBuilder;
		$this->pointService = $pointService;
		$this->configService = $configService;
	}


	/**
	 *
	 */
	protected function configure() {
		$this->setName('backup:reset')
			 ->setDescription('Remove all data related to the Backup App')
			 ->addOption(
				 'uninstall', '', InputOption::VALUE_NONE, 'Also uninstall the app from the instance'
			 );
	}


	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @return int
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$action = ($input->getOption('uninstall')) ? 'uninstall' : 'reset';

		$output->writeln('');
		$output->writeln('');
		$output->writeln(
			'<error>WARNING! You are about to delete all data (and Restoring Point) related to the Backup App!</error>'
		);
		$question = new ConfirmationQuestion(
			'<comment>Do you really want to ' . $action . ' the Backup App?</comment> (y/N) ', false,
			'/^(y|Y)/i'
		);

		$helper = $this->getHelper('question');
		if (!$helper->ask($input, $output, $question)) {
			$output->writeln('aborted.');

			return 0;
		}

		$output->writeln('');
		$output->writeln('<error>WARNING! This operation is not reversible.</error>');

		$question = new Question(
			'<comment>Please confirm this destructive operation by typing \'' . $action
			. '\'</comment>: ', ''
		);

		$helper = $this->getHelper('question');
		$confirmation = $helper->ask($input, $output, $question);
		if (strtolower($confirmation) !== $action) {
			$output->writeln('aborted.');

			return 0;
		}

		$this->coreRequestBuilder->cleanDatabase();
		try {
			$this->pointService->destroyBackupFS();
		} catch (NotFoundException | NotPermittedException | ExternalFolderNotFoundException $e) {
		}

		$this->configService->setAppValue(ConfigService::LAST_FULL_RP, '');
		if ($action === 'uninstall') {
			$this->coreRequestBuilder->uninstall();
		}

		$output->writeln('<info>' . $action . ' done</info>');

		return 0;
	}
}
