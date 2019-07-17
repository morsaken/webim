<?php
/**
 * @author Orhan POLAT
 */

namespace Webim\Database\Query;

class Join {

  /**
   * The query builder instance.
   *
   * @var Webim\Database\Query\Builder
   */
  public $query;

  /**
   * The type of join being performed.
   *
   * @var string
   */
  public $type;

  /**
   * The table the join clause is joining to.
   *
   * @var string
   */
  public $table;

  /**
   * The "on" clauses for the join.
   *
   * @var array
   */
  public $clauses = array();

  /**
   * All of the available clause operators.
   *
   * @var array
   */
  protected $operators = array(
    '=', '<', '>', '<=', '>=', '<>', '!=',
    'like', 'not like', 'between', 'ilike',
    '&', '|', '^', '<<', '>>',
    'rlike', 'regexp', 'not regexp'
  );

  /**
   * Create a new join clause instance.
   *
   * @param Webim\Database\Query\Builder $query
   * @param string $type
   * @param string $table
   */
  public function __construct(Builder $query, $type, $table) {
    $this->type = $type;
    $this->query = $query;
    $this->table = $table;
  }

  /**
   * Add an "or on" clause to the join.
   *
   * @param string $first
   * @param string $operator
   * @param string $second
   *
   * @return Webim\Database\Query\Join
   */
  public function orOn($first, $operator = null, $second = null) {
    return $this->on($first, $operator, $second, 'or');
  }

  /**
   * Add an "on" clause to the join.
   *
   * @param string $first
   * @param string $operator
   * @param string $second
   * @param string $boolean
   * @param bool $where
   *
   * @return $this
   */
  public function on($first, $operator = null, $second = null, $boolean = 'and', $where = false) {
    // Here we will make some assumptions about the operator. If only 2 values are
    // passed to the method, we will assume that the operator is an equals sign
    // and keep going. Otherwise, we'll require the operator to be passed in.
    if (func_num_args() == 2) {
      list($second, $operator) = array($operator, '=');
    } elseif ($this->invalidOperatorAndValue($operator, $second)) {
      throw new \InvalidArgumentException("Second value must be provided.");
    }

    // If the given operator is not found in the list of valid operators we will
    // assume that the developer is just short-cutting the '=' operators and
    // we will set the operators to '=' and set the values appropriately.
    if ($operator instanceof Expression) {
      $operator = $operator->getValue();
    } elseif (!in_array(strtolower($operator), $this->operators, true)) {
      list($second, $operator) = array($operator, '=');
    }

    if ($second instanceof Expression) {
      $second = $second->getValue();
      $where = true;
    }

    $this->clauses[] = compact('first', 'operator', 'second', 'boolean', 'where');

    if ($where) $this->query->addBinding($second);

    return $this;
  }

  /**
   * Determine if the given operator and value combination is legal.
   *
   * @param string $operator
   * @param mixed $value
   *
   * @return bool
   */
  protected function invalidOperatorAndValue($operator, $value) {
    $isOperator = in_array($operator, $this->operators);

    return ($isOperator && ($operator != '=') && is_null($value));
  }

  /**
   * Add an "on where" clause to the join.
   *
   * @param string $first
   * @param string $operator
   * @param string $second
   * @param string $boolean
   *
   * @return Webim\Database\Query\Join
   */
  public function where($first, $operator, $second, $boolean = 'and') {
    return $this->on($first, $operator, $second, $boolean, true);
  }

  /**
   * Add an "or on where" clause to the join.
   *
   * @param string $first
   * @param string $operator
   * @param string $second
   *
   * @return Webim\Database\Query\Join
   */
  public function orWhere($first, $operator, $second) {
    return $this->on($first, $operator, $second, 'or', true);
  }

  /**
   * Add an "on where is null" clause to the join
   *
   * @param  $column
   * @param string $boolean
   *
   * @return Webim\Database\Query\Join
   */
  public function whereNull($column, $boolean = 'and') {
    return $this->on($column, new Expression('IS NULL'), null, $boolean, false);
  }

  /**
   * Add an "or on in" clause to the join.
   *
   * @param string $first
   * @param array $second
   *
   * @return Webim\Database\Query\Join
   */
  public function orWhereIn($first, array $second) {
    return $this->whereIn($first, $second, 'or');
  }

  /**
   * Add an "on in" clause to the join.
   *
   * @param string $first
   * @param array $second
   * @param string $boolean
   * @param bool|false $not
   *
   * @return Webim\Database\Query\Join
   */
  public function whereIn($first, array $second, $boolean = 'and', $not = false) {
    $operator = ($not ? 'NOT ' : '') . 'IN';

    $this->clauses[] = compact('first', 'operator', 'second', 'boolean');

    $this->query->addBinding($second);

    return $this;
  }

  /**
   * Add an "or on not in" clause to the join.
   *
   * @param string $first
   * @param array $second
   *
   * @return Webim\Database\Query\Join
   */
  public function orWhereNotIn($first, array $second) {
    return $this->whereNotIn($first, $second, 'or');
  }

  /**
   * Add an "on not in" clause to the join.
   *
   * @param string $first
   * @param array $second
   * @param string $boolean
   *
   * @return Webim\Database\Query\Join
   */
  public function whereNotIn($first, array $second, $boolean = 'and') {
    return $this->whereIn($first, $second, $boolean, true);
  }

}