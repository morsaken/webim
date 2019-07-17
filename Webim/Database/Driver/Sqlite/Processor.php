<?php
/**
 * @author Orhan POLAT
 */

namespace Webim\Database\Driver\Sqlite;

use Webim\Database\Query\Processor as BaseProcessor;

class Processor extends BaseProcessor {

  /**
   * Process the results of a column listing query.
   *
   * @param array $results
   *
   * @return array
   */
  public function processColumnListing($results) {
    return array_values(array_map(function ($r) {
      return $r->name;
    }, $results));
  }

}