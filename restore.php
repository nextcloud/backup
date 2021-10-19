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


namespace OCA\Backup;

use Composer\Autoload\ClassLoader;
use ArtificialOwl\MySmallPhpTools\DI\DIContainer;
use ArtificialOwl\MySmallPhpTools\Exceptions\DependencyInjectionException;
use Exception;
use OC\Config;
use OC\Logger;
use OCA\Backup\Model\Backup;
use OCA\Backup\Service\PointService;
use OCP\IConfig;
use OCP\ILogger;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutput;
use ZipArchive;

echo "\n" . 'At this moment, this script will not works. Sorry.' . "\n";
echo 'Please follow online documentation to restore your backup.'. "\n\n";

exit();

/**
 * extracting app files
 */
if (!extractAppFiles()) {
	exit();
}


/**
 * init few stuff.
 */
$verbose = false;
if (in_array('-v', $argv) || in_array('--verbose', $argv)) {
	$verbose = true;
}


/**
 * loading libs.
 */
if ($verbose) {
	echo 'Loading libraries.' . "\n";
}

$classes = [
	'Service\ChunkService',
	'Service\CliService',
	'Service\ConfigService',
	'Service\EncryptService',
	'Service\FilesService',

	'Model\ArchiveFile',
	'Model\Backup',
	'Model\BackupArchive',
	'Model\BackupChunk',
	'Model\BackupOptions',
	'Model\Error',
	'Model\RemoteStorage',

	'ISqlDump',
	'SqlDump\SqlDumpMySQL',

	'Exceptions\ArchiveCreateException',
	'Exceptions\ArchiveNotFoundException',
	'Exceptions\BackupFolderException',
	'Exceptions\BackupScriptNotFoundException',
	'Exceptions\VersionException',
	'Exceptions\EncryptionKeyException',
	'Exceptions\ArchiveDeleteException',
	'Exceptions\BackupAppCopyException',
	'Exceptions\BackupNotFoundException',
];

$mockup = [
	'OCP\IConfig',
	'OC\Config',
	'OCP\ILogger',
	'OC\Logger'
];

loadClasses($classes, $mockup);


/**
 * generate Container.
 */
if ($verbose) {
	echo 'Generating Container.' . "\n";
}
$container = new DIContainer();

$container->registerInterface(IConfig::class, Config::class);
$container->registerInterface(ILogger::class, Logger::class);


/**
 * init Services.
 */
if ($verbose) {
	echo 'Loading Services.' . "\n";
}

try {
	/**
	 * @var CliService $cliService
	 */
	$cliService = $container->query(CliService::class);
} catch (DependencyInjectionException $e) {
	echo $e->getMessage() . "\n";
	exit();
}


if ($verbose) {
	echo 'App is ready.' . "\n";
}


/**
 * init input/output
 */
$inputDefinition = generateInputDefinition();

$input = new ArgvInput($argv, $inputDefinition);
$output = new ConsoleOutput();
$output = $output->section();
$cliService->init($input, $output);

if ($verbose) {
	$output->writeln('Switching to <info>better</info> console output!');
	$output->writeln('');
}


/**
 * parsing backup.json
 */
if ($verbose) {
	echo 'Parsing backup.json.' . "\n";
}
$json = file_get_contents(PointService::METADATA_FILE);

$backup = new Backup();
$backup->import(json_decode($json, true));
$backup->setLocal(true);

$backup->setEncryptionKey($input->getOption('key'));
$options = $backup->getOptions();
$options->setNewRoot($input->getOption('root'));
$options->setPath($input->getOption('path'));
$options->setSearch($input->getOption('search'));
$options->setConfigRaw($input->getOption('config'));
$options->setChunk($input->getOption('chunk'));
$options->setAll($input->getOption('all'));
$options->setArchive($input->getOption('archive'));
$options->setFixDataDir($input->getOption('fix-datadirectory'));

$cliService->displayBackupResume($backup);


/**
 * let's start based on Options
 */
try {
	switch ($input->getArgument('action')) {

		case 'details':
			$cliService->displayBackupDetails($backup);
			break;

		case 'restore':
			$cliService->displayBackupRestore($backup);
			break;

		case 'files':
			$cliService->displayFilesList($backup);
			break;

		default:
			$output->writeln('details/restore/files');
			break;
	}
} catch (Exception $e) {
	echo "\n" . $e->getMessage() . "\n\n";
	exit();
}


/**
 * The End.
 */


/**
 * @return bool
 */
function extractAppFiles(): bool {
	if (is_dir(__DIR__ . '/app/')) {
		return true;
	}

	echo 'Extracting Backup App files (using PHP/ZipArchive) ' . "\n";

	try {
		$zip = new ZipArchive();
		if (($err = $zip->open('app.zip')) !== true) {
			throw new Exception('failed with error ' . $err);
		}

		$zip->extractTo('./');
		$zip->close();

		return true;
	} catch (Exception $e) {
		echo $e->getMessage() . "\n";
		echo 'Extracting Backup App files (using unzip command) ' . "\n";
		system('unzip app.zip');

		return true;
	}
}


/**
 * @param array $classes
 * @param array $mockups
 *
 * @return ClassLoader
 */
function loadClasses(array $classes, array $mockups): ClassLoader {
	$r = 'OCA\Backup\\';
	$p = __DIR__ . '/app/lib/';
	$map = [];
	foreach ($classes as $class) {
		$classPath = str_replace('\\', '/', $class);
		$map[$r . $class] = $p . $classPath . '.php';
	}

	foreach ($mockups as $class) {
		$classPath = str_replace('\\', '/', $class);
		$map[$class] = $p . 'Mockup/' . $classPath . '.php';
	}

	$loader = require 'app/vendor/autoload.php';
	$loader->addClassMap($map);
	$loader->register();

	return $loader;
}


/**
 * @return InputDefinition
 */
function generateInputDefinition(): InputDefinition {
	$input = new InputDefinition();

	$input->addOption(new InputOption('verbose', 'v'));
	$input->addOption(new InputOption('full-install'));
	$input->addOption(new InputOption('check'));
	$input->addOption(new InputOption('key', 'k', InputOption::VALUE_REQUIRED, '', ''));
	$input->addOption(new InputOption('root', '', InputOption::VALUE_REQUIRED, '', ''));
	$input->addOption(new InputOption('search', '', InputOption::VALUE_REQUIRED, '', ''));
	$input->addOption(new InputOption('config', '', InputOption::VALUE_REQUIRED, '', '{}'));
	$input->addOption(new InputOption('path', '', InputOption::VALUE_REQUIRED, '', ''));
	$input->addOption(new InputOption('chunk', '', InputOption::VALUE_REQUIRED, '', ''));
	$input->addOption(new InputOption('archive', '', InputOption::VALUE_REQUIRED, '', ''));
	$input->addOption(new InputOption('dir', '', InputOption::VALUE_REQUIRED, '', ''));
	$input->addOption(new InputOption('fix-datadirectory'));
	$input->addOption(new InputOption('all'));
	$input->addArgument(new InputArgument('action'));
	$input->addArgument(new InputArgument('search'));

	return $input;
}
