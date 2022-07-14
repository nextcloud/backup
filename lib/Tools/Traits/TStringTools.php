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

use DateTime;
use Exception;

trait TStringTools {
	use TArrayTools;


	/**
	 * @param int $length
	 *
	 * @return string
	 */
	protected function token(int $length = 15): string {
		$chars = 'qwertyuiopasdfghjklzxcvbnmQWERTYUIOPASDFGHJKLZXCVBNM1234567890';

		$str = '';
		$max = strlen($chars);
		for ($i = 0; $i < $length; $i++) {
			try {
				$str .= $chars[random_int(0, $max - 2)];
			} catch (Exception $e) {
			}
		}

		return $str;
	}


	/**
	 * Generate uuid: 2b5a7a87-8db1-445f-a17b-405790f91c80
	 *
	 * @param int $length
	 *
	 * @return string
	 */
	protected function uuid(int $length = 0): string {
		$uuid = sprintf(
			'%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff),
			mt_rand(0, 0xffff), mt_rand(0, 0xfff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
			mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
		);

		if ($length > 0) {
			if ($length <= 16) {
				$uuid = str_replace('-', '', $uuid);
			}

			$uuid = substr($uuid, 0, $length);
		}

		return $uuid;
	}


	/**
	 * @param string $line
	 * @param int $length
	 *
	 * @return string
	 */
	protected function cut(string $line, int $length): string {
		if (strlen($line) < $length) {
			return $line;
		}

		return substr($line, 0, $length - 5) . ' (..)';
	}

	/**
	 * @param string $str1
	 * @param string $str2
	 * @param bool $cs case sensitive ?
	 *
	 * @return string
	 */
	protected function commonPart(string $str1, string $str2, bool $cs = true): string {
		for ($i = 0; $i < strlen($str1) && $i < strlen($str2); $i++) {
			$chr1 = $str1[$i];
			$chr2 = $str2[$i];

			if (!$cs) {
				$chr1 = strtolower($chr1);
				$chr2 = strtolower($chr2);
			}

			if ($chr1 !== $chr2) {
				break;
			}
		}

		return substr($str1, 0, $i);
	}


	/**
	 * @param string $line
	 * @param array $params
	 *
	 * @return string
	 */
	protected function feedStringWithParams(string $line, array $params): string {
		$ak = array_keys($params);
		foreach ($ak as $k) {
			$line = str_replace('{' . $k . '}', (string)$params[$k], $line);
		}

		return $line;
	}


	/**
	 * @param int $words
	 *
	 * @return string
	 */
	public function generateRandomSentence(int $words = 5): string {
		$sentence = [];
		for ($i = 0; $i < $words; $i++) {
			$sentence[] = $this->generateRandomWord(rand(2, 12));
		}

		return implode(' ', $sentence);
	}


	/**
	 * @param int $length
	 *
	 * @return string
	 */
	public function generateRandomWord(int $length = 8): string {
		$c = ['b', 'c', 'd', 'f', 'g', 'h', 'j', 'k', 'l', 'm', 'n', 'p', 'r', 's', 't', 'v'];
		$v = ['a', 'e', 'i', 'o', 'u', 'y'];

		$word = [];
		for ($i = 0; $i <= ($length / 2); $i++) {
			$word[] = $c[array_rand($c)];
			$word[] = $v[array_rand($v)];
		}

		return implode('', $word);
	}


	/**
	 * @param int $bytes
	 *
	 * @return string
	 */
	public function humanReadable(int $bytes): string {
		if ($bytes == 0) {
			return '0.00 B';
		}

		$s = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
		$e = floor(log($bytes, 1024));

		return round($bytes / pow(1024, $e), 2) . ' ' . $s[$e];
	}


	/**
	 * @param int $first
	 * @param int $second
	 * @param bool $short
	 *
	 * @return string
	 * @throws Exception
	 */
	public function getDateDiff(
		int $first,
		int $second = 0,
		bool $short = false,
		array $words = []
	): string {
		if ($second === 0) {
			$first = time() - $first;
			$second = time();
		}

		$f = new DateTime('@' . $first);
		$s = new DateTime('@' . $second);
		$duration = $second - $first;
		if ($short) {
			$minutes = $this->get('minutes', $words, 'M');
			$hours = $this->get('hours', $words, 'H');
			$days = $this->get('days', $words, 'D');

			if ($duration < 60) {
				return $f->diff($s)->format('<1' . $minutes);
			}
			if ($duration < 3600) {
				return $f->diff($s)->format('%i' . $minutes);
			}
			if ($duration < 86400) {
				return $f->diff($s)->format('%h' . $hours . ', %i' . $minutes);
			}

			return $f->diff($s)->format('%a' . $days . ', %h' . $hours . ', %i' . $minutes);
		}

		$seconds = $this->get('seconds', $words, 'seconds');
		$minutes = $this->get('minutes', $words, 'minutes');
		$hours = $this->get('hours', $words, 'hours');
		$days = $this->get('days', $words, 'days');
		if ($duration < 60) {
			return $f->diff($s)->format('%s ' . $seconds);
		}

		if ($duration < 3600) {
			return $f->diff($s)->format('%i ' . $minutes . ' and %s ' . $seconds);
		}

		if ($duration < 86400) {
			return $f->diff($s)->format('%h ' . $hours . ', %i ' . $minutes . ' and %s ' . $seconds);
		}

		return $f->diff($s)->format(
			'%a ' . $days .
			', %h ' . $hours .
			', %i ' . $minutes .
			' and %s ' . $seconds
		);
	}
}
