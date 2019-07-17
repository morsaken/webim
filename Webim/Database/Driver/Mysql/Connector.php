<?php
/**
 * @author Orhan POLAT
 */

namespace Webim\Database\Driver\Mysql;

use Webim\Database\Driver\Connector as BaseConnector;

class Connector extends BaseConnector {

  /**
   * Establish a database connection.
   *
   * @param array $config
   *
   * @return \PDO
   */
  public function connect(array $config) {
    $dsn = $this->getDsn($config);

    $options = $this->getOptions($config);

    // We need to grab the PDO options that should be used while making the brand
    // new connection instance. The PDO options control various aspects of the
    // connection's behavior, and some might be specified by the developers.
    $connection = $this->createConnection($dsn, $config, $options);

    if (isset($config['unix_socket'])) {
      $connection->exec('USE ' . array_get($config, 'database') . ';');
    }

    $collation = array_get($config, 'collation');

    // Next we will set the "names" and "collation" on the clients connections so
    // a correct character set will be used by this client. The collation also
    // is set on the server but needs to be set here on this client objects.
    $charset = array_get($config, 'charset');

    $names = "SET NAMES '$charset'" . (!is_null($collation) ? " COLLATE '$collation'" : '');

    $connection->prepare($names)->execute();

    // If the "strict" option has been configured for the connection we'll enable
    // strict mode on all of these tables. This enforces some extra rules when
    // using the MySQL database system and is a quicker way to enforce them.
    if (isset($config['strict']) && $config['strict']) {
      $connection->prepare("SET SESSION sql_mode='STRICT_ALL_TABLES'")->execute();
    }

    return $connection;
  }

  /**
   * Create a DSN string from a configuration. Chooses socket or host/port based on
   * the 'unix_socket' config value
   *
   * @param array $config
   *
   * @return string
   */
  protected function getDsn(array $config) {
    return $this->configHasSocket($config) ? $this->getSocketDsn($config) : $this->getHostDsn($config);
  }

  /**
   * Determine if the given configuration array has a UNIX socket value.
   *
   * @param array $config
   *
   * @return bool
   */
  protected function configHasSocket(array $config) {
    return isset($config['unix_socket']) && !empty($config['unix_socket']);
  }

  /**
   * Get the DSN string for a socket configuration.
   *
   * @param array $config
   *
   * @return string
   */
  protected function getSocketDsn(array $config) {
    return 'mysql:unix_socket=' . array_get($config, 'unix_socket') . ';'
      . 'dbname=' . array_get($config, 'database');
  }

  /**
   * Get the DSN string for a host / port configuration.
   *
   * @param array $config
   *
   * @return string
   */
  protected function getHostDsn(array $config) {
    return 'mysql:host=' . array_get($config, 'host') . ';'
      . (isset($config['port']) ? 'port=' . array_get($config, 'port') . ';' : '')
      . 'dbname=' . array_get($config, 'database');
  }

}