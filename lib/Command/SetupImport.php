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
use ArtificialOwl\MySmallPhpTools\Traits\TArrayTools;
use Exception;
use OC\Core\Command\Base;
use OCA\Backup\Db\RemoteRequest;
use OCA\Backup\Model\RemoteInstance;
use OCA\Backup\Service\ConfigService;
use OCA\Backup\Service\EncryptService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


/**
 * Class SetupImport
 *
 * @package OCA\Backup\Command
 */
class SetupImport extends Base {


	use TArrayTools;
	use TNC23Deserialize;


	/** @var RemoteRequest */
	private $remoteRequest;

	/** @var EncryptService */
	private $encryptService;

	/** @var ConfigService */
	private $configService;


	/**
	 * SetupImport constructor.
	 *
	 * @param RemoteRequest $remoteRequest
	 * @param EncryptService $encryptService
	 * @param ConfigService $configService
	 */
	public function __construct(
		RemoteRequest $remoteRequest,
		EncryptService $encryptService,
		ConfigService $configService
	) {
		parent::__construct();

		$this->remoteRequest = $remoteRequest;
		$this->encryptService = $encryptService;
		$this->configService = $configService;
	}


	/**
	 *
	 */
	protected function configure() {
		$this->setName('backup:setup:import')
			 ->setDescription('Import your setup for easier restoration')
			 ->addOption('key', '', InputOption::VALUE_REQUIRED, 'key used when exporting the setup', '');
	}


	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @return int
	 * @throws Exception
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$json = '';
		while (!feof(STDIN)) {
			$json .= fgets(STDIN);
		}

		$key = $input->getOption('key');
		if ($key !== '') {
			$key = base64_decode($key);
			$json = $this->encryptService->decryptString($json, $key);
		}

		$setup = json_decode($json, true);
		if (!is_array($setup)) {
			throw new Exception(
				'Setup cannot be imported' . "\n"
				. 'Is it encrypted ? if so use --key <KEY>'
			);
		}

		$this->configService->setAppValue('key_pairs', $this->get('signatory', $setup));

		/** @var RemoteInstance[] $remotes */
		$remotes = $this->deserializeArray($this->getArray('remote', $setup), RemoteInstance::class);

		foreach ($remotes as $remote) {
			$this->remoteRequest->insertOrUpdate($remote, true);
		}

		return 0;
	}

}
