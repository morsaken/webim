<?php
/**
 * @author Orhan POLAT
 */

namespace Webim\Library;

use Webim\Database\Manager as DB;
use Webim\Database\Query\Builder;

abstract class Controller {

  /**
   * Columns to list
   *
   * @var array
   */
  protected $columns = array();

  /**
   * Returning list
   *
   * @var \stdClass
   */
  protected $list;

  /**
   * Saving data
   *
   * @var array
   */
  protected $data;

  /**
   * Query
   *
   * @var Webim\Database\Query\Builder
   */
  protected $query;

  /**
   * Saving validation rules and messages
   *
   * @var \stdClass
   */
  protected $validation;

  /**
   * Get called [with] functions for prevent duplicate
   *
   * @var array
   */
  protected $called = array();

  /**
   * Constructor
   *
   * @param Webim\Database\Query\Builder $table
   */
  public function __construct(Builder $table) {
    //Create list object
    $list = new \stdClass();
    $list->total = 0;
    $list->offset = 0;
    $list->limit = 20;
    $list->rows = array();
    $list->orders = array();

    //Set list and data
    $this->list = $list;
    $this->data = array();

    //Set validation rules and messages
    $this->validation = new \stdClass();
    $this->validation->rules = array();
    $this->validation->messages = array();

    //Start query
    $this->table($table);
  }

  /**
   * Query table
   *
   * @param Webim\Database\Query\Builder $table
   *
   * @return $this
   */
  protected function table(Builder $table) {
    if (!($table instanceof Builder)) {
      throw new \BadMethodCallException('Table must be instance of database manager!');
    }

    $this->query = $table;

    return $this;
  }

  /**
   * Load list
   *
   * @param null|int $offset
   * @param null|int $limit
   *
   * @return $this
   */
  public function load($offset = null, $limit = null) {
    //Set offset and limit null as default
    $this->list->offset = null;
    $this->list->limit = null;

    //Set row container
    $this->list->rows = array();

    if ((int)$limit > 0) {
      //Total
      $this->list->total = $this->count();

      //Calculate
      $calc = Paging::calc($offset, $limit, $this->list->total);

      //Set offset and limit
      $offset = $calc->offset;
      $limit = $calc->limit;

      //Offset and limit
      $this->query->offset($offset)->limit($limit);

      //Change offset and limit
      $this->list->offset = $offset;
      $this->list->limit = $limit;
    }

    //Set orders
    foreach ($this->list->orders as $order) {
      $this->query->orderBy($order->by, $order->dir);
    }

    $query = $this->query->get($this->columns);

    foreach ($query as $row) {
      $this->list->rows[] = (object)$row;
    }

    if (!$limit || !$this->list->total) {
      $this->list->total = count($this->list->rows);
    }

    return $this;
  }

  /**
   * Get count
   *
   * @return int
   */
  public function count() {
    $total = clone $this->query;

    return (int)$total->count();
  }

  /**
   * Select columns
   *
   * @param mixed $column
   *
   * @return $this
   */
  public function column($column) {
    $column = is_array($column) ? $column : func_get_args();

    $this->columns = array_merge((array)$this->columns, $column);

    return $this;
  }

  /**
   * List ordering
   *
   * @param string $column
   * @param string $direction
   * @param null|string $name
   *
   * @return $this
   */
  public function orderBy($column, $direction = 'asc', $name = null) {
    //Order info
    $order = new \stdClass();
    $order->name = strlen($name) ? $name : uniqid();
    $order->by = $column;
    $order->dir = $direction;

    //Set to returning list
    $this->list->orders[] = $order;

    return $this;
  }

  /**
   * Returning list with options
   *
   * @param string $with
   * @param array $params
   *
   * @return $this
   */
  public function with($with, $params = array()) {
    if (count($this->ids())) {
      if ($with instanceof \Closure) {
        call_user_func(\Closure::bind($with, $this), $this->list->rows, $params);
      } elseif (method_exists($this, $with)) {
        call_user_func(array($this, $with), $this->list->rows, $params);
      }
    }

    return $this;
  }

  /**
   * Loaded list ids
   *
   * @param string $key
   *
   * @return array
   */
  public function ids($key = 'id') {
    $ids = array();

    foreach ($this->list->rows as $row) {
      if (object_get($row, $key)) {
        $ids[$row->$key] = $row->$key;
      }
    }

    return $ids;
  }

  /**
   * Checks incoming data is unique
   *
   * @param mixed $columns
   *
   * @return bool
   */
  public function unique($columns) {
    if (!is_array($columns)) {
      $columns = func_get_args();
    }

    //Clone created query
    $query = clone $this->query;

    //Except current id
    $query->where('id', '<>', array_get($this->data, 'id', 0));

    foreach ($columns as $column) {
      $query->where($column, array_get($this->data, $column));
    }

    return ($query->count() == 0);
  }

  /**
   * Save function
   *
   * @return array
   *
   * @throws \Exception
   */
  public function save() {
    //Validate
    $validator = Validator::make($this->data, $this->validation->rules, $this->validation->messages);

    if ($validator->valid()) {
      //Default id
      $id = array_get($this->data, 'id', 0);

      //Version check
      $versionCheck = true;

      if (($id > 0) && isset($this->data['version'])) {
        //Version
        $version = $this->data['version'];

        //Check version
        $query = clone $this->query;

        if (!$query->where('id', $id)->where('version', $version)->count()) {
          $versionCheck = false;
        } else {
          //Update to new version
          $this->data['version'] = ++$version;
        }
      }

      if ($versionCheck) {
        //Start transaction
        DB::beginTransaction();

        try {
          if ($id > 0) {
            if (count($this->data) > 1) {
              array_forget($this->data, 'id');
            }

            //Update
            $this->query->where('id', $id)->update($this->data);
          } else {
            //Insert
            $save = $this->query->insertGetId($this->data);

            if ($save->success) {
              $id = $save->id;
            }
          }

          foreach (func_get_args() as $callable) {
            if (($callable instanceof \Closure) && ($id > 0)) {
              call_user_func(\Closure::bind($callable, $this), $id);
            }
          }

          //Commit
          DB::commit();

          //Return
          $return = array(
            'id' => $id
          );

          if (isset($version)) {
            $return['version'] = $version;
          }

          return $return;
        } catch (\Exception $e) {
          //Rollback
          DB::rollBack();

          throw new \Exception($e->getMessage());
        }
      }

      throw new \Exception('Version mismatch!');
    }

    throw new \Exception($validator->errors('0.message'));
  }

  /**
   * Validation rules and message setter
   *
   * @param array $rules
   * @param array $messages
   *
   * @return $this
   */
  public function validation($rules, $messages) {
    $this->validation->rules = array_merge_recursive($this->validation->rules, (array)$rules);
    $this->validation->messages = array_merge_recursive($this->validation->messages, (array)$messages);

    return $this;
  }

  /**
   * Delete
   *
   * @param null|mixed $id
   *
   * @return int
   *
   * @throws \Exception
   */
  public function delete($id = null) {
    //Start transaction
    DB::beginTransaction();

    try {
      //Total deleted
      $total = 0;

      switch (true) {
        case ($id instanceof \Closure):

          $total = call_user_func($id);

          break;
        case is_array($id):

          $delete = $this->query->whereIn('id', $id)->delete();
          $total = $delete->return;

          break;
        case ($id > 0):

          $delete = $this->query->delete($id);
          $total = $delete->return;

          break;
        default:

          $delete = $this->query->delete();
          $total = $delete->return;
      }

      //Commit
      DB::commit();

      return $total;
    } catch (\Exception $e) {
      //Rollback
      DB::rollBack();

      throw new \Exception($e->getMessage());
    }
  }

  /**
   * Simple list
   *
   * @param mixed $column
   * @param string $key
   *
   * @return array
   */
  public function getList($column = 'title', $key = 'id') {
    $list = array();

    foreach ($this->list->rows as $row) {
      $id = array_get($row, $key);
      $value = $row;

      if ($column instanceof \Closure) {
        $value = call_user_func($value, $row);
      } elseif (is_scalar($column)) {
        $value = array_get($row, $column);
      }

      if (!is_null($id)) {
        $list[$id] = $value;
      } else {
        $list[] = $value;
      }
    }

    return $list;
  }

  /**
   * Magic get
   *
   * @param string $key
   *
   * @return mixed|\stdClass
   */
  public function __get($key) {
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
   * Get returning list
   *
   * @param null|string $key
   * @param null|mixed $default
   *
   * @return mixed|\stdClass
   */
  public function get($key = null, $default = null) {
    if (!is_null($key)) {
      return object_get($this->list, $key, $default);
    }

    return $this->list;
  }

  /**
   * Set query column values
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
   * Magic clone
   */
  public function __clone() {
    //Clone query object
    $this->query = clone $this->query;
  }

  /**
   * Magic call
   *
   * @param string $method
   * @param array $args
   *
   * @return mixed
   */
  public function __call($method, $args) {
    if (!method_exists($this->query, $method)) {
      $className = get_class($this);

      throw new \BadMethodCallException("Call to undefined method {$className}::{$method}()");
    }

    call_user_func_array(array($this->query, $method), $args);

    return $this;
  }

}