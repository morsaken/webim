<?php
/**
 * @author Orhan POLAT
 */

namespace Webim\Database\Driver\Mysql;

use Webim\Database\Driver\Connection as BaseConnection;

class Connection extends BaseConnection {

  /**
   * Get a schema builder instance for the connection.
   *
   * @return Webim\Database\Driver\Mysql\Builder
   */
  public function getSchemaBuilder() {
    if (is_null($this->schemaGrammar)) {
      $this->useDefaultSchemaGrammar();
    }

    return new Builder($this);
  }

  /**
   * Get the default query grammar instance.
   *
   * @return Webim\Database\Driver\Mysql\Query
   */
  protected function getDefaultQueryGrammar() {
    return $this->withTablePrefix(new Query);
  }

  /**
   * Get the default schema grammar instance.
   *
   * @return Webim\Database\Driver\Mysql\Schema
   */
  protected function getDefaultSchemaGrammar() {
    return $this->withTablePrefix(new Schema);
  }

  /**
   * Get the default post processor instance.
   *
   * @return Webim\Database\Driver\Mysql\Processor
   */
  protected function getDefaultPostProcessor() {
    return new Processor;
  }

}