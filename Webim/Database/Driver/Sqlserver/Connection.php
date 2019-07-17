<?php
/**
 * @author Orhan POLAT
 */

namespace Webim\Database\Driver\Sqlserver;

use Closure;
use Webim\Database\Driver\Connection as BaseConnection;

class Connection extends BaseConnection {

  /**
   * Execute a Closure within a transaction.
   *
   * @param  \Closure $callback
   *
   * @return mixed
   *
   * @throws \Exception
   */
  public function transaction(Closure $callback) {
    if ($this->getDriverName() == 'sqlsrv') {
      return parent::transaction($callback);
    }

    $this->pdo->exec('BEGIN TRAN');

    // We'll simply execute the given callback within a try / catch block
    // and if we catch any exception we can rollback the transaction
    // so that none of the changes are persisted to the database.
    try {
      $result = $callback($this);

      $this->pdo->exec('COMMIT TRAN');
    }

      // If we catch an exception, we will roll back so nothing gets messed
      // up in the database. Then we'll re-throw the exception so it can
      // be handled how the developer sees fit for their applications.
    catch (\Exception $e) {
      $this->pdo->exec('ROLLBACK TRAN');

      throw $e;
    }

    return $result;
  }

  /**
   * Get the default query grammar instance.
   *
   * @return Webim\Database\Driver\Sqlserver\Query
   */
  protected function getDefaultQueryGrammar() {
    return $this->withTablePrefix(new Query);
  }

  /**
   * Get the default schema grammar instance.
   *
   * @return Webim\Database\Driver\Sqlserver\Schema
   */
  protected function getDefaultSchemaGrammar() {
    return $this->withTablePrefix(new Schema);
  }

  /**
   * Get the default post processor instance.
   *
   * @return Webim\Database\Driver\Sqlserver\Processor
   */
  protected function getDefaultPostProcessor() {
    return new Processor;
  }

}