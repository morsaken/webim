<?php
/**
 * @author Orhan POLAT
 */

namespace Webim\Database\Driver\Mysql;

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
    return array_map(function ($r) {
      return $r->column_name;
    }, $results);
  }

}