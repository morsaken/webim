<?php
/**
 * @author Orhan POLAT
 */

namespace Webim\Http;

class Session {

  /**
   * Session vars
   *
   * @var array
   */
  protected $vars;

  /**
   * Constructor
   */
  public function __construct() {
    @session_name('Webim');

    //Start session
    if (!session_id()) {
      //Session started
      session_start();
    }

    //Equalize session variables
    $this->vars =& $_SESSION['SYSTEM'];
  }

  /**
   * Init or get current
   *
   * @return Session
   */
  public static function current() {
    return new self();
  }

  /**
   * Set value
   *
   * @param string $key
   * @param string $value
   *
   * @return $this
   */
  public function set($key, $value) {
    array_set($this->vars, $key, $value);

    return $this;
  }

  /**
   * Delete session variable
   *
   * @param string $key
   *
   * @return $this
   */
  public function delete($key) {
    array_forget($this->vars, $key);

    return $this;
  }

  /**
   * Magic get
   *
   * @param string $key
   *
   * @return mixed
   */
  public function __get($key) {
    return $this->get($key);
  }

  /**
   * Get value
   *
   * @param string $key
   * @param null|string $default
   *
   * @return string
   */
  public function get($key, $default = null) {
    return array_get($this->vars, $key, $default);
  }

  /**
   * Magic isset
   *
   * @param string $key
   *
   * @return bool
   */
  public function __isset($key) {
    return (array_get($this->vars, $key) !== null);
  }

  /**
   * Magic unset
   *
   * @param string $key
   */
  public function __unset($key) {
    array_forget($this->vars, $key);
  }

}