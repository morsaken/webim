<?php
/**
 * @author Orhan POLAT
 */

namespace Webim\Library;

class Log {

  /**
   * Log an exception to the log file.
   *
   * @param \Exception $e
   *
   * @return void
   */
  public static function exception($e) {
    static::write('error', static::exceptionLine($e));
  }

  /**
   * Write a message to the log file.
   *
   * <code>
   * // Write an "error" message to the log file
   * Log::write('error', 'Something went horribly wrong!');
   *
   * // Write an "error" message using the class' magic method
   * Log::error('Something went horribly wrong!');
   * </code>
   *
   * @param string $type
   * @param string $data
   *
   * @return void
   */
  public static function write($type, $data) {
    // If there is a listener for the log event, we'll delegate the logging
    // to the event and not write to the log files. This allows for quick
    // swapping of log implementations for debugging.
    if (Event::listeners('log')) {
      Event::fire('log', array(
        $type, $data
      ));
    } else {
      File::path('logs.' . Str::text($type, array(
          'lower' => array()
        ))->slug(), @date('Y-m-d') . '.log')->create()->append(static::format($type, $data));
    }
  }

  /**
   * Format a log message for logging.
   *
   * @param string $type
   * @param string $data
   *
   * @return string
   */
  protected static function format($type, $data) {
    return @date('Y-m-d H:i:s')
      . ' [' . Str::text($type, array('upper' => array()))->slug()->upper() . '] - '
      . $data . PHP_EOL;
  }

  /**
   * Format a log friendly message from the given exception.
   *
   * @param \Exception $e
   *
   * @return string
   */
  protected static function exceptionLine($e) {
    return $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine();
  }

  /**
   * Dynamically write a log message.
   *
   * <code>
   * // Write an "error" message to the log file
   * Log::error('This is an error!');
   *
   * // Write a "warning" message to the log file
   * Log::warning('This is a warning!');
   * </code>
   *
   * @param string $method
   * @param array $args
   */
  public static function __callStatic($method, $args) {
    static::write($method, $args[0]);
  }

}