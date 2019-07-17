<?php
/**
 * @author Orhan POLAT
 */

namespace Webim\Cache;

use Webim\Cache\Driver\ApcCache;
use Webim\Cache\Driver\FilesystemCache;
use Webim\Cache\Driver\MemcacheCache;
use Webim\Cache\Driver\MemcachedCache;
use Webim\Cache\Driver\MongoDBCache;
use Webim\Cache\Driver\WincacheCache;
use Webim\Cache\Driver\XcacheCache;

class Manager {

  /**
   * Stat hits
   */
  const STATS_HITS = 'hits';

  /**
   * Stat misses
   */
  const STATS_MISSES = 'misses';

  /**
   * Stat uptime
   */
  const STATS_UPTIME = 'uptime';

  /**
   * Stat memory usage
   */
  const STATS_MEMORY_USAGE = 'memory_usage';

  /**
   * Stat memory available
   */
  const STATS_MEMORY_AVAILABLE = 'memory_available';

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
   * @var string Cache key.
   */
  protected $key;

  /**
   * @var Manager
   */
  protected $driver;

  /**
   * @var bool
   */
  protected $enabled;

  /**
   * Constructor
   */
  public function __construct() {
    $this->enabled = (bool)array_get(static::$config, 'enabled', false);

    // Cache key allows us to invalidate all cache on configuration changes.
    $this->key = array_get(static::$config, 'prefix', 'webim');

    $this->driver = $this->getCacheDriver();

    // Set the cache namespace to our unique key
    $this->driver->setNamespace($this->key);

    static::$instance = $this;
  }

  /**
   * Automatically picks the cache mechanism to use.  If you pick one manually it will use that
   * If there is no config option for $driver in the config, or it's set to 'auto', it will
   * pick the best option based on which cache extensions are installed.
   *
   * @return mixed The cache driver to use
   */
  protected function getCacheDriver() {
    //Driver
    $cacheDriver = array_get(static::$config, 'driver');

    //Available drivers
    $availableDrivers = array(
      'file' => function () {
        return new FilesystemCache(array_get(static::$config, 'dir', 'cache'));
      }
    );

    if (extension_loaded('apc')) {
      $availableDrivers['apc'] = function () {
        return new ApcCache();
      };
    }

    if (extension_loaded('wincache')) {
      $availableDrivers['wincache'] = function () {
        return new WincacheCache();
      };
    }

    if (extension_loaded('xcache')) {
      $availableDrivers['xcache'] = function () {
        return new XcacheCache();
      };
    }

    if (class_exists('Memcache')) {
      $availableDrivers['memcache'] = function () {
        $memcache = new \Memcache();
        $memcache->connect(
          array_get(static::$config, 'memcache.server', 'localhost'),
          array_get(static::$config, 'memcache.port', 11211)
        );

        $driver = new MemcacheCache();
        $driver->setMemcache($memcache);

        return $driver;
      };
    }

    if (class_exists('Memcached')) {
      $availableDrivers['memcached'] = function () {
        $memcached = new \Memcached();
        $memcached->addServer(
          array_get(static::$config, 'memcached.server', 'localhost'),
          array_get(static::$config, 'memcached.port', 11211)
        );

        $driver = new MemcachedCache();
        $driver->setMemcached($memcached);

        return $driver;
      };
    }

    if (class_exists('Mongo')) {
      $availableDrivers['mongodb'] = function () {
        $mongo = new \MongoClient(
          array_get(static::$config, 'mongodb.server', 'mongodb://localhost:27017'),
          array_get(static::$config, 'mongodb.options', array()),
          array_get(static::$config, 'mongodb.driver_options', array())
        );
        $mongo->selectDB(array_get(static::$config, 'mongodb.database', 'webim'));


        $driver = new MongoDBCache();
        $driver->setMongoCache(new \MongoCollection(new \MongoDB($mongo, 'webim'), 'webim'));

        return $driver;
      };
    }

    $driverName = 'file';

    if (!$cacheDriver || ($cacheDriver == 'auto')) {
      $driverName = array_first(array_keys($availableDrivers));
    } elseif (isset($availableDrivers[$cacheDriver])) {
      $driverName = $cacheDriver;
    }

    return $availableDrivers[$driverName]();
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
   * Call staticaly class
   *
   * @param string $method
   * @param array $args
   *
   * @return mixed
   */
  public static function __callStatic($method, $args) {
    if (!static::$instance) {
      new static();
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
    if (!method_exists($this->driver, $method)) {
      $className = get_class($this);

      throw new \BadMethodCallException("Call to undefined method {$className}::{$method}()");
    }

    return call_user_func_array(array($this->driver, $method), $args);
  }

  /**
   * Gets a cached entry if it exists based on an id. If it does not exist, it returns false
   *
   * @param string $id the id of the cached entry
   *
   * @return object  returns the cached entry, can be any type, or false if doesn't exist
   */
  protected function get($id) {
    if ($this->enabled) {
      return $this->driver->get($id);
    } else {
      return false;
    }
  }

  /**
   * Gets a cached entry existence based on an id. If it does not exist, it returns false
   *
   * @param string $id the id of the cached entry
   *
   * @return null|object  returns the cached entry, can be any type, or false if doesn't exist
   */
  protected function has($id) {
    if ($this->enabled) {
      return $this->driver->has($id);
    } else {
      return false;
    }
  }

  /**
   * Stores a new cached entry.
   *
   * @param string $id the id of the cached entry
   * @param array|object $data the data for the cached entry to store
   * @param int $lifetime the lifetime to store the entry in seconds
   *
   * @return bool
   */
  protected function save($id, $data, $lifetime = null) {
    if ($this->enabled) {
      return $this->driver->save($id, $data, $lifetime);
    } else {
      return false;
    }
  }

  /**
   * Deletes a cached entry existence based on an id. If it does not exist, it returns false
   *
   * @param string $id the id of the cached entry
   *
   * @return null|object     returns the cached entry, can be any type, or false if doesn't exist
   */
  protected function delete($id) {
    if ($this->enabled) {
      return $this->driver->delete($id);
    } else {
      return false;
    }
  }

  /**
   * Flush all
   */
  protected function flush() {
    if ($this->enabled) {
      $this->driver->flush();
    }
  }

  /**
   * Getter method to get the cache key
   */
  protected function getKey() {
    return $this->key;
  }

  /**
   * Get instance
   *
   * @return Manager
   */
  protected function getManager() {
    return static::$instance;
  }

  /**
   * Get the configuration for a connection.
   *
   * @param null|string $name
   *
   * @return mixed
   */
  protected function getConfig($name = null) {
    if (is_null($name)) {
      return static::$config;
    }

    return array_get(static::$config, $name);
  }

}