<?php
/**
 * @author Orhan POLAT
 */

namespace Webim\Http;

use Webim\Library\Language;

class URL {

  /**
   * Instance
   *
   * @var URL
   */
  protected static $instance;

  /**
   * The route collection.
   *
   * @var Webim\Http\Route
   */
  protected $routes;

  /**
   * The request instance.
   *
   * @var Webim\Http\Request
   */
  protected $request;

  /**
   * The force URL root.
   *
   * @var string
   */
  protected $forcedRoot;

  /**
   * The forced schema for URLs.
   *
   * @var string
   */
  protected $forceSchema;

  /**
   * Characters that should not be URL encoded.
   *
   * @var array
   */
  protected $dontEncode = array(
    '%2F' => '/',
    '%40' => '@',
    '%3A' => ':',
    '%3B' => ';',
    '%2C' => ',',
    '%3D' => '=',
    '%2B' => '+',
    '%21' => '!',
    '%2A' => '*',
    '%7C' => '|',
  );

  /**
   * Create a new URL instance.
   */
  public function __construct() {
    if (!static::$instance) {
      $this->routes = Router::make();
      $this->request = Request::current();

      static::$instance = $this;
    }
  }

  /**
   * Make URL instance
   *
   * @return URL
   */
  public static function make() {
    return (static::$instance ? static::$instance : new static());
  }

  /**
   * Home page link
   *
   * @return string
   */
  public function home() {
    return $this->to('/');
  }

  /**
   * Generate a absolute URL to the given path.
   *
   * @param string $path
   * @param mixed $params
   * @param null|bool $secure
   *
   * @return string
   */
  public function to($path, $params = array(), $secure = null) {
    // First we will check if the URL is already a valid URL. If it is we will not
    // try to generate a new one but will simply return the URL as is, which is
    // convenient since developers do not always have to check if it's valid.
    if ($this->isValid($path)) return $path;

    $scheme = $this->getScheme($secure);

    $tail = implode('/', array_map('rawurlencode', (array)$params));

    // Once we have the scheme we will compile the "tail" by collapsing the values
    // into a single string delimited by slashes. This just makes it convenient
    // for passing the array of parameters to this URL as a list of segments.
    $root = $this->getRoot($scheme);

    //Trim to check
    $path = trim($path, '/');

    if (Language::total() > 1) {
      $lang = Language::current()->code();

      $segments = explode('/', $path);

      if ((strpos($path, $lang) !== 0) && !Language::has($segments[0])) {
        $path = $lang . '/' . $path;
      }
    }

    return $this->trim($root, $path, $tail);
  }

  /**
   * Determine if the given path is a valid URL.
   *
   * @param string $path
   *
   * @return bool
   */
  public function isValid($path) {
    if (starts_with($path, array('#', '//', 'mailto:', 'tel:'))) return true;

    return filter_var($path, FILTER_VALIDATE_URL) !== false;
  }

  /**
   * Get the scheme for a raw URL.
   *
   * @param bool $secure
   *
   * @return string
   */
  protected function getScheme($secure) {
    if (is_null($secure)) {
      return $this->forceSchema ?: $this->request->getScheme() . '://';
    } else {
      return $secure ? 'https://' : 'http://';
    }
  }

  /**
   * Get the base URL for the request.
   *
   * @param string $scheme
   * @param string $root
   *
   * @return string
   */
  protected function getRoot($scheme, $root = null) {
    if (is_null($root)) {
      $root = $this->forcedRoot ?: $this->request->root();
    }

    $start = starts_with($root, 'http://') ? 'http://' : 'https://';

    return preg_replace('~' . $start . '~', $scheme, $root, 1);
  }

  /**
   * Format the given URL segments into a single URL.
   *
   * @param string $root
   * @param string $path
   * @param string $tail
   *
   * @return string
   */
  protected function trim($root, $path, $tail = '') {
    return trim($root . '/' . trim($path . '/' . $tail, '/'), '/');
  }

  /**
   * Make the current URL to parent URL.
   *
   * @param int $level
   * @param null|bool $secure
   *
   * @return string
   */
  public function up($level = 1, $secure = null) {
    $segments = explode('/', $this->request->getPathInfo());

    for ($i = 0; $i < $level; $i++) {
      array_pop($segments);
    }

    return $this->to(implode('/', $segments), array(), $secure);
  }

  /**
   * Get the URL for the previous request.
   *
   * @return string
   */
  public function previous() {
    return $this->to($this->request->header('Referer'));
  }

  /**
   * Get the URL to a named route.
   *
   * @param string $name
   * @param mixed $parameters
   * @param bool $absolute
   *
   * @return string
   */
  public function route($name, $parameters = array(), $absolute = true) {
    return ($absolute ? $this->request->root() : '') . $this->routes->urlFor($name, $parameters);
  }

  /**
   * Generate a URL to a secure asset.
   *
   * @param string $path
   *
   * @return string
   */
  public function secureAsset($path) {
    return $this->asset($path, true);
  }

  /**
   * Generate a URL to an application asset.
   *
   * @param string $path
   * @param bool $secure
   *
   * @return string
   */
  public function asset($path, $secure = null) {
    if ($this->isValid($path)) return $path;

    // Once we get the root URL, we will check to see if it contains an index.php
    // file in the paths. If it does, we will remove it since it is not needed
    // for asset paths, but only for routes to endpoints in the application.
    $root = $this->getRoot($this->getScheme($secure));

    return $this->removeIndex($root) . '/' . trim($path, '/');
  }

  /**
   * Remove the index.php file from a path.
   *
   * @param string $root
   *
   * @return string
   */
  protected function removeIndex($root) {
    $i = 'index.php';

    return str_contains($root, $i) ? str_replace('/' . $i, '', $root) : $root;
  }

  /**
   * Force the schema for URLs.
   *
   * @param string $schema
   *
   * @return void
   */
  public function forceSchema($schema) {
    $this->forceSchema = $schema . '://';
  }

  /**
   * Compares current url with given path
   *
   * @param string $path
   * @param bool $like
   *
   * @return bool
   */
  public function is($path, $like = false) {
    $path = trim($path, '/');

    if (!strlen($path)) {
      $path = '/';
    }

    $url = static::to($path);
    $current = $this->current();

    if ($url == $current) {
      return true;
    }

    if ($like && (static::to('/') !== $url) && (strpos($current, $url) === 0)) {
      return '/' === substr(str_replace($url, '', $current), 0, 1);
    }

    return false;
  }

  /**
   * Get the current URL for the request.
   *
   * @param null|bool $secure
   *
   * @return string
   */
  public function current($secure = null) {
    return $this->to($this->request->getPathInfo(), array(), $secure);
  }

  /**
   * Add the port to the domain if necessary.
   *
   * @param string $domain
   *
   * @return string
   */
  protected function addPortToDomain($domain) {
    if (!in_array($this->request->getPort(), array('80', '443'))) {
      $domain .= ':' . $this->request->getPort();
    }

    return $domain;
  }

}