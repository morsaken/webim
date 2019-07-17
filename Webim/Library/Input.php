<?php
/**
 * @author Orhan POLAT
 */

namespace Webim\Library;

use Webim\Http\Request;

class Input {

  /**
   * PUT array
   *
   * @var array
   */
  private static $phpInputs;
  /**
   * Name
   *
   * @var string
   */
  protected $name;
  /**
   * Default value
   *
   * @var mixed
   */
  protected $default;
  /**
   * Options
   *
   * @var array
   */
  protected $options = array(
    'removeTags' => false,
    'htmlTags' => false,
    'trimSpaces' => true
  );
  /**
   * Request
   *
   * @var Webim\Http\Request
   */
  protected $request;

  /**
   * Constructor
   *
   * @param string $name
   * @param string $default
   */
  public function __construct($name, $default = '') {
    $this->name = $name;
    $this->default = $default;
    $this->request = Request::current();
  }

  /**
   * Class constructor by static method
   *
   * @param string $name
   * @param string $default
   *
   * @return Input
   */
  public static function name($name, $default = '') {
    return new static($name, $default);
  }

  /**
   * All input
   *
   * @return array
   */
  public static function all() {
    return Request::current()->all();
  }

  /**
   * Selected input keys
   *
   * @param mixed $keys
   *
   * @return array
   */
  public static function only($keys) {
    $keys = is_array($keys) ? $keys : func_get_args();

    return Request::current()->only($keys);
  }

  /**
   * Excepted inputs
   *
   * @param mixed $keys
   *
   * @return array
   */
  public static function except($keys) {
    $keys = is_array($keys) ? $keys : func_get_args();

    return Request::current()->except($keys);
  }

  /**
   * Set option
   *
   * @param string $key
   * @param string $value
   *
   * @return Input
   */
  public function opt($key, $value) {
    //Set option
    if (isset($this->options[$key]) && (gettype($this->options[$key]) === gettype($value))) {
      $this->options[$key] = $value;
    }

    return $this;
  }

  /**
   * Write value
   */
  public function write() {
    echo $this->val();
  }

  /**
   * Return value
   *
   * @return mixed
   */
  public function val() {
    return $this->value();
  }

  /**
   * Value
   *
   * @return mixed
   */
  protected function value() {
    $value = $this->input($this->name);

    $is_array = is_array($value);

    if (!$is_array) {
      $value = array($value);
    }

    //Clear from unnecessary chars
    $value = $this->clearString($value);

    if (is_array($this->default)) {
     //Zero shit
      $this->default = array_map(function ($val) {
        if ($val === 0) {
          return '0';
        }

        return $val;
      }, $this->default);

      array_walk($value, function (&$val, $key) {
        if (is_null($val) || !in_array($val, $this->default)) {
          $val = array_get($this->default, $key, array_first($this->default));
        }
      });
    } else {

      $value = array_map(function ($val) {
        return $this->setDefault($val);
      }, $value);
    }

    //Clear from unnecessary chars again for default values
    $value = $this->clearString($value);

    return $is_array ? $value : current($value);
  }

  /**
   * Get target input
   *
   * @param string $name
   *
   * @return mixed|string
   */
  protected function input($name) {
    if (!in_array($this->request->method(), array('GET', 'POST'))) {
      return $this->phpInput($name);
    }

    return $this->request->input($name);
  }

  /**
   * PHP Input elements
   *
   * @param null|string $name
   *
   * @return mixed
   */
  protected function phpInput($name = null) {
    if (!is_array(static::$phpInputs)) {
      //Return
      $inputs = array();

      foreach (explode('&', file_get_contents('php://input', 'r')) as $data) {
        $values = explode('=', $data, 2);

        if (count($values) == 2) {
          $inputs[$values[0]] = urldecode($values[1]);
        }
      }

      static::$phpInputs = $inputs;
    }

    return array_get(static::$phpInputs, $name);
  }

  /**
   * Clear tags
   *
   * @param string $value
   *
   * @return string
   */
  private function clearString($value) {
    //Get array status for return type
    $is_array = is_array($value);

    if (!$is_array) {
      $value = array($value);
    }

    if ($this->options['trimSpaces']) {
      $value = array_map(function ($val) {
        if (is_string($val)) {
          $val = trim($val);
        }

        return $val;
      }, $value);
    }

    if ($this->options['removeTags']) {
      $value = array_map(function ($val) {
        if (is_string($val)) {
          $val = strip_tags($val);
        }

        return $val;
      }, $value);
    }

    if (!$this->options['htmlTags']) {
      $value = array_map(function ($val) {
        if (is_string($val)) {
          $val = str_replace(array('<', '>'), array('&lt;', '&gt;'), $val);
        }

        return $val;
      }, $value);
    }

    return $is_array ? $value : current($value);
  }

  /**
   * Set default value
   *
   * @param mixed $value
   *
   * @return array
   */
  private function setDefault($value) {
    if (!is_scalar($value) && !strlen($value)) {
      $value = $this->default;
    }

    if (is_numeric($this->default) || is_string($this->default)) {
      //Set default type
      settype($value, gettype($this->default));
    }

    return $value;
  }

  /**
   * Return value
   *
   * @return mixed
   */
  public function __toString() {
    return $this->val();
  }

  /**
   * Magic call
   *
   * @param string $method
   * @param array $parameters
   *
   * @return Input
   */
  public function __call($method, $parameters) {
    if (isset($this->options[$method])) {
      call_user_func_array(array($this, 'opt'), array($method, $parameters[0]));
    }

    return $this;
  }

}