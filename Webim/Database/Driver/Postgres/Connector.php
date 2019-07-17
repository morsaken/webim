<?php
/**
 * @author Orhan POLAT
 */

namespace Webim\Database\Driver\Postgres;

use PDO;
use Webim\Database\Driver\Connector as BaseConnector;

class Connector extends BaseConnector {

  /**
   * The default PDO connection options.
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
   * @param array $config
   *
   * @return PDO
   */
  public function connect(array $config) {
    // First we'll create the basic DSN and connection instance connecting to the
    // using the configuration option specified by the developer. We will also
    // set the default character set on the connections to UTF-8 by default.
    $dsn = $this->getDsn($config);

    $options = $this->getOptions($config);

    $connection = $this->createConnection($dsn, $config, $options);

    $charset = array_get($config, 'charset');

    $connection->prepare("SET NAMES '$charset'")->execute();

    // Unlike MySQL, Postgres allows the concept of "schema" and a default schema
    // may have been specified on the connections. If that is the case we will
    // set the default schema search paths to the specified database schema.
    if (isset($config['schema'])) {
      $schema = $config['schema'];

      $connection->prepare("SET search_path TO {$schema}")->execute();
    }

    return $connection;
  }

  /**
   * Create a DSN string from a configuration.
   *
   * @param array $config
   *
   * @return string
   */
  protected function getDsn(array $config) {
    return 'pgsql:'
      . (isset($config['host']) ? 'host=' . array_get($config, 'host') . ';' : '')
      . (isset($config['port']) ? 'port=' . array_get($config, 'port') . ';' : '')
      . 'dbname=' . array_get($config, 'database');
  }

}