<?php
/**
 * @author Orhan POLAT
 */

namespace Webim\Database\Driver\Mysql;

use Webim\Database\Query\Builder;
use Webim\Database\Query\Grammar;

class Query extends Grammar {

  /**
   * The components that make up a select clause.
   *
   * @var array
   */
  protected $selectComponents = array(
    'aggregate',
    'columns',
    'from',
    'joins',
    'wheres',
    'groups',
    'havings',
    'orders',
    'limit',
    'offset',
    'lock'
  );

  /**
   * Compile a select query into SQL.
   *
   * @param Webim\Database\Query\Builder
   *
   * @return string
   */
  public function compileSelect(Builder $query) {
    $sql = parent::compileSelect($query);

    if ($query->unions) {
      $sql = '(' . $sql . ') ' . $this->compileUnions($query);
    }

    return $sql;
  }

  /**
   * Compile an update statement into SQL.
   *
   * @param Webim\Database\Query\Builder $query
   * @param array $values
   *
   * @return string
   */
  public function compileUpdate(Builder $query, $values) {
    $sql = parent::compileUpdate($query, $values);

    if (isset($query->orders)) {
      $sql .= ' ' . $this->compileOrders($query, $query->orders);
    }

    if (isset($query->limit)) {
      $sql .= ' ' . $this->compileLimit($query, $query->limit);
    }

    return rtrim($sql);
  }

  /**
   * Compile a single union statement.
   *
   * @param array $union
   *
   * @return string
   */
  protected function compileUnion(array $union) {
    $joiner = $union['all'] ? ' UNION ALL ' : ' UNION ';

    return $joiner . '(' . $union['query']->toSql() . ')';
  }

  /**
   * Compile the lock into SQL.
   *
   * @param Webim\Database\Query\Builder $query
   * @param bool|string $value
   *
   * @return string
   */
  protected function compileLock(Builder $query, $value) {
    if (is_string($value)) return $value;

    return $value ? 'FOR UPDATE' : 'LOCK IN SHARE MODE';
  }

  /**
   * Wrap a single string in keyword identifiers.
   *
   * @param string $value
   *
   * @return string
   */
  protected function wrapValue($value) {
    if ($value === '*') return $value;

    return '`' . str_replace('`', '``', $value) . '`';
  }

}