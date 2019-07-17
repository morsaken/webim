<?php
/**
 * @author Orhan POLAT
 */

namespace Webim\Library;

class Message {

  /**
   * Message container
   *
   * @var array
   */
  protected $result;

  /**
   * Constructor
   *
   * @param string $text
   * @param boolean $success
   * @param array $return
   */
  public function __construct($text, $success = false, $return = array()) {
    //Result
    $this->result = array(
      'success' => $success,
      'text' => $text,
      'return' => $return
    );
  }

  /**
   * Setting directly message content
   *
   * @param string $text
   * @param bool $success
   * @param array $return
   * @param bool $alsoLog
   *
   * @return $this
   */
  public static function result($text, $success = false, $return = array(), $alsoLog = false) {
    //Init
    $message = new static($text, $success, $return);

    if ($alsoLog) {
      static::log($message);
    }

    return $message;
  }

  /**
   * Log message
   *
   * @param Webim\Library\Message $message
   */
  public static function log(Message $message) {
    $type = $message->success() ? 'info' : 'error';

    Log::$type($message->text());
  }

  /**
   * Result success info
   *
   * @return bool|null
   */
  public function success() {
    return array_get($this->result, 'success');
  }

  /**
   * Result text
   *
   * @return string|null
   */
  public function text() {
    return array_get($this->result, 'text');
  }

  /**
   * Write message content
   *
   * @param string $contentType
   * @param bool $show
   *
   * @return string
   */
  public function forData($contentType = 'json', $show = false) {
    return array_to($this->result, $contentType, $show);
  }

  /**
   * Message bag
   *
   * @return \stdClass
   */
  public function get() {
    $message = new \stdClass();
    $message->success = $this->success();
    $message->text = $this->text();
    $message->return = $this->returns();

    return $message;
  }

  /**
   * Return
   *
   * @param null|string $key
   *
   * @return mixed
   */
  public function returns($key = null) {
    return array_get($this->result, 'return' . (!is_null($key) ? '.' . $key : ''));
  }

  /**
   * Magic get
   *
   * @param string $key
   *
   * @return mixed
   */
  public function __get($key) {
    return array_get($this->result, $key);
  }

  /**
   * Magic set
   *
   * @param string $key
   * @param mixed $value
   */
  public function __set($key, $value) {
    if (!is_null(array_get($this->result, $key))) {
      array_set($this->result, $key, $value);
    }
  }

  /**
   * Return message result
   *
   * @return string
   */
  public function __toString() {
    return $this->text();
  }

}