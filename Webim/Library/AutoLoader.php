<?php
/**
 * @author Orhan POLAT
 */

namespace Webim\Library;

class AutoLoader {
  /**
   * Include dirs
   *
   * @var array
   */
  static $dirs = array();

  /**
   * Loader
   *
   * @param string $className
   *
   * @return bool
   */
  public static function load($className) {
    //Class name
    $className = str_replace('\\', DIRECTORY_SEPARATOR, trim($className, '\\')) . '.php';

    foreach (static::$dirs as $dir) {
      $file = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $className;

      if (file_exists($file) && is_file($file)) {
        // Include file
        require_once($file);

        return true;
      }
    }

    return false;
  }

  /**
   * Register
   */
  public static function register() {
    foreach (func_get_args() as $dir) {
      if (is_array($dir)) {
        static::$dirs = array_merge(static::$dirs, $dir);
      } else {
        static::$dirs[] = $dir;
      }
    }

    spl_autoload_register(__NAMESPACE__ . '\\AutoLoader::load');
  }

}