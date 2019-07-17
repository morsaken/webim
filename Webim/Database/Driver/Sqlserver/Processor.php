<?php
/**
 * @author Orhan POLAT
 */

namespace Webim\Database\Driver\Sqlserver;

use Webim\Database\Query\Builder;
use Webim\Database\Query\Processor as BaseProcessor;

class Processor extends BaseProcessor {

  /**
   * Process an "insert get ID" query.
   *
   * @param  Webim\Database\Query\Builder $query
   * @param  string $sql
   * @param  array $values
   * @param  string $sequence
   *
   * @return int
   */
  public function processInsertGetId(Builder $query, $sql, $values, $sequence = null) {
    $query->getConnection()->insert($sql, $values);

    $id = $query->getConnection()->getPdo()->lastInsertId();

    return is_numeric($id) ? (int) $id : $id;
  }

  /**
   * Process the results of a column listing query.
   *
   * @param  array $results
   *
   * @return array
   */
  public function processColumnListing($results) {
    return array_values(array_map(function ($r) {
      return $r->name;
    }, $results));
  }

}