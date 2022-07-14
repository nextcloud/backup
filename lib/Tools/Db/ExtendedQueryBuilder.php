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


namespace OCA\Backup\Tools\Db;

use DateInterval;
use DateTime;
use Doctrine\DBAL\Query\QueryBuilder as DBALQueryBuilder;
use Exception;
use OC;
use OC\DB\QueryBuilder\QueryBuilder;
use OC\SystemConfig;
use OCA\Backup\Tools\Exceptions\DateTimeException;
use OCA\Backup\Tools\Exceptions\InvalidItemException;
use OCA\Backup\Tools\Exceptions\RowNotFoundException;
use OCA\Backup\Tools\Traits\TArrayTools;
use OCP\DB\QueryBuilder\ICompositeExpression;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

class ExtendedQueryBuilder extends QueryBuilder {
	use TArrayTools;


	/** @var string */
	private $defaultSelectAlias = '';

	/** @var array */
	private $defaultValues = [];


	public function __construct() {
		parent::__construct(
			OC::$server->get(IDBConnection::class),
			OC::$server->get(SystemConfig::class),
			OC::$server->get(LoggerInterface::class)
		);
	}


	/**
	 * @param string $alias
	 *
	 * @return self
	 */
	public function setDefaultSelectAlias(string $alias): self {
		$this->defaultSelectAlias = $alias;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getDefaultSelectAlias(): string {
		return $this->defaultSelectAlias;
	}


	/**
	 * @return array
	 */
	public function getDefaultValues(): array {
		return $this->defaultValues;
	}

	/**
	 * @param string $key
	 * @param string $value
	 *
	 * @return $this
	 */
	public function addDefaultValue(string $key, string $value): self {
		$this->defaultValues[$key] = $value;

		return $this;
	}

	/**
	 * @param int $size
	 * @param int $page
	 */
	public function paginate(int $size, int $page = 0): void {
		if ($page < 0) {
			$page = 0;
		}

		$this->chunk($page * $size, $size);
	}

	/**
	 * @param int $offset
	 * @param int $limit
	 */
	public function chunk(int $offset, int $limit): void {
		if ($offset > -1) {
			$this->setFirstResult($offset);
		}

		if ($limit > 0) {
			$this->setMaxResults($limit);
		}
	}


	/**
	 * Limit the request to the Id
	 *
	 * @param int $id
	 */
	public function limitToId(int $id): void {
		$this->limitInt('id', $id);
	}

	/**
	 * @param array $ids
	 */
	public function limitToIds(array $ids): void {
		$this->limitArray('id', $ids);
	}

	/**
	 * @param string $id
	 */
	public function limitToIdString(string $id): void {
		$this->limit('id', $id);
	}

	/**
	 * @param string $userId
	 */
	public function limitToUserId(string $userId): void {
		$this->limit('user_id', $userId);
	}

	/**
	 * @param string $uniqueId
	 */
	public function limitToUniqueId(string $uniqueId): void {
		$this->limit('unique_id', $uniqueId);
	}

	/**
	 * @param string $memberId
	 */
	public function limitToMemberId(string $memberId): void {
		$this->limit('member_id', $memberId);
	}

	/**
	 * @param string $status
	 */
	public function limitToStatus(string $status): void {
		$this->limit('status', $status, '', false);
	}

	/**
	 * @param int $type
	 */
	public function limitToType(int $type): void {
		$this->limitInt('type', $type);
	}

	/**
	 * @param string $type
	 */
	public function limitToTypeString(string $type): void {
		$this->limit('type', $type, '', false);
	}

	/**
	 * @param string $token
	 */
	public function limitToToken(string $token): void {
		$this->limit('token', $token);
	}


	/**
	 * Limit the request to the creation
	 *
	 * @param int $delay
	 *
	 * @return self
	 * @throws Exception
	 */
	public function limitToCreation(int $delay = 0): self {
		$date = new DateTime('now');
		$date->sub(new DateInterval('PT' . $delay . 'M'));

		$this->limitToDBFieldDateTime('creation', $date, true);

		return $this;
	}


	/**
	 * @param string $field
	 * @param DateTime $date
	 * @param bool $orNull
	 */
	public function limitToDBFieldDateTime(string $field, DateTime $date, bool $orNull = false): void {
		$expr = $this->expr();
		$pf = ($this->getType() === DBALQueryBuilder::SELECT) ? $this->getDefaultSelectAlias()
																. '.' : '';
		$field = $pf . $field;

		$orX = $expr->orX();
		$orX->add(
			$expr->lte($field, $this->createNamedParameter($date, IQueryBuilder::PARAM_DATE))
		);

		if ($orNull === true) {
			$orX->add($expr->isNull($field));
		}

		$this->andWhere($orX);
	}


	/**
	 * @param int $timestamp
	 * @param string $field
	 *
	 * @throws DateTimeException
	 */
	public function limitToSince(int $timestamp, string $field): void {
		try {
			$dTime = new DateTime();
			$dTime->setTimestamp($timestamp);
		} catch (Exception $e) {
			throw new DateTimeException($e->getMessage());
		}

		$expr = $this->expr();
		$pf = ($this->getType() === DBALQueryBuilder::SELECT) ? $this->getDefaultSelectAlias() . '.' : '';
		$field = $pf . $field;

		$orX = $expr->orX();
		$orX->add(
			$expr->gte($field, $this->createNamedParameter($dTime, IQueryBuilder::PARAM_DATE))
		);

		$this->andWhere($orX);
	}


	/**
	 * @param string $field
	 * @param string $value
	 */
	public function searchInDBField(string $field, string $value): void {
		$expr = $this->expr();

		$pf = ($this->getType() === DBALQueryBuilder::SELECT) ? $this->getDefaultSelectAlias() . '.' : '';
		$field = $pf . $field;

		$this->andWhere($expr->iLike($field, $this->createNamedParameter($value)));
	}


	/**
	 * @param string $field
	 * @param string $value
	 * @param string $alias
	 * @param bool $cs
	 */
	public function like(string $field, string $value, string $alias = '', bool $cs = true): void {
		$this->andWhere($this->exprLike($field, $value, $alias, $cs));
	}


	/**
	 * @param string $field
	 * @param string $value
	 * @param string $alias
	 * @param bool $cs
	 */
	public function limit(string $field, string $value, string $alias = '', bool $cs = true): void {
		$this->andWhere($this->exprLimit($field, $value, $alias, $cs));
	}

	/**
	 * @param string $field
	 * @param int $value
	 * @param string $alias
	 */
	public function limitInt(string $field, int $value, string $alias = ''): void {
		$this->andWhere($this->exprLimitInt($field, $value, $alias));
	}

	/**
	 * @param string $field
	 * @param bool $value
	 * @param string $alias
	 */
	public function limitBool(string $field, bool $value, string $alias = ''): void {
		$this->andWhere($this->exprLimitBool($field, $value, $alias));
	}

	/**
	 * @param string $field
	 * @param bool $orNull
	 * @param string $alias
	 */
	public function limitEmpty(string $field, bool $orNull = false, string $alias = ''): void {
		$this->andWhere($this->exprLimitEmpty($field, $orNull, $alias));
	}

	/**
	 * @param string $field
	 * @param bool $orEmpty
	 * @param string $alias
	 */
	public function limitNull(string $field, bool $orEmpty = false, string $alias = ''): void {
		$this->andWhere($this->exprLimitNull($field, $orEmpty, $alias));
	}

	/**
	 * @param string $field
	 * @param array $value
	 * @param string $alias
	 * @param bool $cs
	 */
	public function limitArray(string $field, array $value, string $alias = '', bool $cs = true): void {
		$this->andWhere($this->exprLimitArray($field, $value, $alias, $cs));
	}

	/**
	 * @param string $field
	 * @param array $value
	 * @param string $alias
	 */
	public function limitInArray(string $field, array $value, string $alias = ''): void {
		$this->andWhere($this->exprLimitInArray($field, $value, $alias));
	}

	/**
	 * @param string $field
	 * @param int $flag
	 * @param string $alias
	 */
	public function limitBitwise(string $field, int $flag, string $alias = ''): void {
		$this->andWhere($this->exprLimitBitwise($field, $flag, $alias));
	}

	/**
	 * @param string $field
	 * @param int $value
	 * @param bool $gte
	 * @param string $alias
	 */
	public function gt(string $field, int $value, bool $gte = false, string $alias = ''): void {
		$this->andWhere($this->exprGt($field, $value, $gte, $alias));
	}

	/**
	 * @param string $field
	 * @param int $value
	 * @param bool $lte
	 * @param string $alias
	 */
	public function lt(string $field, int $value, bool $lte = false, string $alias = ''): void {
		$this->andWhere($this->exprLt($field, $value, $lte, $alias));
	}


	/**
	 * @param string $field
	 * @param string $value
	 * @param string $alias
	 * @param bool $cs
	 *
	 * @return string
	 */
	public function exprLike(string $field, string $value, string $alias = '', bool $cs = true): string {
		if ($this->getType() === DBALQueryBuilder::SELECT) {
			$field = (($alias === '') ? $this->getDefaultSelectAlias() : $alias) . '.' . $field;
		}

		$expr = $this->expr();
		if ($cs) {
			return $expr->like($field, $this->createNamedParameter($value));
		} else {
			return $expr->iLike($field, $this->createNamedParameter($value));
		}
	}


	/**
	 * @param string $field
	 * @param string $value
	 * @param string $alias
	 * @param bool $cs
	 *
	 * @return string
	 */
	public function exprLimit(string $field, string $value, string $alias = '', bool $cs = true): string {
		if ($this->getType() === DBALQueryBuilder::SELECT) {
			$field = (($alias === '') ? $this->getDefaultSelectAlias() : $alias) . '.' . $field;
		}

		$expr = $this->expr();
		if ($value === '') {
			return $expr->emptyString($field);
		}
		if ($cs) {
			return $expr->eq($field, $this->createNamedParameter($value));
		} else {
			$func = $this->func();

			return $expr->eq($func->lower($field), $func->lower($this->createNamedParameter($value)));
		}
	}


	/**
	 * @param string $field
	 * @param int $value
	 * @param string $alias
	 *
	 * @return string
	 */
	public function exprLimitInt(string $field, int $value, string $alias = ''): string {
		if ($this->getType() === DBALQueryBuilder::SELECT) {
			$field = (($alias === '') ? $this->getDefaultSelectAlias() : $alias) . '.' . $field;
		}

		$expr = $this->expr();

		return $expr->eq($field, $this->createNamedParameter($value, IQueryBuilder::PARAM_INT));
	}


	/**
	 * @param string $field
	 * @param bool $value
	 * @param string $alias
	 *
	 * @return string
	 */
	public function exprLimitBool(string $field, bool $value, string $alias = ''): string {
		if ($this->getType() === DBALQueryBuilder::SELECT) {
			$field = (($alias === '') ? $this->getDefaultSelectAlias() : $alias) . '.' . $field;
		}

		$expr = $this->expr();

		return $expr->eq($field, $this->createNamedParameter($value, IQueryBuilder::PARAM_BOOL));
	}

	/**
	 * @param string $field
	 * @param bool $orNull
	 * @param string $alias
	 *
	 * @return ICompositeExpression
	 */
	public function exprLimitEmpty(
		string $field,
		bool $orNull = false,
		string $alias = ''
	): ICompositeExpression {
		if ($this->getType() === DBALQueryBuilder::SELECT) {
			$field = (($alias === '') ? $this->getDefaultSelectAlias() : $alias) . '.' . $field;
		}

		$expr = $this->expr();
		$orX = $expr->orX();
		$orX->add($expr->emptyString($field));
		if ($orNull) {
			$orX->add($expr->isNull($field));
		}

		return $orX;
	}

	/**
	 * @param string $field
	 * @param bool $orEmpty
	 * @param string $alias
	 *
	 * @return ICompositeExpression
	 */
	public function exprLimitNull(
		string $field,
		bool $orEmpty = false,
		string $alias = ''
	): ICompositeExpression {
		if ($this->getType() === DBALQueryBuilder::SELECT) {
			$field = (($alias === '') ? $this->getDefaultSelectAlias() : $alias) . '.' . $field;
		}

		$expr = $this->expr();
		$orX = $expr->orX();
		$orX->add($expr->isNull($field));
		if ($orEmpty) {
			$orX->add($expr->emptyString($field));
		}

		return $orX;
	}


	/**
	 * @param string $field
	 * @param array $values
	 * @param string $alias
	 * @param bool $cs
	 *
	 * @return ICompositeExpression
	 */
	public function exprLimitArray(
		string $field,
		array $values,
		string $alias = '',
		bool $cs = true
	): ICompositeExpression {
		if ($this->getType() === DBALQueryBuilder::SELECT) {
			$field = (($alias === '') ? $this->getDefaultSelectAlias() : $alias) . '.' . $field;
		}

		$andX = $this->expr()->andX();
		foreach ($values as $value) {
			if (is_integer($value)) {
				$andX->add($this->exprLimitInt($field, $value, $alias));
			} else {
				$andX->add($this->exprLimit($field, $value, $alias, $cs));
			}
		}

		return $andX;
	}


	/**
	 * @param string $field
	 * @param array $values
	 * @param string $alias
	 *
	 * @return string
	 */
	public function exprLimitInArray(string $field, array $values, string $alias = ''): string {
		if ($this->getType() === DBALQueryBuilder::SELECT) {
			$field = (($alias === '') ? $this->getDefaultSelectAlias() : $alias) . '.' . $field;
		}

		$expr = $this->expr();

		return $expr->in($field, $this->createNamedParameter($values, IQueryBuilder::PARAM_STR_ARRAY));
	}


	/**
	 * @param string $field
	 * @param int $flag
	 * @param string $alias
	 *
	 * @return string
	 */
	public function exprLimitBitwise(string $field, int $flag, string $alias = ''): string {
		if ($this->getType() === DBALQueryBuilder::SELECT) {
			$field = (($alias === '') ? $this->getDefaultSelectAlias() : $alias) . '.' . $field;
		}

		$expr = $this->expr();

		return $expr->gt(
			$expr->bitwiseAnd($field, $flag),
			$this->createNamedParameter(0, IQueryBuilder::PARAM_INT)
		);
	}


	/**
	 * @param string $field
	 * @param int $value
	 * @param bool $lte
	 * @param string $alias
	 *
	 * @return string
	 */
	public function exprLt(string $field, int $value, bool $lte = false, string $alias = ''): string {
		if ($this->getType() === DBALQueryBuilder::SELECT) {
			$field = (($alias === '') ? $this->getDefaultSelectAlias() : $alias) . '.' . $field;
		}

		$expr = $this->expr();

		if ($lte) {
			return $expr->lte($field, $this->createNamedParameter($value, IQueryBuilder::PARAM_INT));
		} else {
			return $expr->lt($field, $this->createNamedParameter($value, IQueryBuilder::PARAM_INT));
		}
	}

	/**
	 * @param string $field
	 * @param int $value
	 * @param bool $gte
	 * @param string $alias
	 *
	 * @return string
	 */
	public function exprGt(string $field, int $value, bool $gte = false, string $alias = ''): string {
		if ($this->getType() === DBALQueryBuilder::SELECT) {
			$field = (($alias === '') ? $this->getDefaultSelectAlias() : $alias) . '.' . $field;
		}

		$expr = $this->expr();

		if ($gte) {
			return $expr->gte($field, $this->createNamedParameter($value, IQueryBuilder::PARAM_INT));
		} else {
			return $expr->gt($field, $this->createNamedParameter($value, IQueryBuilder::PARAM_INT));
		}
	}


	/**
	 * @param string $field
	 * @param string $value
	 * @param string $alias
	 * @param bool $cs
	 */
	public function unlike(string $field, string $value, string $alias = '', bool $cs = true): void {
		$this->andWhere($this->exprUnlike($field, $value, $alias, $cs));
	}


	/**
	 * @param string $field
	 * @param string $value
	 * @param string $alias
	 * @param bool $cs
	 */
	public function filter(string $field, string $value, string $alias = '', bool $cs = true): void {
		$this->andWhere($this->exprFilter($field, $value, $alias, $cs));
	}

	/**
	 * @param string $field
	 * @param int $value
	 * @param string $alias
	 */
	public function filterInt(string $field, int $value, string $alias = ''): void {
		$this->andWhere($this->exprFilterInt($field, $value, $alias));
	}

	/**
	 * @param string $field
	 * @param bool $value
	 * @param string $alias
	 */
	public function filterBool(string $field, bool $value, string $alias = ''): void {
		$this->andWhere($this->exprFilterBool($field, $value, $alias));
	}

	/**
	 * @param string $field
	 * @param bool $norNull
	 * @param string $alias
	 */
	public function filterEmpty(string $field, bool $norNull = false, string $alias = ''): void {
		$this->andWhere($this->exprFilterEmpty($field, $norNull, $alias));
	}

	/**
	 * @param string $field
	 * @param bool $norEmpty
	 * @param string $alias
	 */
	public function filterNull(string $field, bool $norEmpty = false, string $alias = ''): void {
		$this->andWhere($this->exprFilterNull($field, $norEmpty, $alias));
	}

	/**
	 * @param string $field
	 * @param array $value
	 * @param string $alias
	 * @param bool $cs
	 */
	public function filterArray(string $field, array $value, string $alias = '', bool $cs = true): void {
		$this->andWhere($this->exprFilterArray($field, $value, $alias, $cs));
	}

	/**
	 * @param string $field
	 * @param array $value
	 * @param string $alias
	 */
	public function filterInArray(string $field, array $value, string $alias = ''): void {
		$this->andWhere($this->exprFilterInArray($field, $value, $alias));
	}

	/**
	 * @param string $field
	 * @param int $flag
	 * @param string $alias
	 */
	public function filterBitwise(string $field, int $flag, string $alias = ''): void {
		$this->andWhere($this->exprFilterBitwise($field, $flag, $alias));
	}


	/**
	 * @param string $field
	 * @param string $value
	 * @param string $alias
	 * @param bool $cs
	 *
	 * @return string
	 */
	public function exprUnlike(string $field, string $value, string $alias = '', bool $cs = true): string {
		if ($this->getType() === DBALQueryBuilder::SELECT) {
			$field = (($alias === '') ? $this->getDefaultSelectAlias() : $alias) . '.' . $field;
		}

		$expr = $this->expr();
		if ($cs) {
			return $expr->notLike($field, $this->createNamedParameter($value));
		} else {
			$func = $this->func();

			return $expr->notLike($func->lower($field), $func->lower($this->createNamedParameter($value)));
		}
	}


	/**
	 * @param string $field
	 * @param string $value
	 * @param string $alias
	 * @param bool $cs
	 *
	 * @return string
	 */
	public function exprFilter(string $field, string $value, string $alias = '', bool $cs = true): string {
		if ($this->getType() === DBALQueryBuilder::SELECT) {
			$field = (($alias === '') ? $this->getDefaultSelectAlias() : $alias) . '.' . $field;
		}

		$expr = $this->expr();
		if ($value === '') {
			return $expr->nonEmptyString($field);
		}
		if ($cs) {
			return $expr->neq($field, $this->createNamedParameter($value));
		} else {
			$func = $this->func();

			return $expr->neq($func->lower($field), $func->lower($this->createNamedParameter($value)));
		}
	}


	/**
	 * @param string $field
	 * @param int $value
	 * @param string $alias
	 *
	 * @return string
	 */
	public function exprFilterInt(string $field, int $value, string $alias = ''): string {
		if ($this->getType() === DBALQueryBuilder::SELECT) {
			$field = (($alias === '') ? $this->getDefaultSelectAlias() : $alias) . '.' . $field;
		}

		$expr = $this->expr();

		return $expr->neq($field, $this->createNamedParameter($value, IQueryBuilder::PARAM_INT));
	}


	/**
	 * @param string $field
	 * @param bool $value
	 * @param string $alias
	 *
	 * @return string
	 */
	public function exprFilterBool(string $field, bool $value, string $alias = ''): string {
		if ($this->getType() === DBALQueryBuilder::SELECT) {
			$field = (($alias === '') ? $this->getDefaultSelectAlias() : $alias) . '.' . $field;
		}

		$expr = $this->expr();

		return $expr->neq($field, $this->createNamedParameter($value, IQueryBuilder::PARAM_BOOL));
	}

	/**
	 * @param string $field
	 * @param bool $norNull
	 * @param string $alias
	 *
	 * @return ICompositeExpression
	 */
	public function exprFilterEmpty(
		string $field,
		bool $norNull = false,
		string $alias = ''
	): ICompositeExpression {
		if ($this->getType() === DBALQueryBuilder::SELECT) {
			$field = (($alias === '') ? $this->getDefaultSelectAlias() : $alias) . '.' . $field;
		}

		$expr = $this->expr();
		$andX = $expr->andX();
		$andX->add($expr->nonEmptyString($field));
		if ($norNull) {
			$andX->add($expr->isNotNull($field));
		}

		return $andX;
	}

	/**
	 * @param string $field
	 * @param bool $norEmpty
	 * @param string $alias
	 *
	 * @return ICompositeExpression
	 */
	public function exprFilterNull(
		string $field,
		bool $norEmpty = false,
		string $alias = ''
	): ICompositeExpression {
		if ($this->getType() === DBALQueryBuilder::SELECT) {
			$field = (($alias === '') ? $this->getDefaultSelectAlias() : $alias) . '.' . $field;
		}

		$expr = $this->expr();
		$andX = $expr->andX();
		$andX->add($expr->isNotNull($field));
		if ($norEmpty) {
			$andX->add($expr->nonEmptyString($field));
		}

		return $andX;
	}


	/**
	 * @param string $field
	 * @param array $values
	 * @param string $alias
	 * @param bool $cs
	 *
	 * @return ICompositeExpression
	 */
	public function exprFilterArray(
		string $field,
		array $values,
		string $alias = '',
		bool $cs = true
	): ICompositeExpression {
		if ($this->getType() === DBALQueryBuilder::SELECT) {
			$field = (($alias === '') ? $this->getDefaultSelectAlias() : $alias) . '.' . $field;
		}

		$orX = $this->expr()->orX();
		foreach ($values as $value) {
			if (is_integer($value)) {
				$orX->add($this->exprFilterInt($field, $value, $alias));
			} else {
				$orX->add($this->exprFilter($field, $value, $alias, $cs));
			}
		}

		return $orX;
	}


	/**
	 * @param string $field
	 * @param array $values
	 * @param string $alias
	 *
	 * @return string
	 */
	public function exprFilterInArray(string $field, array $values, string $alias = ''): string {
		if ($this->getType() === DBALQueryBuilder::SELECT) {
			$field = (($alias === '') ? $this->getDefaultSelectAlias() : $alias) . '.' . $field;
		}

		$expr = $this->expr();

		return $expr->notIn($field, $this->createNamedParameter($values, IQueryBuilder::PARAM_STR_ARRAY));
	}


	/**
	 * @param string $field
	 * @param int $flag
	 * @param string $alias
	 *
	 * @return string
	 */
	public function exprFilterBitwise(string $field, int $flag, string $alias = ''): string {
		if ($this->getType() === DBALQueryBuilder::SELECT) {
			$field = (($alias === '') ? $this->getDefaultSelectAlias() : $alias) . '.' . $field;
		}

		$expr = $this->expr();

		return $expr->eq(
			$expr->bitwiseAnd($field, $flag),
			$this->createNamedParameter(0, IQueryBuilder::PARAM_INT)
		);
	}


	/**
	 * @param string $object
	 * @param array $params
	 *
	 * @return IQueryRow
	 * @throws RowNotFoundException
	 * @throws InvalidItemException
	 */
	public function asItem(string $object, array $params = []): IQueryRow {
		return $this->getRow([$this, 'parseSimpleSelectSql'], $object, $params);
	}

	/**
	 * @param string $object
	 * @param array $params
	 *
	 * @return IQueryRow[]
	 */
	public function asItems(string $object, array $params = []): array {
		return $this->getRows([$this, 'parseSimpleSelectSql'], $object, $params);
	}


	/**
	 * @param string $field
	 * @param array $params
	 *
	 * @return IQueryRow
	 * @throws InvalidItemException
	 * @throws RowNotFoundException
	 */
	public function asItemFromField(string $field, array $params = []): IQueryRow {
		$param['modelFromField'] = $field;

		return $this->getRow([$this, 'parseSimpleSelectSql'], '', $params);
	}

	/**
	 * @param string $field
	 * @param array $params
	 *
	 * @return IQueryRow[]
	 */
	public function asItemsFromField(string $field, array $params = []): array {
		$param['modelFromField'] = $field;

		return $this->getRows([$this, 'parseSimpleSelectSql'], $field, $params);
	}


	/**
	 * @param array $data
	 * @param ExtendedQueryBuilder $qb
	 * @param string $object
	 * @param array $params
	 *
	 * @return IQueryRow
	 * @throws InvalidItemException
	 */
	private function parseSimpleSelectSql(
		array $data,
		ExtendedQueryBuilder $qb,
		string $object,
		array $params
	): IQueryRow {
		$fromField = $this->get('modelFromField', $params);
		if ($fromField !== '') {
			$object = $fromField;
		}

		$item = new $object();
		if (!($item instanceof IQueryRow)) {
			throw new InvalidItemException();
		}

		if (!empty($params)) {
			$data['_params'] = $params;
		}

		foreach ($qb->getDefaultValues() as $k => $v) {
			if ($this->get($k, $data) === '') {
				$data[$k] = $v;
			}
		}

		$data = array_merge($qb->getDefaultValues(), $data);

		$item->importFromDatabase($data);

		return $item;
	}


	/**
	 * @param callable $method
	 * @param string $object
	 * @param array $params
	 *
	 * @return IQueryRow
	 * @throws RowNotFoundException
	 */
	public function getRow(callable $method, string $object = '', array $params = []): IQueryRow {
		$cursor = $this->execute();
		$data = $cursor->fetch();
		$cursor->closeCursor();

		if ($data === false) {
			throw new RowNotFoundException();
		}

		return $method($data, $this, $object, $params);
	}


	/**
	 * @param callable $method
	 * @param string $object
	 * @param array $params
	 *
	 * @return IQueryRow[]
	 */
	public function getRows(callable $method, string $object = '', array $params = []): array {
		$rows = [];
		$cursor = $this->execute();
		while ($data = $cursor->fetch()) {
			try {
				$rows[] = $method($data, $this, $object, $params);
			} catch (Exception $e) {
			}
		}
		$cursor->closeCursor();

		return $rows;
	}


	/**
	 * @param string $table
	 * @param array $fields
	 * @param string $alias
	 *
	 * @return $this
	 */
	public function generateSelect(
		string $table,
		array $fields,
		string $alias = ''
	): self {
		$selectFields = array_map(
			function (string $item) use ($alias) {
				if ($alias === '') {
					return $item;
				}

				return $alias . '.' . $item;
			}, $fields
		);

		$this->select($selectFields)
			 ->from($table, $alias)
			 ->setDefaultSelectAlias($alias);

		return $this;
	}


	/**
	 * @param array $fields
	 * @param string $alias
	 * @param string $prefix
	 * @param array $default
	 *
	 * @return $this
	 */
	public function generateSelectAlias(
		array $fields,
		string $alias,
		string $prefix,
		array $default = []
	): self {
		$prefix = trim($prefix) . '_';
		foreach ($default as $k => $v) {
			$this->addDefaultValue($prefix . $k, (string)$v);
		}

		foreach ($fields as $field) {
			$this->selectAlias($alias . '.' . $field, $prefix . $field);
		}

		return $this;
	}
}
