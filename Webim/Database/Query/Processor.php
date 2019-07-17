<?php
/**
 * @author Orhan POLAT
 */

namespace Webim\Database\Query;

class Processor {

  /**
   * Process the results of a "select" query.
   *
   * @param Webim\Database\Query\Builder $query
   * @param array $results
   *
   * @return array
   */
  public function processSelect(Builder $query, $results) {
    return $results;
  }

  /**
   * Process an  "insert get ID" query.
   *
   * @param Webim\Database\Query\Builder $query
   * @param string $sql
   * @param array $values
   * @param string $sequence
   *
   * @return \stdClass
   */
  public function processInsertGetId(Builder $query, $sql, $values, $sequence = null) {
    $result = $query->getConnection()->insert($sql, $values);

    if ($result->success) {
      $id = $query->getConnection()->getPdo()->lastInsertId($sequence);

      $result->id = is_numeric($id) ? (int)$id : $id;
    }

    return $result;
  }

  /**
   * Process the results of a column listing query.
   *
   * @param array $results
   *
   * @return array
   */
  public function processColumnListing($results) {
    return $results;
  }

}