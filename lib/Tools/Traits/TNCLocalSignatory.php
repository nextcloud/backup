<?php

declare(strict_types=1);


/**
 * Nextcloud - Backup now. Restore later.
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Maxence Lange <maxence@artificial-owl.com>
 * @copyright 2022
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


namespace OCA\Backup\Tools\Traits;

use OC;
use OCA\Backup\Tools\Exceptions\SignatoryException;
use OCA\Backup\Tools\Model\NCSignatory;
use OCP\IConfig;

trait TNCLocalSignatory {
	use TNCSignatory;

	public static $SIGNATORIES_APP = 'signatories';


	/**
	 * @param NCSignatory $signatory
	 * @param bool $generate
	 *
	 * @throws SignatoryException
	 */
	public function fillSimpleSignatory(NCSignatory $signatory, bool $generate = false): void {
		$app = $this->setup('app', '', self::$SIGNATORIES_APP);
		$signatories = json_decode(OC::$server->get(IConfig::class)->getAppValue($app, 'key_pairs'), true);
		if (!is_array($signatories)) {
			$signatories = [];
		}

		$sign = $this->getArray($signatory->getId(), $signatories);
		if (!empty($sign)) {
			$signatory->setKeyId($this->get('keyId', $sign))
					  ->setKeyOwner($this->get('keyOwner', $sign))
					  ->setPublicKey($this->get('publicKey', $sign))
					  ->setPrivateKey($this->get('privateKey', $sign));

			return;
		}

		if (!$generate) {
			throw new SignatoryException('signatory not found');
		}

		$this->createSimpleSignatory($signatory);
	}


	/**
	 * @param NCSignatory $signatory
	 */
	public function createSimpleSignatory(NCSignatory $signatory): void {
		$app = $this->setup('app', '', self::$SIGNATORIES_APP);
		$signatory->setKeyId($signatory->getId() . '#main-key');
		$signatory->setKeyOwner($signatory->getId());
		$this->generateKeys($signatory);

		$signatories =
			json_decode(OC::$server->get(IConfig::class)->getAppValue($app, 'key_pairs', '[]'), true);
		$signatories[$signatory->getId()] = [
			'keyId' => $signatory->getKeyId(),
			'keyOwner' => $signatory->getKeyOwner(),
			'publicKey' => $signatory->getPublicKey(),
			'privateKey' => $signatory->getPrivateKey()
		];

		OC::$server->get(IConfig::class)->setAppValue($app, 'key_pairs', json_encode($signatories));
	}


	/**
	 * @param NCSignatory $signatory
	 */
	public function removeSimpleSignatory(NCSignatory $signatory): void {
		$app = $this->setup('app', '', self::$SIGNATORIES_APP);
		$signatories = json_decode(OC::$server->get(IConfig::class)->getAppValue($app, 'key_pairs'), true);
		if (!is_array($signatories)) {
			$signatories = [];
		}

		unset($signatories[$signatory->getId()]);
		OC::$server->get(IConfig::class)->setAppValue($app, 'key_pairs', json_encode($signatories));
	}
}
