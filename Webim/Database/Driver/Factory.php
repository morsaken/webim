<?php
/**
 * @author Orhan POLAT
 */

namespace Webim\Database\Driver;

use PDO;
use Webim\Database\Driver\Mysql\Connection as MysqlConnection;
use Webim\Database\Driver\Mysql\Connector as MysqlConnector;
use Webim\Database\Driver\Postgres\Connection as PostgresConnection;
use Webim\Database\Driver\Postgres\Connector as PostgresConnector;
use Webim\Database\Driver\Sqlite\Connection as SqliteConnection;
use Webim\Database\Driver\Sqlite\Connector as SqliteConnector;
use Webim\Database\Driver\SqlServer\Connection as SqlServerConnection;
use Webim\Database\Driver\SqlServer\Connector as SqlServerConnector;

class Factory {

  /**
   * Establish a PDO connection based on the configuration.
   *
   * @param array $config
   * @param string $name
   *
   * @return Webim\Database\Driver\Connection
   */
  public function make(array $config, $name = null) {
    $config = $this->parseConfig($config, $name);

    if (isset($config['read'])) {
      return $this->createReadWriteConnection($config);
    } else {
      return $this->createSingleConnection($config);
    }
  }

  /**
   * Parse and prepare the database configuration.
   *
   * @param array $config
   * @param string $name
   *
   * @return array
   */
  protected function parseConfig(array $config, $name) {
    return array_add(array_add($config, 'prefix', ''), 'name', $name);
  }

  /**
   * Create a single database connection instance.
   *
   * @param array $config
   *
   * @return Webim\Database\Driver\Connection
   */
  protected function createReadWriteConnection(array $config) {
    $connection = $this->createSingleConnection($this->getWriteConfig($config));

    return $connection->setReadPdo($this->createReadPdo($config));
  }

  /**
   * Create a single database connection instance.
   *
   * @param array $config
   *
   * @return Webim\Database\Driver\Connection
   */
  protected function createSingleConnection(array $config) {
    $pdo = $this->createConnector($config)->connect($config);

    return $this->createConnection(array_get($config, 'driver'),
      $pdo,
      array_get($config, 'database'),
      array_get($config, 'prefix'),
      $config
    );
  }

  /**
   * Create a connector instance based on the configuration.
   *
   * @param array $config
   *
   * @return Webim\Database\Driver\Connector
   *
   * @throws \InvalidArgumentException
   */
  public function createConnector(array $config) {
    if (!isset($config['driver'])) {
      throw new \InvalidArgumentException("A driver must be specified.");
    }

    switch ($config['driver']) {
      case 'mysql':
        return new MysqlConnector;

      case 'pgsql':
        return new PostgresConnector;

      case 'sqlite':
        return new SqliteConnector;

      case 'sqlsrv':
        return new SqlserverConnector;
    }

    throw new \InvalidArgumentException("Unsupported driver [{$config['driver']}]");
  }

  /**
   * Create a new connection instance.
   *
   * @param string $driver
   * @param \PDO $connection
   * @param string $database
   * @param string $prefix
   * @param array $config
   *
   * @return Webim\Database\Driver\Connection
   *
   * @throws \InvalidArgumentException
   */
  protected function createConnection($driver, PDO $connection, $database, $prefix = '', array $config = array()) {
    switch ($driver) {
      case 'mysql':
        return new MysqlConnection($connection, $database, $prefix, $config);

      case 'pgsql':
        return new PostgresConnection($connection, $database, $prefix, $config);

      case 'sqlite':
        return new SqliteConnection($connection, $database, $prefix, $config);

      case 'sqlsrv':
        return new SqlserverConnection($connection, $database, $prefix, $config);
    }

    throw new \InvalidArgumentException("Unsupported driver [$driver]");
  }

  /**
   * Get the read configuration for a read / write connection.
   *
   * @param array $config
   *
   * @return array
   */
  protected function getWriteConfig(array $config) {
    $writeConfig = $this->getReadWriteConfig($config, 'write');

    return $this->mergeReadWriteConfig($config, $writeConfig);
  }

  /**
   * Get a read / write level configuration.
   *
   * @param array $config
   * @param string $type
   *
   * @return array
   */
  protected function getReadWriteConfig(array $config, $type) {
    if (isset($config[$type][0])) {
      return $config[$type][array_rand($config[$type])];
    } else {
      return $config[$type];
    }
  }

  /**
   * Merge a configuration for a read / write connection.
   *
   * @param array $config
   * @param array $merge
   *
   * @return array
   */
  protected function mergeReadWriteConfig(array $config, array $merge) {
    return array_except(array_merge($config, $merge), array('read', 'write'));
  }

  /**
   * Create a new PDO instance for reading.
   *
   * @param array $config
   *
   * @return \PDO
   */
  protected function createReadPdo(array $config) {
    $readConfig = $this->getReadConfig($config);

    return $this->createConnector($readConfig)->connect($readConfig);
  }

  /**
   * Get the read configuration for a read / write connection.
   *
   * @param array $config
   *
   * @return array
   */
  protected function getReadConfig(array $config) {
    $readConfig = $this->getReadWriteConfig($config, 'read');

    return $this->mergeReadWriteConfig($config, $readConfig);
  }

}