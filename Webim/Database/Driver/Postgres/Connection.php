<?php
/**
 * @author Orhan POLAT
 */

namespace Webim\Database\Driver\Postgres;

use Webim\Database\Driver\Connection as BaseConnection;

class Connection extends BaseConnection {

  /**
   * Get the default query grammar instance.
   *
   * @return Webim\Database\Driver\Postgres\Query
   */
  protected function getDefaultQueryGrammar() {
    return $this->withTablePrefix(new Query);
  }

  /**
   * Get the default schema grammar instance.
   *
   * @return Webim\Database\Driver\Postgres\Schema
   */
  protected function getDefaultSchemaGrammar() {
    return $this->withTablePrefix(new Schema);
  }

  /**
   * Get the default post processor instance.
   *
   * @return Webim\Database\Driver\Postgres\Processor
   */
  protected function getDefaultPostProcessor() {
    return new Processor;
  }

}