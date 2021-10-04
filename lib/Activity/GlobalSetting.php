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


namespace OCA\Backup\Activity;

use OCA\Backup\AppInfo\Application;
use OCP\Activity\ISetting;
use OCP\IL10N;


/**
 * Class Settings
 *
 * @package OCA\Backup\Activity
 */
class GlobalSetting implements ISetting {


	/** @var IL10N */
	private $l10n;


	/**
	 * @param IL10N $l10n
	 */
	public function __construct(IL10N $l10n) {
		$this->l10n = $l10n;
	}


	/**
	 * @return string
	 */
	public function getIdentifier(): string {
		return Application::APP_ID;
	}


	/**
	 * @return string
	 */
	public function getName(): string {
		return $this->l10n->t('Update on all Backup\'s event');
	}


	/**
	 * @return int
	 */
	public function getPriority(): int {
		return 60;
	}

	/**
	 * @return bool
	 */
	public function canChangeStream(): bool {
		return $this->isAdmin();
	}

	/**
	 * @return bool
	 */
	public function isDefaultEnabledStream(): bool {
		return $this->isAdmin();
	}

	/**
	 * @return bool
	 */
	public function canChangeMail(): bool {
		return $this->isAdmin();
	}

	/**
	 * @return bool
	 */
	public function isDefaultEnabledMail(): bool {
		return $this->isAdmin();
	}


	private function isAdmin(): bool {
		return true;
	}
}
