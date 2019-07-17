<?php
/**
 * @author Orhan POLAT
 */

namespace Webim\Library;

class Str {

  /**
   * Special characters
   *
   * @var array
   */
  protected static $chars = array();

  /**
   * Characters for usage
   *
   * @var array
   */
  protected $_chars = array();

  /**
   * String container
   *
   * @var string $str
   */
  protected $value;

  /**
   * Constructor
   *
   * @param string $value
   * @param null|array $chars
   */
  public function __construct($value, $chars = null) {
    if (is_array($chars)) {
      $this->_chars = $chars;
    } else {
      $this->_chars = static::getChars();
    }

    $this->value = (string)$value;
  }

  /**
   * Get special chars
   *
   * @param null|string $key
   *
   * @return array
   */
  public static function getChars($key = null) {
    return array_get(static::$chars, $key, array());
  }

  /**
   * Set special chars
   *
   * @param array $chars
   */
  public static function setChars($chars = array()) {
    static::$chars = $chars;
  }

  /**
   * Determine if a given string starts with a given substring.
   *
   * @param string $haystack
   * @param string|array $needles
   *
   * @return bool
   */
  public static function startsWith($haystack, $needles) {
    foreach ((array)$needles as $needle) {
      if ($needle != '' && strpos($haystack, $needle) === 0) return true;
    }

    return false;
  }

  /**
   * Determine if a given string ends with a given substring.
   *
   * @param string $haystack
   * @param string|array $needles
   *
   * @return bool
   */
  public static function endsWith($haystack, $needles) {
    foreach ((array)$needles as $needle) {
      if ((string)$needle === substr($haystack, -strlen($needle))) return true;
    }

    return false;
  }

  /**
   * Parse a method@Class style callback into class and method.
   *
   * @param string $callback
   * @param string $default
   *
   * @return array
   */
  public static function parseCallback($callback, $default) {
    return static::contains($callback, '@') ? explode('@', $callback, 2) : array($callback, $default);
  }

  /**
   * Determine if a given string contains a given substring.
   *
   * @param string $haystack
   * @param string|array $needles
   *
   * @return bool
   */
  public static function contains($haystack, $needles) {
    foreach ((array)$needles as $needle) {
      if ($needle != '' && strpos($haystack, $needle) !== false) return true;
    }

    return false;
  }

  /**
   * Generate a more truly "random" alpha-numeric string.
   *
   * @param int $length
   *
   * @return string
   */
  public static function random($length = 16) {
    if (function_exists('openssl_random_pseudo_bytes')) {
      $bytes = openssl_random_pseudo_bytes($length * 2);

      if ($bytes) {
        return substr(str_replace(array('/', '+', '='), '', base64_encode($bytes)), 0, $length);
      }
    }

    return static::quickRandom($length);
  }

  /**
   * Generate a "random" alpha-numeric string.
   *
   * Should not be considered sufficient for cryptography, etc.
   *
   * @param int $length
   *
   * @return string
   */
  public static function quickRandom($length = 16) {
    $pool = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

    return substr(str_shuffle(str_repeat($pool, 5)), 0, $length);
  }

  /**
   * Trim string text
   *
   * @param null|string $chars
   *
   * @return $this
   */
  public function trim($chars = null) {
    $this->value = trim($this->value, $chars);

    return $this;
  }

  /**
   * Return the length of the given string.
   *
   * @return int
   */
  public function length() {
    return preg_match_all('/./u', $this->value, $match);
  }

  /**
   * Convert the given string to upper-case.
   *
   * @return $this
   */
  public function upper() {
    $this->value = strtoupper(strtr($this->value, $this->chars('upper')));

    return $this;
  }

  /**
   * Get special chars in usage
   *
   * @param null|string $key
   *
   * @return mixed
   */
  public function chars($key = null) {
    return array_get($this->_chars, $key, array());
  }

  /**
   * Convert the given string to title case.
   *
   * @return string
   */
  public function capitalize() {
    //First lower all
    $this->lower();

    //Capitalized
    $capitalized = array();

    //Explode string
    $words = explode(' ', $this->value);

    foreach ($words as $word) {
      $capitalized[] = static::text($word)->upperFirst()->get();
    }

    $this->value = implode(' ', $capitalized);

    return $this;
  }

  /**
   * Convert the given string to lower-case.
   *
   * @return $this
   */
  public function lower() {
    $this->value = strtolower(strtr($this->value, $this->chars('lower')));

    return $this;
  }

  /**
   * Get string.
   *
   * @return string
   */
  public function get() {
    return (string)$this->value;
  }

  /**
   * Convert the given string's first letter to upper-case
   *
   * @return $this
   */
  public function upperFirst() {
    //Match
    preg_match_all('/./u', $this->value, $match);

    //Get first
    $first = implode('', array_slice(array_get($match, 0, array()), 0, 1));
    $rest = implode('', array_slice(array_get($match, 0, array()), 1));

    //Uppercase first letter
    $first = ucfirst(strtr($first, $this->chars('upper')));

    //Implode all
    $this->value = $first . $rest;

    return $this;
  }

  /**
   * Static constructor
   *
   * @param string $value
   * @param null|array $chars
   *
   * @return $this
   */
  public static function text($value, $chars = null) {
    return new static($value, $chars);
  }

  /**
   * Convert a value to camel case.
   *
   * @return $this
   */
  public function camel() {
    $this->studly();

    $this->value = lcfirst($this->value);

    return $this;
  }

  /**
   * Convert a value to studly caps case.
   *
   * @return string
   */
  public function studly() {
    $this->normalize();

    $value = ucwords(str_replace(array('-', '_'), ' ', $this->value));

    $this->value = str_replace(' ', '', $value);

    return $this;
  }

  /**
   * Normalize string text
   *
   * @return $this
   */
  public function normalize() {
    //Change to lower first
    $this->lower();

    //Change special chars
    $this->value = strtr($this->value, $this->chars('normalize'));

    //Remove others
    $this->value = preg_replace('/[^a-z0-9]/i', '_', $this->value);

    return $this;
  }

  /**
   * Convert a string to snake case.
   *
   * @param string $delimiter
   *
   * @return $this
   */
  public function snake($delimiter = '_') {
    $this->normalize();

    $replace = '$1' . $delimiter . '$2';

    $this->value = strtolower(preg_replace('/(.)([A-Z])/', $replace, $this->value));

    return $this;
  }

  /**
   * Cap a string with a single instance of a given value.
   *
   * @param string $cap
   *
   * @return $this
   */
  public function finish($cap) {
    $quoted = preg_quote($cap, '/');

    $this->value = preg_replace('/(?:' . $quoted . ')+$/', '', $this->value) . $cap;

    return $this;
  }

  /**
   * Determine if a given string matches a given pattern.
   *
   * @param string $pattern
   *
   * @return bool
   */
  public function is($pattern) {
    if ($pattern == $this->value) return true;

    $pattern = preg_quote($pattern, '#');

    // Asterisks are translated into zero-or-more regular expression wildcards
    // to make it convenient to check if the strings starts with the given
    // pattern such as "Lib/*", making any string check convenient.
    $pattern = str_replace('\*', '.*', $pattern) . '\z';

    return (bool)preg_match('#^' . $pattern . '#', $this->value);
  }

  /**
   * Checks the string is UTF-8
   *
   * @return bool
   */
  public function isUTF8() {
    return preg_match('%(?:
   [\xC2-\xDF][\x80-\xBF]             # non-overlong 2-byte
   |\xE0[\xA0-\xBF][\x80-\xBF]        # excluding overlongs
   |[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2} # straight 3-byte
   |\xED[\x80-\x9F][\x80-\xBF]        # excluding surrogates
   |\xF0[\x90-\xBF][\x80-\xBF]{2}     # planes 1-3
   |[\xF1-\xF3][\x80-\xBF]{3}         # planes 4-15
   |\xF4[\x80-\x8F][\x80-\xBF]{2}     # plane 16
   )+%xs', $this->value);
  }

  /**
   * Checks the string is serialized
   *
   * @return bool
   */
  public function isSerialized() {
    return (@unserialize($this->value) !== false);
  }

  /**
   * Limit the number of characters in a string.
   *
   * @param int $limit
   * @param int $offset
   * @param bool $strict
   * @param string $end
   *
   * @return $this
   */
  public function limit($limit = 30, $offset = 0, $strict = false, $end = ' ...') {
    //Return
    $str = strip_tags($this->value);

    //New string
    $newStr = '';

    //Length of the string
    $strLen = preg_match_all('/./u', $str, $match);

    if ($strict) {
      //Wrap string
      $newStr = implode('', array_slice($match[0], $offset, $limit));
    } else {
      //Length
      $len = 0;

      //Words
      $words = explode(' ', $str);

      //String array
      $strArray = array();

      //Cycle
      foreach ($words as $word) {
        //Length of the string
        $wordLen = preg_match_all('/./u', $word, $match);

        //Add to length
        $len += $wordLen;

        if ($len >= $offset) {
          if ($len > $limit) {
            $len--;

            break;
          }

          //Add word
          $strArray[] = $word;

          //Add space
          $len++;
        }
      }

      $newStr = implode(' ', $strArray);
    }

    if (($strLen > $limit) && (strlen($newStr) > 0)) {
      $newStr .= $end;
    }

    //Change string
    $this->value = $newStr;

    return $this;
  }

  /**
   * Generate a URL friendly "slug" from a given string.
   *
   * @param string $separator
   *
   * @return $this
   */
  public function slug($separator = '-') {
    $this->normalize();

    // Convert all dashes/underscores into separator
    $flip = (($separator == '-') ? '_' : '-');

    $this->value = preg_replace('![' . preg_quote($flip) . ']+!u', $separator, $this->value);

    // Remove all characters that are not the separator, letters, numbers, or whitespace.
    $this->value = preg_replace('![^' . preg_quote($separator) . '\pL\pN\s]+!u', '', $this->value);

    // Replace all separator characters and whitespace by a single separator
    $this->value = preg_replace('![' . preg_quote($separator) . '\s]+!u', $separator, $this->value);

    $this->value = trim($this->value, $separator);

    return $this;
  }

  /**
   * Get the singular form of an English word.
   *
   * @return string
   */
  public function singular() {
    return Pluralizer::singular($this->value);
  }

  /**
   * Get the plural form of an English word.
   *
   * @param int $count
   *
   * @return string
   */
  public function plural($count = 2) {
    return Pluralizer::plural($this->value, $count);
  }

  /**
   * Get string.
   *
   * @return string
   */
  public function __toString() {
    return $this->get();
  }

}