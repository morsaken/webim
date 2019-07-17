<?php
/**
 * @author Orhan POLAT
 */

namespace Webim\Database;

use Webim\Database\Driver\Connection;
use Webim\Database\Driver\Factory;

class Manager {

  /**
   * Configuration
   *
   * @var array
   */
  protected static $config = array();

  /**
   * Instance
   *
   * @var Manager
   */
  protected static $instance;

  /**
   * The active connection instances.
   *
   * @var array
   */
  protected $connections = array();

  /**
   * Has connection
   *
   * @return bool
   */
  public static function hasConnection() {
    if (!static::$instance) {
      static::$instance = new static;
    }

    return count(static::$instance->getConnections()) > 0;
  }

  /**
   * Return all of the created connections.
   *
   * @return array
   */
  protected function getConnections() {
    return $this->connections;
  }

  /**
   * Set the configuration
   *
   * @param array $config
   */
  public static function setConfig($config = array()) {
    static::$config = $config;
  }

  /**
   * Call statically class
   *
   * @param string $method
   * @param array $args
   *
   * @return mixed
   */
  public static function __callStatic($method, $args) {
    if (!static::$instance) {
      static::$instance = new static;
    }

    /* calling method must be protected  */

    return call_user_func_array(array(static::$instance, $method), $args);
  }

  /**
   * Dynamically pass methods to the default connection.
   *
   * @param string $method
   * @param array $args
   *
   * @return mixed
   *
   * @throws \BadMethodCallException
   */
  public function __call($method, $args) {
    if (!method_exists($this->connection(), $method)) {
      $className = get_class($this);

      throw new \BadMethodCallException("Call to undefined method {$className}::{$method}()");
    }

    return call_user_func_array(array($this->connection(), $method), $args);
  }

  /**
   * Disconnect from the given database and remove from local cache.
   *
   * @param string $name
   *
   * @return void
   */
  protected function purge($name = null) {
    $this->disconnect($name);

    unset($this->connections[$name]);
  }

  /**
   * Disconnect from the given database.
   *
   * @param string $name
   *
   * @return void
   */
  protected function disconnect($name = null) {
    if (isset($this->connections[$name = $name ?: $this->getDefaultConnection()])) {
      $this->connections[$name]->disconnect();
    }
  }

  /**
   * Get the default connection name.
   *
   * @return string
   */
  protected function getDefaultConnection() {
    return array_get(static::$config, 'default', 'default');
  }

  /**
   * Reconnect to the given database.
   *
   * @param string $name
   *
   * @return Webim\Database\Driver\Connection
   */
  protected function reconnect($name = null) {
    $this->disconnect($name = $name ?: $this->getDefaultConnection());

    if (!isset($this->connections[$name])) {
      return $this->connection($name);
    } else {
      return $this->refreshPdoConnections($name);
    }
  }

  /**
   * Get a database connection instance.
   *
   * @param string $name
   *
   * @return Webim\Database\Driver\Connection
   */
  protected function connection($name = null) {
    $name = $name ?: $this->getDefaultConnection();

    // If we haven't created this connection, we'll create it based on the config
    // provided in the application. Once we've created the connections we will
    // set the "fetch mode" for PDO which determines the query return types.
    if (!isset($this->connections[$name])) {
      $connection = $this->makeConnection($name);

      $this->connections[$name] = $this->prepare($connection);
    }

    return $this->connections[$name];
  }

  /**
   * Make the database connection instance.
   *
   * @param string $name
   *
   * @return Webim\Database\Driver\Connection
   */
  protected function makeConnection($name) {
    $config = $this->getConfig($name);

    return with(new Factory)->make($config, $name);
  }

  /**
   * Get the configuration for a connection.
   *
   * @param string $name
   *
   * @return array
   */
  protected function getConfig($name) {
    $name = $name ?: $this->getDefaultConnection();

    return array_get(static::$config, 'connections.' . $name, array());
  }

  /**
   * Prepare the database connection instance.
   *
   * @param Webim\Database\Driver\Connection $connection
   *
   * @return Webim\Database\Driver\Connection
   */
  protected function prepare(Connection $connection) {
    if (array_get(static::$config, 'fetch')) {
      $connection->setFetchMode(array_get(static::$config, 'fetch'));
    }

    // Here we'll set a reconnector callback. This reconnector can be any callable
    // so we will set a Closure to reconnect from this manager with the name of
    // the connection, which will allow us to reconnect from the connections.
    $connection->setReconnector(function ($connection) {
      $this->reconnect($connection->getName());
    });

    return $connection;
  }

  /**
   * Refresh the PDO connections on a given connection.
   *
   * @param string $name
   *
   * @return Webim\Database\Driver\Connection
   */
  protected function refreshPdoConnections($name) {
    $fresh = $this->makeConnection($name);

    return $this->connections[$name]
      ->setPdo($fresh->getPdo())
      ->setReadPdo($fresh->getReadPdo());
  }

}