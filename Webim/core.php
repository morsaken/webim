<?php
/**
 * @author Orhan POLAT
 */

use Webim\Http\Request;
use Webim\Http\URL;
use Webim\Library\Arr;
use Webim\Library\Auth;
use Webim\Library\AutoLoader;
use Webim\Database\Manager as DB;
use Webim\Library\Carbon;
use Webim\Library\Config;
use Webim\Library\Input;
use Webim\Library\Language;
use Webim\Library\Str;

//Auto loader class
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'Library' . DIRECTORY_SEPARATOR . 'AutoLoader.php');

//Register auto load
AutoLoader::register(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR);

if (!function_exists('db')) {
  /**
   * Return the given name connection or default connection instance
   *
   * @param null|string $name
   *
   * @return Webim\Database\Driver\Connection
   */
  function db($name = null) {
    return DB::connection($name);
  }
}

if (!function_exists('value')) {
  /**
   * Return the default value of the given value.
   *
   * @param mixed $value
   *
   * @return mixed
   */
  function value($value) {
    return $value instanceof Closure ? $value() : $value;
  }
}

if (!function_exists('with')) {
  /**
   * Return the given object. Useful for chaining.
   *
   * @param mixed $object
   *
   * @return mixed
   */
  function with($object) {
    return $object;
  }
}

if (!function_exists('first')) {
  /**
   * Get the first element of an array. Useful for method chaining.
   *
   * @param array $array
   *
   * @return mixed
   */
  function first($array) {
    return reset($array);
  }
}

if (!function_exists('last')) {
  /**
   * Get the last element from an array.
   *
   * @param array $array
   *
   * @return mixed
   */
  function last($array) {
    return end($array);
  }
}

if (!function_exists('object_get')) {
  /**
   * Get an item from an object using "dot" notation.
   *
   * @param object|array $object
   * @param string $key
   * @param mixed $default
   *
   * @return mixed
   */
  function object_get($object, $key, $default = null) {
    return Arr::get($object, $key, $default);
  }
}

if (!function_exists('array_add')) {
  /**
   * Add an element to an array using "dot" notation if it doesn't exist.
   *
   * @param array $array
   * @param string $key
   * @param mixed $value
   *
   * @return array
   */
  function array_add($array, $key, $value) {
    return Arr::add($array, $key, $value);
  }
}

if (!function_exists('array_build')) {
  /**
   * Build a new array using a callback.
   *
   * @param array $array
   * @param \Closure $callback
   *
   * @return array
   */
  function array_build($array, Closure $callback) {
    return Arr::build($array, $callback);
  }
}

if (!function_exists('array_divide')) {
  /**
   * Divide an array into two arrays. One with keys and the other with values.
   *
   * @param array $array
   *
   * @return array
   */
  function array_divide($array) {
    return Arr::divide($array);
  }
}

if (!function_exists('array_dot')) {
  /**
   * Flatten a multi-dimensional associative array with dots.
   *
   * @param array $array
   * @param string $prepend
   *
   * @return array
   */
  function array_dot($array, $prepend = '') {
    return Arr::dot($array, $prepend);
  }
}

if (!function_exists('array_except')) {
  /**
   * Get all of the given array except for a specified array of items.
   *
   * @param array $array
   * @param array|string $keys
   *
   * @return array
   */
  function array_except($array, $keys) {
    return Arr::except($array, $keys);
  }
}

if (!function_exists('array_fetch')) {
  /**
   * Fetch a flattened array of a nested array element.
   *
   * @param array $array
   * @param string $key
   *
   * @return array
   */
  function array_fetch($array, $key) {
    return Arr::fetch($array, $key);
  }
}

if (!function_exists('array_first')) {
  /**
   * Return the first element in an array passing a given truth test.
   *
   * @param array $array
   * @param null|\Closure $callback
   * @param mixed $default
   *
   * @return mixed
   */
  function array_first($array, $callback = null, $default = null) {
    return Arr::first($array, $callback, $default);
  }
}

if (!function_exists('array_last')) {
  /**
   * Return the last element in an array passing a given truth test.
   *
   * @param array $array
   * @param null|\Closure $callback
   * @param mixed $default
   *
   * @return mixed
   */
  function array_last($array, $callback = null, $default = null) {
    return Arr::last($array, $callback, $default);
  }
}

if (!function_exists('array_flatten')) {
  /**
   * Flatten a multi-dimensional array into a single level.
   *
   * @param array $array
   *
   * @return array
   */
  function array_flatten($array) {
    return Arr::flatten($array);
  }
}

if (!function_exists('array_forget')) {
  /**
   * Remove one or many array items from a given array using "dot" notation.
   *
   * @param array $array
   * @param array|string $keys
   *
   * @return void
   */
  function array_forget(&$array, $keys) {
    Arr::forget($array, $keys);
  }
}

if (!function_exists('array_get')) {
  /**
   * Get an item from an array using "dot" notation.
   *
   * @param array $array
   * @param string $key
   * @param mixed $default
   *
   * @return mixed
   */
  function array_get($array, $key, $default = null) {
    if (is_object($array)) {
      return object_get($array, $key, $default);
    }

    return Arr::get($array, $key, $default);
  }
}

if (!function_exists('array_only')) {
  /**
   * Get a subset of the items from the given array.
   *
   * @param array $array
   * @param array|string $keys
   *
   * @return array
   */
  function array_only($array, $keys) {
    return Arr::only($array, $keys);
  }
}

if (!function_exists('array_pluck')) {
  /**
   * Pluck an array of values from an array.
   *
   * @param array $array
   * @param string $value
   * @param string $key
   *
   * @return array
   */
  function array_pluck($array, $value, $key = null) {
    return Arr::pluck($array, $value, $key);
  }
}

if (!function_exists('array_pull')) {
  /**
   * Get a value from the array, and remove it.
   *
   * @param array $array
   * @param string $key
   * @param mixed $default
   *
   * @return mixed
   */
  function array_pull(&$array, $key, $default = null) {
    return Arr::pull($array, $key, $default);
  }
}

if (!function_exists('array_set')) {
  /**
   * Set an array item to a given value using "dot" notation.
   *
   * If no key is given to the method, the entire array will be replaced.
   *
   * @param array $array
   * @param string $key
   * @param mixed $value
   *
   * @return array
   */
  function array_set(&$array, $key, $value) {
    return Arr::set($array, $key, $value);
  }
}

if (!function_exists('array_sort')) {
  /**
   * Sort the array using the given Closure.
   *
   * @param array $array
   * @param Closure $callback
   *
   * @return array
   */
  function array_sort($array, Closure $callback) {
    return Arr::sort($array, $callback);
  }
}

if (!function_exists('array_where')) {
  /**
   * Filter the array using the given Closure.
   *
   * @param array $array
   * @param Closure $callback
   *
   * @return array
   */
  function array_where($array, Closure $callback) {
    return Arr::where($array, $callback);
  }
}

if (!function_exists('array_merge_distinct')) {
  /**
   * Merge arrays with distinct keys.
   *
   * @param array $array0
   * @param array $array1
   *
   * @return array
   */
  function array_merge_distinct($array0, $array1) {
    return forward_static_call_array('Webim\Library\Arr::merge', func_get_args());
  }
}

if (!function_exists('array_to')) {
  /**
   * Convert array to given format.
   *
   * @param array $array
   * @param string $as
   * @param bool $show
   *
   * @return string
   */
  function array_to($array, $as = 'json', $show = false) {
    $str = Arr::to($array, $as);

    if ($show) {
      echo $str;
    }

    return $str;
  }
}

if (!function_exists('array_to_string')) {
  /**
   * Convert array to string.
   *
   * @param array $array
   * @param string $glue
   *
   * @return string
   */
  function array_to_string($array, $glue = ' ') {
    return Arr::str($array, $glue);
  }
}

if (!function_exists('snake_case')) {
  /**
   * Convert a string to snake case.
   *
   * @param string $value
   * @param string $delimiter
   *
   * @return string
   */
  function snake_case($value, $delimiter = '_') {
    return Str::text($value)->snake($delimiter)->get();
  }
}

if (!function_exists('starts_with')) {
  /**
   * Determine if a given string starts with a given substring.
   *
   * @param string $haystack
   * @param string|array $needles
   *
   * @return bool
   */
  function starts_with($haystack, $needles) {
    return Str::startsWith($haystack, $needles);
  }
}

if (!function_exists('ends_with')) {
  /**
   * Determine if a given string ends with a given substring.
   *
   * @param string $haystack
   * @param string|array $needles
   *
   * @return bool
   */
  function ends_with($haystack, $needles) {
    return Str::endsWith($haystack, $needles);
  }
}

if (!function_exists('str_case')) {
  /**
   * Change string case.
   *
   * @param string $value
   * @param string $case
   *
   * @return string
   */
  function str_case($value, $case = 'upper') {
    $str = Str::text($value);

    switch ($case) {
      case 'upper';

        $str->upper();

        break;
      case 'lower';

        $str->lower();

        break;
      case 'upperFirst';

        $str->upperFirst();

        break;
      case 'capitalize';

        $str->capitalize();

        break;
      case 'normalize';

        $str->normalize();

        break;
    }

    return $str->get();
  }
}

if (!function_exists('str_contains')) {
  /**
   * Determine if a given string contains a given substring.
   *
   * @param string $haystack
   * @param string|array $needles
   *
   * @return bool
   */
  function str_contains($haystack, $needles) {
    return Str::contains($haystack, $needles);
  }
}

if (!function_exists('str_finish')) {
  /**
   * Cap a string with a single instance of a given value.
   *
   * @param string $value
   * @param string $cap
   *
   * @return string
   */
  function str_finish($value, $cap) {
    return Str::text($value)->finish($cap);
  }
}

if (!function_exists('str_is')) {
  /**
   * Determine if a given string matches a given pattern.
   *
   * @param string $pattern
   * @param string $value
   *
   * @return bool
   */
  function str_is($pattern, $value) {
    return Str::text($value)->is($pattern);
  }
}

if (!function_exists('str_limit')) {
  /**
   * Limit the number of characters in a string.
   *
   * @param string $value
   * @param int $limit
   * @param int $offset
   * @param bool $strict
   * @param string $end
   *
   * @return string
   */
  function str_limit($value, $limit = 100, $offset = 0, $strict = false, $end = ' ...') {
    return Str::text($value)->limit($limit, $offset, $strict, $end)->get();
  }
}

if (!function_exists('str_random')) {
  /**
   * Generate a more truly "random" alpha-numeric string.
   *
   * @param int $length
   *
   * @return string
   */
  function str_random($length = 16) {
    return Str::random($length);
  }
}

if (!function_exists('str_replace_array')) {
  /**
   * Replace a given value in the string sequentially with an array.
   *
   * @param string $search
   * @param array $replace
   * @param string $subject
   *
   * @return string
   */
  function str_replace_array($search, array $replace, $subject) {
    foreach ($replace as $value) {
      $subject = preg_replace('/' . $search . '/', $value, $subject, 1);
    }

    return $subject;
  }
}

if (!function_exists('studly_case')) {
  /**
   * Convert a value to studly caps case.
   *
   * @param string $value
   *
   * @return string
   */
  function studly_case($value) {
    return Str::text($value)->studly()->get();
  }
}

if (!function_exists('camel_case')) {
  /**
   * Convert a value to camel case.
   *
   * @param string $value
   *
   * @return string
   */
  function camel_case($value) {
    return Str::text($value)->camel()->get();
  }
}


if (!function_exists('str_singular')) {
  /**
   * Get the singular form of an English word.
   *
   * @param string $value
   *
   * @return string
   */
  function str_singular($value) {
    return Str::text($value)->singular()->get();
  }
}

if (!function_exists('str_plural')) {
  /**
   * Get the plural form of an English word.
   *
   * @param string $value
   * @param int $count
   *
   * @return string
   */
  function str_plural($value, $count = 2) {
    return Str::text($value)->plural($count)->get();
  }
}

if (!function_exists('slug')) {
  /**
   * Convert text to slug text
   *
   * @param string $value
   * @param string $separator
   *
   * @return string
   */
  function slug($value, $separator = '-') {
    return Str::text($value)->slug($separator)->get();
  }
}

if (!function_exists('e')) {
  /**
   * Escape HTML entities in a string.
   *
   * @param string $value
   *
   * @return string
   */
  function e($value) {
    return htmlentities($value, ENT_QUOTES, 'UTF-8', false);
  }
}

if (!function_exists('conf')) {
  /**
   * Configuration value.
   *
   * @param string $key
   * @param null|string $default
   *
   * @return mixed
   */
  function conf($key, $default = null) {
    return Config::get($key, $default);
  }
}

if (!function_exists('previous_url')) {
  /**
   * Previous url string
   *
   * @return string
   */
  function previous_url() {
    return URL::make()->previous();
  }
}

if (!function_exists('url')) {
  /**
   * Creates url string.
   *
   * @param null|string $path
   * @param array $params
   * @param null|bool $secure
   *
   * @return string
   */
  function url($path = null, $params = array(), $secure = null) {
    if (is_null($path)) {
      return URL::make()->current($secure);
    } elseif (starts_with($path, '#current')) {
      $path = Request::current()->url() . str_replace('#current', '', $path);
    }

    return URL::make()->to($path, $params, $secure);
  }
}

if (!function_exists('url_is')) {
  /**
   * Compares url.
   *
   * @param string $path
   * @param bool $like
   *
   * @return string
   */
  function url_is($path, $like = false) {
    return URL::make()->is($path, $like);
  }
}

if (!function_exists('url_up')) {
  /**
   * URL to parent URL.
   *
   * @param int $level
   * @param null|bool $secure
   *
   * @return string
   */
  function url_up($level = 1, $secure = null) {
    return URL::make()->up($level, $secure);
  }
}

if (!function_exists('asset')) {
  /**
   * Creates asset string.
   *
   * @param string $path
   * @param null|bool $secure
   *
   * @return string
   */
  function asset($path, $secure = null) {
    return URL::make()->asset($path, $secure);
  }
}

if (!function_exists('root')) {
  /**
   * Web root.
   *
   * @return string
   */
  function root() {
    return Request::current()->root();
  }
}

if (!function_exists('langs')) {
  /**
   * Available languages.
   *
   * @return array
   */
  function langs() {
    return Language::getList();
  }
}

if (!function_exists('lang')) {
  /**
   * Translate string.
   *
   * @param null|string $key
   * @param null|mixed $default
   * @param null|string $lang
   *
   * @return string
   */
  function lang($key = null, $default = null, $lang = null) {
    if (is_null($key)) {
      return Language::current()->code($lang);
    }

    if (is_array($default)) {
      return forward_static_call_array('Webim\Library\Language::get', func_get_args());
    }

    return Language::get($key, $default, $lang);
  }
}

if (!function_exists('choice')) {
  /**
   * Translate choicable string.
   *
   * @param string $key
   * @param int $count
   * @param array $params
   * @param null|mixed $default
   * @param null|string $lang
   *
   * @return string
   */
  function choice($key, $count = 1, $params = array(), $default = null, $lang = null) {
    return Language::choice($key, $count, $params, $default, $lang);
  }
}

if (!function_exists('input')) {
  /**
   * Input value
   *
   * @param string $name
   * @param string $default
   * @param array $options
   *
   * @return mixed
   */
  function input($name, $default = '', $options = array()) {
    $input = Input::name($name, $default);

    if (array_get($options, 'removeTags', false)) {
      $input->removeTags(true);
    }

    if (array_get($options, 'htmlTags', false)) {
      $input->htmlTags(true);
    }

    if (!array_get($options, 'trimSpaces', true)) {
      $input->trimSpaces(false);
    }

    return $input->val();
  }
}

if (!function_exists('raw_input')) {
  /**
   * Raw input value
   *
   * @param string $name
   * @param string $default
   *
   * @return mixed
   */
  function raw_input($name, $default = '') {
    $input = Input::name($name, $default);

    //Set options
    $input->removeTags(false)->htmlTags(true)->trimSpaces(false);

    return $input->val();
  }
}

if (!function_exists('date_show')) {
  /**
   * Show date
   *
   * @param string|Carbon $date
   * @param string $format
   * @param bool $withHours
   *
   * @return string
   */
  function date_show($date, $format = 'long', $withHours = false) {
    if (!$date instanceof Carbon) {
      $date = Carbon::parse($date);
    }

    //Date vars
    $day = $date->format('d');
    $month = $date->format('m');
    $month_name = lang('date.months.' . $date->format('n'));
    $month_short_name = str_limit($month_name, 3, 0, true, '');
    $year = $date->format('Y');
    $day_name = lang('date.days.' . $date->format('w'));

    //Hour vars
    $hour = $date->format('H');
    $minute = $date->format('i');
    $second = $date->format('s');

    //Return
    $readable = lang('date.format.' . $format, $format);

    if (!strlen($readable) || in_array($readable, array('short', 'long'), true)) {
      $readable = '{day} {month_short_name} {year}';
    }

    if ($withHours) {
      $readable .= ' {hour}:{minute}:{second}';
    }

    //Match
    preg_match_all('/\{(.*?)\}/', $readable, $matches);

    foreach (array_get($matches, 1, array()) as $match) {
      if (isset($$match) && is_scalar($$match)) {
        $readable = str_replace('{' . $match . '}', $$match, $readable);
      }
    }

    return $readable;
  }
}

if (!function_exists('date_ago')) {
  /**
   * Date difference as human readable
   *
   * @param string|Carbon $date
   *
   * @return string
   */
  function date_ago($date) {
    if (!$date instanceof Carbon) {
      $date = Carbon::parse($date);
    }

    $ago = $date->diffForHumans();

    if (preg_match('/(\d+)/', $ago, $match)) {
      $val = $match[1];
      $key = str_replace(' ', '_', trim(str_replace($val, '', $ago)));

      return lang('date.diff.' . $key, array($val), $ago);
    }

    return $ago;
  }
}

if (!function_exists('now')) {
  /**
   * Date now
   *
   * @param string $format
   * @param bool $withHours
   *
   * @return string
   */
  function now($format = 'long', $withHours = false) {
    return date_show(Carbon::now(), $format, $withHours);
  }
}

if (!function_exists('my')) {
  /**
   * @param null|string $key
   * @param null|string $default
   *
   * @return mixed
   */
  function my($key = null, $default = null) {
    return Auth::current()->get($key, $default);
  }
}

if (!function_exists('dump')) {
  /**
   * Dump shortcut.
   *
   * @param $array
   */
  function dump($array) {
    echo '<pre>';
    print_r($array);
    echo '</pre>';
  }
}