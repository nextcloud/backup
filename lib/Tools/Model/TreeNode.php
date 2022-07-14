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


namespace OCA\Backup\Tools\Model;

class TreeNode {


	/** @var self[] */
	private $children = [];

	/** @var self */
	private $parent;

	/** @var SimpleDataStore */
	private $item;


	/** @var self */
	private $currentChild;

	/** @var bool */
	private $displayed = false;

	/** @var bool */
	private $splited = false;


	/**
	 * NC22TreeNode constructor.
	 *
	 * @param self|null $parent
	 * @param SimpleDataStore $item
	 */
	public function __construct(?TreeNode $parent, SimpleDataStore $item) {
		$this->parent = $parent;
		$this->item = $item;

		if ($this->parent !== null) {
			$this->parent->addChild($this);
		}
	}

	/**
	 * @return bool
	 */
	public function isRoot(): bool {
		return (is_null($this->parent));
	}


	/**
	 * @param array $children
	 *
	 * @return TreeNode
	 */
	public function setChildren(array $children): self {
		$this->children = $children;

		return $this;
	}

	/**
	 * @param TreeNode $child
	 *
	 * @return $this
	 */
	public function addChild(TreeNode $child): self {
		$this->children[] = $child;

		return $this;
	}


	/**
	 * @return SimpleDataStore
	 */
	public function getItem(): SimpleDataStore {
		$this->displayed = true;

		return $this->item;
	}


	/**
	 * @return TreeNode
	 */
	public function getParent(): TreeNode {
		return $this->parent;
	}


	/**
	 * @return $this
	 */
	public function getRoot(): TreeNode {
		if ($this->isRoot()) {
			return $this;
		}

		return $this->getParent()->getRoot();
	}


	/**
	 * @return TreeNode[]
	 */
	public function getPath(): array {
		if ($this->isRoot()) {
			return [$this];
		}

		return array_merge($this->parent->getPath(), [$this]);
	}


	/**
	 * @return int
	 */
	public function getLevel(): int {
		if ($this->isRoot()) {
			return 0;
		}

		return $this->getParent()->getLevel() + 1;
	}


	/**
	 * @return TreeNode|null
	 */
	public function current(): ?TreeNode {
		if (!$this->isDisplayed()) {
			return $this;
		}

		$this->splited = true;
		if ($this->initCurrentChild()) {
			$next = $this->getCurrentChild()->current();
			if (!is_null($next)) {
				return $next;
			}
		}

		if (!$this->haveNext()) {
			return null;
		}

		return $this->next();
	}


	/**
	 * @return TreeNode
	 */
	private function next(): TreeNode {
		$this->currentChild = array_shift($this->children);

		return $this->currentChild;
	}

	/**
	 * @return bool
	 */
	public function haveNext(): bool {
		return !empty($this->children);
	}


	/**
	 * @return bool
	 */
	private function initCurrentChild(): bool {
		if (is_null($this->currentChild)) {
			if (!$this->haveNext()) {
				return false;
			}
			$this->next();
		}

		return true;
	}

	/**
	 * @return TreeNode|null
	 */
	private function getCurrentChild(): ?TreeNode {
		return $this->currentChild;
	}

	/**
	 * @return bool
	 */
	private function isDisplayed(): bool {
		return $this->displayed;
	}

	/**
	 * @return bool
	 */
	public function isSplited(): bool {
		return $this->splited;
	}
}
