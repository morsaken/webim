<?php
/**
 * @author Orhan POLAT
 */

namespace Webim\Library;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use Webim\Http\Session;

class Flash implements ArrayAccess, Countable, IteratorAggregate {

  /**
   * The flash session storage key
   * @var string
   */
  protected $key;

  /**
   * The session object
   * @var Session
   */
  protected $session;

  /**
   * The flash messages
   * @var array
   */
  protected $messages;

  /**
   * Constructor
   *
   * @param Session $session
   * @param string $key The flash session storage key
   */
  public function __construct(Session $session, $key = 'flash') {
    $this->session = $session;
    $this->key = $key;
    $this->messages = array(
      'prev' => $session->get($key, array()),
      'next' => array(),
      'now' => array()
    );
  }

  /**
   * Persist flash messages from previous request to the next request
   *
   * @return $this
   */
  public function keep() {
    foreach ($this->messages['prev'] as $key => $val) {
      $this->next($key, $val);
    }

    return $this;
  }

  /**
   * Set flash message for next request
   *
   * @param string $key The flash message key
   * @param mixed $value The flash message value
   *
   * @return $this
   */
  public function next($key, $value) {
    $this->messages['next'][(string)$key] = $value;

    return $this;
  }

  /**
   * Save flash messages to session
   *
   * @return $this
   */
  public function save() {
    $this->session->set($this->key, $this->messages['next']);

    return $this;
  }

  /**
   * Check the message exists by given key
   *
   * @param string $key
   *
   * @return bool
   */
  public function has($key) {
    return !is_null($this->getMessage($key));
  }

  /**
   * Get specific message
   *
   * @param null|string $key
   *
   * @return mixed
   */
  public function getMessage($key = null) {
    if (is_null($key)) {
      $key = array_first(array_keys($this->getMessages()));
    }

    return array_get($this->getMessages(), $key);
  }

  /**
   * Return flash messages to be shown for the current request
   *
   * @return array
   */
  public function getMessages() {
    return array_merge($this->messages['prev'], $this->messages['now']);
  }

  /**
   * Offset exists
   *
   * @param mixed $offset
   *
   * @return bool
   */
  public function offsetExists($offset) {
    $messages = $this->getMessages();

    return isset($messages[$offset]);
  }

  /**
   * Offset get
   *
   * @param mixed $offset
   *
   * @return mixed|null The value at specified offset, or null
   */
  public function offsetGet($offset) {
    $messages = $this->getMessages();

    return isset($messages[$offset]) ? $messages[$offset] : null;
  }

  /**
   * Offset set
   *
   * @param mixed $offset
   * @param mixed $value
   */
  public function offsetSet($offset, $value) {
    $this->now($offset, $value);
  }

  /**
   * Set flash message for current request
   *
   * @param string $key The flash message key
   * @param mixed $value The flash message value
   *
   * @return $this
   */
  public function now($key, $value) {
    $this->messages['now'][(string)$key] = $value;

    return $this;
  }

  /**
   * Offset unset
   *
   * @param mixed $offset
   */
  public function offsetUnset($offset) {
    unset($this->messages['prev'][$offset], $this->messages['now'][$offset]);
  }

  /**
   * Get iterator
   *
   * @return \ArrayIterator
   */
  public function getIterator() {
    return new \ArrayIterator($this->getMessages());
  }

  /**
   * Count
   *
   * @return int
   */
  public function count() {
    return count($this->getMessages());
  }

}