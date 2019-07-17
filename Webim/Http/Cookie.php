<?php
/**
 * @author Orhan POLAT
 */

namespace Webim\Http;

use Webim\Library\Collection;
use Webim\Library\Crypt;

class Cookie {

  /**
   * New cookies container
   *
   * @var Collection
   */
  protected $data;

  /**
   * Existed cookies container
   *
   * @var Collection
   */
  protected $list;

  /**
   * Default cookie settings
   *
   * @var array
   */
  protected $default = array(
    'value' => '',
    'expires' => 0,
    'path' => '/',
    'domain' => null,
    'secure' => false,
    'httponly' => true
  );

  /**
   * Constructor, will parse headers for cookie information if present
   *
   * @param Webim\Library\Collection $headers
   */
  public function __construct($headers) {
    $this->data = new Collection(array());
    $this->list = new Collection($this->parseHeader($headers->get('Cookie', '')));
  }

  /**
   * Parse cookie header
   *
   * This method will parse the HTTP request's `Cookie` header
   * and extract an associative array of cookie names and values.
   *
   * @param string $header
   *
   * @return array
   */
  public function parseHeader($header) {
    $header = rtrim($header, "\r\n");
    $pieces = preg_split('@\s*[;,]\s*@', $header);
    $cookies = array();

    foreach ($pieces as $cookie) {
      $cookie = explode('=', $cookie, 2);

      if (count($cookie) === 2) {
        $key = urldecode($cookie[0]);
        $value = urldecode($cookie[1]);

        if (!isset($cookies[$key])) {
          $cookies[$key] = $value;
        }
      }
    }

    return $cookies;
  }

  /**
   * Has cookie
   *
   * @param string $key
   *
   * @return bool
   */
  public function has($key) {
    return $this->list->has($key);
  }

  /**
   * Get cookie
   *
   * @param null|string $key
   * @param null|string $default
   *
   * @return mixed
   */
  public function get($key = null, $default = null) {
    if (is_null($key)) {
      return $this->list->all();
    }

    return $this->list->get($key, $default);
  }

  /**
   * Remove cookie
   *
   * Unlike Webim\Library\Collection, this will actually *set* a cookie with
   * an expiration date in the past. This expiration date will force
   * the client-side cache to remove its cookie with the given name
   * and settings.
   *
   * @param string $key Cookie name
   * @param array $settings Optional cookie settings
   */
  public function remove($key, $settings = array()) {
    $settings['value'] = '';
    $settings['expires'] = time() - 86400;

    $this->set($key, array_replace($this->default, $settings));
  }

  /**
   * Set cookie
   *
   * The second argument may be a single scalar value, in which case
   * it will be merged with the default settings and considered the `value`
   * of the merged result.
   *
   * The second argument may also be an array containing any or all of
   * the keys shown in the default settings above. This array will be
   * merged with the defaults shown above.
   *
   * @param string $key Cookie name
   * @param mixed $value Cookie settings
   * @param mixed $expires
   * @param string $path
   * @param null|string $domain
   * @param bool $secure
   * @param bool $httponly
   *
   * @return $this
   */
  public function set($key, $value, $expires = 0, $path = '/', $domain = null, $secure = false, $httponly = true) {
    if (is_array($value)) {
      $settings = array_replace($this->default, $value);
    } else {
      $settings = array_replace($this->default, array(
        'value' => $value,
        'expires' => (is_string($expires) ? strtotime($expires) : intval($expires)),
        'path' => $path,
        'domain' => $domain,
        'secure' => $secure,
        'httponly' => $httponly
      ));
    }

    $this->data->set($key, $settings);

    return $this;
  }

  /**
   * Encrypt cookies
   *
   * This method iterates and encrypts data values.
   *
   * @param Webim\Library\Crypt $crypt
   *
   * @return $this
   */
  public function encrypt(Crypt $crypt) {
    foreach ($this->data->all() as $name => $values) {
      $values['value'] = $crypt->encrypt($values['value']);
      $this->set($name, $values);
    }

    return $this;
  }

  /**
   * New cookies
   *
   * @return array
   */
  public function all() {
    return $this->data->all();
  }

  /**
   * Decrypt cookies
   *
   * This method decrypt list values.
   *
   * @param Webim\Library\Crypt $crypt
   *
   * @return $this
   */
  public function decrypt(Crypt $crypt) {
    //To be changed
    $list = array();

    foreach ($this->list->all() as $name => $value) {
      $list[$name] = $crypt->decrypt($value);
    }

    $this->list->replace($list);

    return $this;
  }

  /**
   * Returns the cookies as a string.
   *
   * @return string The cookie
   */
  public function __toString() {
    $vars = array();

    foreach ($this->data as $name => $values) {
      $str = urlencode($name) . '=';

      if (is_array($values)) {
        $value = array_get($values, 'value', '');
        $expires = array_get($values, 'expires', 0);
        $path = array_get($values, 'path');
        $domain = array_get($values, 'domain');
        $secure = array_get($values, 'secure', false);
        $httponly = array_get($values, 'httponly', false);

        if ('' === (string)$value) {
          $str .= 'deleted; expires=' . gmdate("D, d-M-Y H:i:s T", time() - 31536001);
        } else {
          $str .= urlencode($value);

          if ($expires !== 0) {
            $str .= '; expires=' . gmdate("D, d-M-Y H:i:s T", $expires);
          }
        }

        if ($path) {
          $str .= '; path=' . $path;
        }

        if ($domain) {
          $str .= '; domain=' . $domain;
        }

        if (true === $secure) {
          $str .= '; secure';
        }

        if (true === $httponly) {
          $str .= '; httponly';
        }
      } else {
        $str .= $values;
      }

      $vars[] = $str;
    }

    return implode("\n", $vars);
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
    if (!method_exists($this->data, $method)) {
      $className = get_class($this);

      throw new \BadMethodCallException("Call to undefined method {$className}::{$method}()");
    }

    return call_user_func_array(array($this->data, $method), $args);
  }

}