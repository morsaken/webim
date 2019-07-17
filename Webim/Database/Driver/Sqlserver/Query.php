<?php
/**
 * @author Orhan POLAT
 */

namespace Webim\Database\Driver\Sqlserver;

use Webim\Database\Query\Builder;
use Webim\Database\Query\Grammar;

class Query extends Grammar {

  /**
   * All of the available clause operators.
   *
   * @var array
   */
  protected $operators = array(
    '=', '<', '>', '<=', '>=', '!<', '!>', '<>', '!=',
    'like', 'not like', 'between', 'ilike',
    '&', '&=', '|', '|=', '^', '^=',
  );

  /**
   * Compile a select query into SQL.
   *
   * @param Webim\Database\Query\Builder
   *
   * @return string
   */
  public function compileSelect(Builder $query) {
    $components = $this->compileComponents($query);

    // If an offset is present on the query, we will need to wrap the query in
    // a big "ANSI" offset syntax block. This is very nasty compared to the
    // other database systems but is necessary for implementing features.
    if ($query->offset > 0) {
      return $this->compileAnsiOffset($query, $components);
    }

    return $this->concatenate($components);
  }

  /**
   * Compile the "select *" portion of the query.
   *
   * @param Webim\Database\Query\Builder $query
   * @param array $columns
   *
   * @return string
   */
  protected function compileColumns(Builder $query, $columns) {
    if (!is_null($query->aggregate)) return '';

    $select = 'SELECT ' . ($query->distinct ? 'DISTINCT ' : '');

    // If there is a limit on the query, but not an offset, we will add the top
    // clause to the query, which serves as a "limit" type clause within the
    // SQL Server system similar to the limit keywords available in MySQL.
    if ($query->limit > 0 && $query->offset <= 0) {
      $select .= 'TOP ' . $query->limit . ' ';
    }

    return $select . $this->columnize($columns);
  }

  /**
   * Compile the "from" portion of the query.
   *
   * @param Webim\Database\Query\Builder $query
   * @param string $table
   *
   * @return string
   */
  protected function compileFrom(Builder $query, $table) {
    $from = parent::compileFrom($query, $table);

    if (is_string($query->lock)) return $from . ' ' . $query->lock;

    if (!is_null($query->lock)) {
      return $from . ' WITH(rowlock,' . ($query->lock ? 'updlock,' : '') . 'holdlock)';
    } else {
      return $from;
    }
  }

  /**
   * Create a full ANSI offset clause for the query.
   *
   * @param Webim\Database\Query\Builder $query
   * @param array $components
   *
   * @return string
   */
  protected function compileAnsiOffset(Builder $query, $components) {
    // An ORDER BY clause is required to make this offset query work, so if one does
    // not exist we'll just create a dummy clause to trick the database and so it
    // does not complain about the queries for not having an "order by" clause.
    if (!isset($components['orders'])) {
      $components['orders'] = 'ORDER BY (SELECT 0)';
    }

    // We need to add the row number to the query so we can compare it to the offset
    // and limit values given for the statements. So we will add an expression to
    // the "select" that will give back the row numbers on each of the records.
    $orderings = $components['orders'];

    $components['columns'] .= $this->compileOver($orderings);

    unset($components['orders']);

    // Next we need to calculate the constraints that should be placed on the query
    // to get the right offset and limit from our query but if there is no limit
    // set we will just handle the offset only since that is all that matters.
    $constraint = $this->compileRowConstraint($query);

    $sql = $this->concatenate($components);

    // We are now ready to build the final SQL query so we'll create a common table
    // expression from the query and get the records with row numbers within our
    // given limit and offset value that we just put on as a query constraint.
    return $this->compileTableExpression($sql, $constraint);
  }

  /**
   * Compile the over statement for a table expression.
   *
   * @param string $orderings
   *
   * @return string
   */
  protected function compileOver($orderings) {
    return ", row_number() OVER ({$orderings}) AS row_num";
  }

  /**
   * Compile the limit / offset row constraint for a query.
   *
   * @param Webim\Database\Query\Builder $query
   *
   * @return string
   */
  protected function compileRowConstraint($query) {
    $start = $query->offset + 1;

    if ($query->limit > 0) {
      $finish = $query->offset + $query->limit;

      return "BETWEEN {$start} AND {$finish}";
    }

    return ">= {$start}";
  }

  /**
   * Compile a common table expression for a query.
   *
   * @param string $sql
   * @param string $constraint
   *
   * @return string
   */
  protected function compileTableExpression($sql, $constraint) {
    return 'SELECT * FROM (' . $sql . ') AS temp_table WHERE row_num ' . $constraint;
  }

  /**
   * Compile the "limit" portions of the query.
   *
   * @param Webim\Database\Query\Builder $query
   * @param int $limit
   *
   * @return string
   */
  protected function compileLimit(Builder $query, $limit) {
    return '';
  }

  /**
   * Compile the "offset" portions of the query.
   *
   * @param Webim\Database\Query\Builder $query
   * @param int $offset
   *
   * @return string
   */
  protected function compileOffset(Builder $query, $offset) {
    return '';
  }

  /**
   * Compile a truncate table statement into SQL.
   *
   * @param Webim\Database\Query\Builder $query
   *
   * @return array
   */
  public function compileTruncate(Builder $query) {
    return array('TRUNCATE TABLE ' . $this->wrapTable($query->from) => array());
  }

  /**
   * Get the format for database stored dates.
   *
   * @return string
   */
  public function getDateFormat() {
    return 'Y-m-d H:i:s.000';
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

    return '[' . str_replace(']', ']]', $value) . ']';
  }

}