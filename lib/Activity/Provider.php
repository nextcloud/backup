<?php

declare(strict_types=1);


/**
 * Nextcloud - Backup now. Restore Later.
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

use ArtificialOwl\MySmallPhpTools\Traits\TArrayTools;
use ArtificialOwl\MySmallPhpTools\Traits\TStringTools;
use Exception;
use InvalidArgumentException;
use OCA\Backup\AppInfo\Application;
use OCA\Backup\Service\ActivityService;
use OCP\Activity\IEvent;
use OCP\Activity\IManager;
use OCP\Activity\IProvider;
use OCP\IL10N;
use OCP\IURLGenerator;

/**
 * Class Provider
 *
 * @package OCA\Backup\Activity
 */
class Provider implements IProvider {
	use TStringTools;
	use TArrayTools;


	/** @var IL10N */
	private $l10n;

	/** @var IManager */
	private $activityManager;

	/** @var IURLGenerator */
	private $urlGenerator;


	/**
	 * Provider constructor.
	 *
	 * @param IL10N $l10n
	 * @param IManager $activityManager
	 * @param IURLGenerator $urlGenerator
	 */
	public function __construct(
		IL10N $l10n,
		IManager $activityManager,
		IURLGenerator $urlGenerator
	) {
		$this->l10n = $l10n;
		$this->activityManager = $activityManager;
		$this->urlGenerator = $urlGenerator;
	}


	/**
	 * @param string $lang
	 * @param IEvent $event
	 * @param IEvent|null $previousEvent
	 *
	 * @return IEvent
	 */
	public function parse($lang, IEvent $event, IEvent $previousEvent = null): IEvent {
		$params = $event->getSubjectParameters();
		$this->initActivityParser($event, $params);
		$this->setIcon($event);

		if ($event->getType() !== ActivityService::TYPE_GLOBAL) {
			return $event;
		}

		switch ($event->getSubject()) {
			case ActivityService::CREATE:
				$this->parseCreate($event, $params);
				break;

			case ActivityService::RESTORE:
				$this->parseRestore($event, $params);
				break;

			case ActivityService::RESTORE_FILE:
				$this->parseRestoreFile($event, $params);
				break;

		}

		return $event;
	}


	/**
	 * @param IEvent $event
	 * @param array $params
	 */
	private function initActivityParser(IEvent $event, array $params = []): void {
		if ($event->getApp() !== Application::APP_ID) {
			throw new InvalidArgumentException();
		}

		if (!key_exists('id', $params)) {
			throw new InvalidArgumentException();
		}
	}


	/**
	 * @param IEvent $event
	 */
	private function setIcon(IEvent $event): void {
		$event->setIcon(
			$this->urlGenerator->getAbsoluteURL(
				$this->urlGenerator->imagePath(
					Application::APP_ID,
					'backup.svg'
				)
			)
		);
	}


	/**
	 * @param IEvent $activity
	 * @param array $params
	 */
	private function parseCreate(IEvent $activity, array $params): void {
		$params['type'] = ($this->getBool('complete', $params)) ?
			$this->l10n->t('complete') :
			$this->l10n->t('partial');

		try {
			$params['downtime'] = $this->getDateDiff(
				$this->getInt('duration', $params),
				0,
				false,
				[
					'seconds' => $this->l10n->t('seconds'),
					'minutes' => $this->l10n->t('minutes'),
					'hours' => $this->l10n->t('hours'),
					'days' => $this->l10n->t('days')
				]
			);
		} catch (Exception $e) {
		}

		$this->parseSimpleEvent(
			$activity,
			$this->l10n->t(
				'A new restoring point ({type}) has been generated, requiring maintenance mode for {downtime}.'
			),
			$params
		);
	}


	/**
	 * @param IEvent $activity
	 * @param array $params
	 */
	private function parseRestore(IEvent $activity, array $params): void {
		$this->readableRewing($params);

		$this->parseSimpleEvent(
			$activity,
			$this->l10n->t(
				'Your system have been fully restored based on a restoring point from {date} (estimated rewind: {rewind})'
			),
			$params
		);
	}


	/**
	 * @param IEvent $activity
	 * @param array $params
	 */
	private function parseRestoreFile(IEvent $activity, array $params): void {
		$this->readableRewing($params);

		$this->parseSimpleEvent(
			$activity,
			$this->l10n->t(
				'The file {file} have been restored based on a restoring point from {date} (estimated rewind: {rewind})'
			),
			$params
		);
	}


	/**
	 * @param IEvent $activity
	 * @param string $global
	 * @param array $params
	 */
	protected function parseSimpleEvent(
		IEvent $activity,
		string $global,
		array $params
	): void {
		$line = $this->l10n->t($global, $params);
		$line = $this->feedStringWithParams($line, $params);

		$this->setSubject($activity, $line);
	}


	/**
	 * @param IEvent $event
	 * @param string $line
	 */
	protected function setSubject(IEvent $event, string $line) {
		$event->setParsedSubject($line);
		$event->setRichSubject($line);
	}


	/**
	 * @param array $params
	 */
	private function readableRewing(array &$params): void {
		$params['date'] = date('Y-m-d H:i:s', $this->getInt('date', $params));

		try {
			$params['rewind'] = $this->getDateDiff(
				$this->getInt('rewind', $params),
				0,
				false,
				[
					'seconds' => $this->l10n->t('seconds'),
					'minutes' => $this->l10n->t('minutes'),
					'hours' => $this->l10n->t('hours'),
					'days' => $this->l10n->t('days')
				]
			);
		} catch (Exception $e) {
		}
	}
}
