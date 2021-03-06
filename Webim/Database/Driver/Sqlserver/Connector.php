<?php
/**
 * @author Orhan POLAT
 */

namespace Webim\Database\Driver\Sqlserver;

use PDO;
use Webim\Database\Driver\Connector as BaseConnector;

class Connector extends BaseConnector {

  /**
   * The PDO connection options.
   *
   * @var array
   */
  protected $options = array(
    PDO::ATTR_CASE => PDO::CASE_NATURAL,
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_ORACLE_NULLS => PDO::NULL_NATURAL,
    PDO::ATTR_STRINGIFY_FETCHES => false,
  );

  /**
   * Establish a database connection.
   *
   * @param  array $config
   *
   * @return PDO
   */
  public function connect(array $config) {
    $options = $this->getOptions($config);

    return $this->createConnection($this->getDsn($config), $config, $options);
  }

  /**
   * Create a DSN string from a configuration.
   *
   * @param  array $config
   *
   * @return string
   */
  protected function getDsn(array $config) {
    $host = array_get($config, 'host');

    // First we will create the basic DSN setup as well as the port if it is in
    // in the configuration options. This will give us the basic DSN we will
    // need to establish the PDO connections and return them back for use.
    $port = array_get($config, 'port') ? ',' . array_get($config, 'port') : '';

    $database = array_get($config, 'database');

    if (in_array('dblib', $this->getAvailableDrivers())) {
      return 'dblib:host=' . $host . $port . ';dbname=' . $database;
    } else {
      $dbName = $database != '' ? ';Database=' . $database : '';

      return 'sqlsrv:Server=' . $host . $port . $dbName;
    }
  }

  /**
   * Get the available PDO drivers.
   *
   * @return array
   */
  protected function getAvailableDrivers() {
    return PDO::getAvailableDrivers();
  }

}