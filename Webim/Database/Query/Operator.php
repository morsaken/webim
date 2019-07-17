<?php
/**
 * @author Orhan POLAT
 */

namespace Webim\Database\Query;

class Operator {

  /**
   * Raw parameters
   *
   * @var array
   */
  public $params;
  /**
   * As
   *
   * @var string
   */
  public $as;
  /**
   * Columns joiner glue
   *
   * @var string
   */
  public $glue = ', ';
  /**
   * Operator result alias
   *
   * @var null|string
   */
  public $alias = null;
  /**
   * Operator name
   *
   * @var string
   */
  private $name;
  /**
   * Parametrized columns
   *
   * @var array
   */
  private $columns;

  /**
   * Construct class
   *
   * @param string $name
   * @param array $params
   * @param null|string $as
   * @param string $glue
   */
  public function __construct($name, $params = array(), $as = null, $glue = ', ') {
    $this->name = $name;

    if (!is_array($params)) {
      $params = array($params);
    }

    $this->params = $params;
    $this->glue = $glue;
    $this->as = $as;
  }

  /**
   * Set parametrized columns
   *
   * @param string $columns
   */
  public function columns($columns) {
    $this->columns = $columns;
  }

  /**
   * Magic string value
   *
   * @return string
   */
  public function __toString() {
    return $this->get();
  }

  /**
   * Get string
   *
   * @return string
   */
  public function get() {
    //Return
    $wrapped = $this->name;

    if (!is_null($this->name)) {
      $wrapped .= '(';
    }

    $wrapped .= $this->columns;

    if (!is_null($this->name)) {
      $wrapped .= ')';
    }

    if (strlen($this->alias) > 0) {
      $wrapped .= ' AS ' . $this->alias;
    }

    return trim($wrapped);
  }

}