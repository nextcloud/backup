<?php

declare(strict_types=1);


/**
 * Nextcloud - Backup now. Restore Later
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


use Exception;
use OC\Core\Command\Base;
use OCA\Backup\Service\ConfigService;
use OCA\Backup\Service\EncryptService;
use OCA\Backup\Service\RemoteService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;


/**
 * Class SetupExport
 *
 * @package OCA\Backup\Command
 */
class SetupExport extends Base {


	/** @var RemoteService */
	private $remoteService;

	/** @var EncryptService */
	private $encryptService;

	/** @var ConfigService */
	private $configService;


	/**
	 * SetupExport constructor.
	 *
	 * @param RemoteService $remoteService
	 * @param EncryptService $encryptService
	 * @param ConfigService $configService
	 */
	public function __construct(
		RemoteService $remoteService,
		EncryptService $encryptService,
		ConfigService $configService
	) {
		parent::__construct();

		$this->remoteService = $remoteService;
		$this->encryptService = $encryptService;
		$this->configService = $configService;
	}


	/**
	 *
	 */
	protected function configure() {
		$this->setName('backup:setup:export')
			 ->setDescription('Export your setup for easier restoration')
			 ->addOption('key', '', InputOption::VALUE_NONE, 'use a generated key to encrypt the data');
	}


	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @return int
	 * @throws Exception
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$setup = [
			'signatory' => $this->configService->getAppValue('key_pairs'),
			'remote' => $this->remoteService->getAll(true),
			'encryption' => $this->encryptService->getEncryptionKey()
		];

		$data = json_encode($setup);

		$key = '';
		if ($input->getOption('key')) {
			$key = random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
			$data = $this->encryptService->encryptString($data, $key);
		}

		$output->writeln($data);

		if ($key !== '') {
			$io = new SymfonyStyle($input, $output);
			$io->getErrorStyle()->warning(
				'Keep this KEY somewhere safe, it will be required to import' . "\n"
				. 'the setup of your Backup App on a fresh installation of Nextcloud: ' . "\n\n"
				. base64_encode($key)
			);
		}

		return 0;
	}

}

