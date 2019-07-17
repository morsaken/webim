<?php
/**
 * @author Orhan POLAT
 */

namespace Webim\Library;

class Language {

  /**
   * Instance
   *
   * @var Language
   */
  protected static $instance;
  /**
   * Current language code
   *
   * @var string
   */
  protected $code;
  /**
   * Language vars
   *
   * @var array
   */
  protected $vars = array();

  /**
   * Has language in list
   *
   * @param string $lang
   *
   * @return bool
   */
  public static function has($lang) {
    $vars = static::current()->getVars();

    return !is_null($lang) && isset($vars[$lang]);
  }

  /**
   * Get language vars
   *
   * @param null|string $key
   *
   * @return array
   */
  public static function getVars($key = null) {
    if (!is_null($key)) {
      $key = static::current()->code() . '.' . $key;
    }

    return static::current()->vars($key);
  }

  /**
   * Get or set the current language code
   *
   * @param null|string $lang
   *
   * @return $this|string
   */
  public function code($lang = null) {
    if (!is_null($lang) && isset($this->vars[$lang])) {
      $this->code = $lang;

      return $this;
    }

    return $this->code;
  }

  /**
   * Current language
   *
   * @return Language
   */
  public static function current() {
    return (static::$instance ? static::$instance : static::init());
  }

  /**
   * Init class
   *
   * @param null|string $current
   *
   * @return Language
   */
  public static function init($current = null) {
    if (!static::$instance) {
      //Create instance
      static::$instance = new static;

      //Set current language
      static::$instance->code = $current;
    }

    return static::$instance;
  }

  /**
   * Get or set language strings
   *
   * @param null|array $list
   *
   * @return $this|array
   */
  public function vars($list = null) {
    if (is_array($list)) {
      //Get language files
      foreach ($list as $lang => $files) {
        //Main dir
        $main = 'language' . DIRECTORY_SEPARATOR . $lang;

        foreach ($files as $path => $file) {
          if ($file instanceof File) {
            //Make prefix to remove initials
            $prefix = str_replace(
              DIRECTORY_SEPARATOR,
              '.',
              trim(str_replace($main, '', $file->baseDir), DIRECTORY_SEPARATOR)
            );

            if ($lang === $file->name) {
              foreach ($file->load() as $key => $values) {
                //Create empty container
                $vars = array();

                //Set language info into vars
                array_set($vars, $lang . '.' . $key, $values);

                $this->vars = array_merge_recursive($this->vars, $vars);
              }
            } else {
              //Create empty container
              $vars = array();

              //Set content into vars
              array_set($vars, implode('.', array_filter(array($lang, $prefix, $file->name), function ($val) {
                return strlen($val);
              })), $file->load());

              //Set
              $this->vars = array_merge_recursive($this->vars, $vars);
            }
          }
        }
      }

      return $this;
    } elseif (is_string($list)) {
      return array_get($this->vars, $list);
    } else {
      return $this->vars;
    }
  }

  /**
   * Total languages
   *
   * @return int
   */
  public static function total() {
    return count(static::getList());
  }

  /**
   * Get languages as list
   *
   * @return array
   */
  public static function getList() {
    $list = array();

    foreach (static::current()->getVars() as $lang => $strings) {
      $list[$lang] = array(
        'name' => array_get($strings, 'name', $lang),
        'order' => intval(array_get($strings, 'order', 0))
      );
    }

    uasort($list, function ($a, $b) {
      return $a['order'] - $b['order'];
    });

    foreach ($list as $code => $values) {
      $list[$code] = $values['name'];
    }

    return $list;
  }

  /**
   * Crawl file to find language strings
   *
   * @param Webim\Library\File $path
   *
   * @return array
   */
  public static function crawl(File $path) {
    if (!($path instanceof File)) {
      throw new \LogicException('Path must be instance of Webim\Library\File!');
    }

    //All string list
    $list = array();

    //Find pattern
    $pattern = '/(?:(lang)\((?:([\'\"]+)((?:(?!\)).)*)\2)?\))/';

    foreach (func_get_args() as $path) {
      if ($path instanceof File) {
        //Files
        $files = $path->fileIn(array(
          '*.html',
          '*' . File::getGlobalPHPFileExt()
        ))->fileNotIn('index' . File::getGlobalPHPFileExt())->allFiles();

        foreach ($files as $file) {
          if (preg_match_all($pattern, $file->content(), $match)) {
            foreach ($match[3] as $key => $matched) {
              $params = preg_split('/([\'"]+)\]?,\s?\[?\1/', $matched);

              if (count($params) > 1) {
                array_set($list, $params[0], $params[count($params) - 1]);
              }
            }
          }
        }
      }
    }

    return $list;
  }

  /**
   * Get language string by choice
   *
   * @param string $key
   * @param int $count
   * @param array $params
   * @param null|mixed $default
   * @param null|string $lang
   *
   * @return string
   */
  public static function choice($key, $count = 1, $params = array(), $default = null, $lang = null) {
    return static::current()->translate($lang, $key, $count, $params, $default);
  }

  /**
   * Translate string
   *
   * @param null|string $lang
   * @param string $key
   * @param int $count
   * @param array $params
   * @param null|mixed $default
   *
   * @return string
   */
  public function translate($lang = null, $key, $count = 1, $params = array(), $default = null) {
    //Current language
    if (is_null($lang) || !is_string($lang) || !isset($this->vars[$lang])) {
      $lang = $this->code();
    }

    //Get strings
    $strings = array_get($this->vars, $lang . '.' . $key, $default);

    if (is_array($strings)) {
      $string = array_get($strings, 0);

      if ($count && (count($strings) > 1)) {
        $string = array_get($strings, 1);
      }
    } else {
      $string = $strings;
    }

    if ($count && !count($params)) {
      $params[] = $count;
    }

    return call_user_func_array('sprintf', array_merge(array($string), (array)$params));
  }

  /**
   * Get language string
   *
   * @param string $key
   * @param null|mixed $default
   * @param null|string $lang
   *
   * @return string
   */
  public static function get($key, $default = null, $lang = null) {
    if (is_array($default)) {
      $params = $default;
      $default = array_get(func_get_args(), 2);
      $lang = array_get(func_get_args(), 3);

      return static::getFormatted($key, $params, $default, $lang);
    }

    return static::current()->translate($lang, $key, 1, array(), $default);
  }

  /**
   * Get language string formatted
   *
   * @param string $key
   * @param array $params
   * @param null|mixed $default
   * @param null|string $lang
   *
   * @return mixed
   */
  protected static function getFormatted($key, $params = array(), $default = null, $lang = null) {
    return static::current()->translate($lang, $key, 1, $params, $default);
  }

  /**
   * Returns language var [Language::name()]
   *
   * @param string $method
   * @param array $args
   *
   * @return mixed
   */
  public static function __callstatic($method, $args = array()) {
    $class = static::current();

    return array_get($class->getVars(), $class->code() . '.' . $method, array_get($args, 0));
  }

  /**
   * Get current language abbreviation
   *
   * @return string
   */
  public function abbr() {
    if (is_null($this->code)) {
      $this->code = array_first(array_keys($this->vars));
    }

    return array_get($this->vars, $this->code . '.abbr', $this->code);
  }

  /**
   * Returns language code
   *
   * @return string
   */
  public function __toString() {
    return $this->code;
  }

  /**
   * Returns language var [Language::current()->name()]
   *
   * @param string $method
   * @param array $args
   *
   * @return mixed
   */
  public function __call($method, $args = array()) {
    return array_get($this->vars, $this->code() . '.' . $method, array_get($args, 0));
  }

}