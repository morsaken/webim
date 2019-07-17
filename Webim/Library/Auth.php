<?php
/**
 * @author Orhan POLAT
 */

namespace Webim\Library;

class Auth {

  /**
   * Instance
   *
   * @var Auth
   */
  protected static $instance;

  /**
   * Auth data
   *
   * @var array
   */
  protected $data = array();

  /**
   * Default settings
   *
   * @var array
   */
  protected $defaults = array(
    'id' => null,
    'name' => 'guest',
    'full_name' => 'Oturum Açmamış Kullanıcı',
    'email' => 'guest@',
    'role' => 'guest',
    'groups' => array()
  );

  /**
   * Constructor
   */
  public function __construct() {
    if (!static::$instance) {
      if (session_id()) {
        $this->data =& $_SESSION['AUTH'];
      }

      //Set defaults
      if (is_null($this->get('id'))) {
        $this->data = $this->defaults;
      }

      static::$instance = $this;
    }
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
    return array_get($this->data, $key, $default);
  }

  /**
   * Current instance
   *
   * @return $this
   */
  public static function current() {
    return (static::$instance ? static::$instance : new static());
  }

  /**
   * Checks the user is logged in
   *
   * @return bool
   */
  public function isLoggedIn() {
    return ('guest' !== $this->get('role'));
  }

  /**
   * Checks the user is admin
   *
   * @return bool
   */
  public function isAdmin() {
    return ('admin' === $this->get('role'));
  }

  /**
   * Logout
   */
  public function logout() {
    //Remove all info
    foreach (array_keys($this->data) as $key) {
      unset($this->data[$key]);
    }

    @session_destroy();
    @session_regenerate_id(true);

    //Set to defaults
    $this->data = $this->defaults;
  }

  /**
   * Delete value
   *
   * @param mixed $key
   *
   * @return $this
   */
  public function delete($key) {
    array_forget($this->data, $key);

    return $this;
  }

  /**
   * Magic get
   *
   * @param null|string $key
   *
   * @return mixed
   */
  public function __get($key = null) {
    return $this->get($key);
  }

  /**
   * Magic set
   *
   * @param string $key
   * @param mixed $value
   */
  public function __set($key, $value) {
    $this->set($key, $value);
  }

  /**
   * Set value
   *
   * @param mixed $key
   * @param null|mixed $value
   *
   * @return $this
   */
  public function set($key, $value = null) {
    if (is_array($key)) {
      $this->data = array_merge_distinct($this->data, $key);
    } else {
      array_set($this->data, $key, $value);
    }

    return $this;
  }

  /**
   * Magic isset
   *
   * @param string $key
   *
   * @return bool
   */
  public function __isset($key) {
    return (array_get($this->data, $key) !== null);
  }

  /**
   * Magic unset
   *
   * @param string $key
   */
  public function __unset($key) {
    array_forget($this->data, $key);
  }

}