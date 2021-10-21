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


namespace OCA\Backup\Settings;

use OCA\Backup\AppInfo\Application;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

/**
 * Class AdminSection
 *
 * @package OCA\Backup\Settings
 */
class AdminSection implements IIconSection {


	/** @var IL10N */
	private $l10n;

	/** @var IURLGenerator */
	private $urlGenerator;


	/**
	 * @param IL10N $l10n
	 * @param IURLGenerator $urlGenerator
	 */
	public function __construct(IL10N $l10n, IURLGenerator $urlGenerator) {
		$this->l10n = $l10n;
		$this->urlGenerator = $urlGenerator;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getID(): string {
		return Application::APP_ID;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getName(): string {
		return $this->l10n->t('Backup');
	}

	/**
	 * {@inheritdoc}
	 */
	public function getPriority(): int {
		return 90;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getIcon(): string {
		return $this->urlGenerator->imagePath('backup', 'backup.svg');
	}
}
