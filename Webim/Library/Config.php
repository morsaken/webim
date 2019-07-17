<?php
/**
 * @author Orhan POLAT
 */

namespace Webim\Library;

class Config {

  /**
   * Config vars
   *
   * @var Collection
   */
  protected static $vars;

  /**
   * Instance
   *
   * @var Config
   */
  protected static $instance;

  /**
   * Constructor
   *
   * @param array $settings
   */
  public function __construct(array $settings = array()) {
    if (!static::$instance) {
      static::$vars = Collection::make($settings);
      static::$instance = $this;
    }
  }

  /**
   * Init class
   *
   * @param array $settings
   *
   * @return Config
   */
  public static function init($settings = array()) {
    return (static::$instance ? static::$instance : new static($settings));
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
      static::$instance = new static();
    }

    return call_user_func_array(array(static::$instance, $method), $args);
  }

  /**
   * Load configuration files
   *
   * @param array $settings
   *
   * @return $this
   */
  public function load($settings) {
    $settings = is_array($settings) ? $settings : func_get_args();

    //Get configuration files
    foreach ($settings as $key => $value) {
      if ($value instanceof File) {
        $key = $value->name;
        $value = $value->load();
      }

      //Set
      static::$vars->put($key, $value);
    }

    return $this;
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
    if (!method_exists(static::$vars, $method)) {
      $className = get_class($this);

      throw new \BadMethodCallException("Call to undefined method {$className}::{$method}()");
    }

    return call_user_func_array(array(static::$vars, $method), $args);
  }

}