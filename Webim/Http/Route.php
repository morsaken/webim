<?php
/**
 * @author Orhan POLAT
 */

namespace Webim\Http;

/**
 * Route
 *
 * This class is a relationship of HTTP method(s), an HTTP URI, and a callback
 * to create a Webim application route. The Webim application will determine
 * the one Route object to dispatch for the current HTTP request.
 *
 * Each route object will have a URI pattern. This pattern must match the
 * current HTTP request's URI for the route object to be dispatched by
 * the Webim application. The route pattern may contain parameters, segments
 * prefixed with a colon (:). For example:
 *
 *     /hello/:first/:last
 *     /hello/?(:first)/?(:last#[0-9]+#)
 *
 * When the route is dispatched, it's parameters array will be populated
 * with the values of the corresponding HTTP request URI segments.
 *
 * Each route object may also be assigned middleware; middleware are callbacks
 * to be invoked before the route's callable is invoked. Route middleware (not
 * to be confused with Webim application middleware) are useful for applying route
 * specific logic such as authentication.
 *
 */
class Route {

  /**
   * Default conditions applied to all route instances
   *
   * @var array
   */
  protected static $defaultConditions = array();
  /**
   * The route pattern (e.g. "/hello/:first/:name")
   *
   * @var string
   */
  protected $pattern;
  /**
   * The route callable
   *
   * @var mixed
   */
  protected $callable;
  /**
   * Conditions for this route's URL parameters
   *
   * @var array
   */
  protected $conditions = array();
  /**
   * The name of this route (optional)
   *
   * @var string
   */
  protected $name;

  /**
   * Array of URL parameters
   *
   * @var array
   */
  protected $params = array();

  /**
   * Array of URL parameter names
   *
   * @var array
   */
  protected $paramNames = array();

  /**
   * Matched?
   *
   * @var bool
   */
  protected $matched = false;

  /**
   * HTTP methods supported by this route
   *
   * @var array
   */
  protected $methods = array();

  /**
   * Middleware to be invoked before immediately before this route is dispatched
   *
   * @var array[Callable]
   */
  protected $middleware = array();

  /**
   * Constructor
   *
   * @param string $pattern The URL pattern
   * @param mixed $callable Anything that returns `true` for `is_callable()`
   * @param array $settings
   * @param bool $caseSensitive Whether or not this route should be matched in a case-sensitive manner
   */
  public function __construct($pattern, $callable, $settings = array(), $caseSensitive = true) {
    $this->setPattern($pattern);
    $this->setCallable($callable);
    $this->setConditions(static::getDefaultConditions());
    $this->setSettings($settings);
    $this->caseSensitive = $caseSensitive;
  }

  /**
   * Get default route conditions for all instances
   *
   * @return array
   */
  public static function getDefaultConditions() {
    return static::$defaultConditions;
  }

  /**
   * Set default route conditions for all routes
   *
   * @param array $defaultConditions
   */
  public static function setDefaultConditions(array $defaultConditions) {
    static::$defaultConditions = $defaultConditions;
  }

  /**
   * Set settings
   *
   * @param $settings
   */
  protected function setSettings($settings) {
    foreach ($settings as $key => $value) {
      $method = 'set' . ucfirst($key);

      if (method_exists($this, $method)) {
        call_user_func(array(
          $this,
          $method
        ), $value);
      }
    }
  }

  /**
   * Get route pattern
   *
   * @return string
   */
  public function getPattern() {
    return $this->pattern;
  }

  /**
   * Set route pattern
   *
   * @param string $pattern
   */
  public function setPattern($pattern) {
    $this->pattern = $pattern;
  }

  /**
   * Get route parameter value
   *
   * @param string $index Name of URL parameter
   *
   * @return string
   *
   * @throws \InvalidArgumentException  If route parameter does not exist at index
   */
  public function getParam($index) {
    if (!isset($this->params[$index])) {
      throw new \InvalidArgumentException('Route parameter does not exist at specified index');
    }

    return $this->params[$index];
  }

  /**
   * Set route parameter value
   *
   * @param string $index Name of URL parameter
   * @param mixed $value The new parameter value
   *
   * @throws \InvalidArgumentException  If route parameter does not exist at index
   */
  public function setParam($index, $value) {
    if (!isset($this->params[$index])) {
      throw new \InvalidArgumentException('Route parameter does not exist at specified index');
    }

    $this->params[$index] = $value;
  }

  /**
   * Add supported HTTP methods (this method accepts an unlimited number of string arguments)
   */
  public function setHttpMethods() {
    $this->methods = func_get_args();
  }

  /**
   * Get supported HTTP methods
   *
   * @return array
   */
  public function getHttpMethods() {
    return $this->methods;
  }

  /**
   * Is matched?
   *
   * @return bool
   */
  public function isMatched() {
    return $this->matched;
  }

  /**
   * Append supported HTTP methods
   *
   * @return $this
   */
  public function via() {
    $args = func_get_args();

    if (count($args) && is_array($args[0])) {
      $args = $args[0];
    }

    $this->methods = array_merge($this->methods, $args);

    return $this;
  }

  /**
   * Detect support for an HTTP method
   *
   * @param string $method
   *
   * @return bool
   */
  public function supportsHttpMethod($method) {
    return in_array($method, $this->methods);
  }

  /**
   * Get middleware
   *
   * @return array[Callable]
   */
  public function getMiddleware() {
    return $this->middleware;
  }

  /**
   * Set middleware
   *
   * This method allows middleware to be assigned to a specific Route.
   * If the method argument `is_callable` (including callable arrays!),
   * we directly append the argument to `$this->middleware`. Else, we
   * assume the argument is an array of callables and merge the array
   * with `$this->middleware`.  Each middleware is checked for is_callable()
   * and an InvalidArgumentException is thrown immediately if it isn't.
   *
   * @param Callable|array[Callable]
   *
   * @return $this
   *
   * @throws \InvalidArgumentException If argument is not callable or not an array of callables.
   */
  public function setMiddleware($middleware) {
    if (is_callable($middleware)) {
      $this->middleware[] = $middleware;
    } elseif (is_array($middleware)) {
      foreach ($middleware as $callable) {
        if (!is_callable($callable)) {
          throw new \InvalidArgumentException('All route middleware must be callable');
        }
      }

      $this->middleware = array_merge($this->middleware, $middleware);
    } else {
      throw new \InvalidArgumentException('Route middleware must be callable or an array of callables');
    }

    return $this;
  }

  /**
   * Matches URI?
   *
   * Parse this route's pattern, and then compare it to an HTTP resource URI
   *
   * @param string $resourceUri A Request URI
   *
   * @return bool
   */
  public function matches($resourceUri) {
    //Convert URL params into regex patterns, construct a regex for this route, init params
    $patternAsRegex = preg_replace_callback(
      '~\:([\w]+)\+?(?:#(.*?)#)?~',
      array($this, 'matchesCallback'),
      (string)$this->pattern
    );

    //Regex
    $regex = '~^' . rtrim($patternAsRegex, '/') . '/?' . '$~';

    if ($this->caseSensitive === false) {
      $regex .= 'i';
    }

    //Match status
    $matched = false;

    if (preg_match($regex, $resourceUri, $matches)) {
      //Set as matched
      $matched = true;

      foreach ($this->paramNames as $name) {
        if (isset($matches[$name])) {
          $this->params[$name] = urldecode(trim($matches[$name], '/'));
        }
      }
    }

    $this->matched = $matched;

    return $matched;
  }

  /**
   * Set route name
   *
   * @param null|string $name The name of the route
   *
   * @return $this|string
   */
  public function name($name = null) {
    if (is_null($name)) {
      return $this->getName();
    }

    $this->setName($name);

    return $this;
  }

  /**
   * Get route name (this may be null if not set)
   *
   * @return string|null
   */
  public function getName() {
    return $this->name;
  }

  /**
   * Set route name
   *
   * @param string $name
   */
  public function setName($name) {
    $this->name = (string)$name;
  }

  /**
   * Merge route conditions
   *
   * @param null|array $conditions Key-value array of URL parameter conditions
   *
   * @return $this|array
   */
  public function conditions(array $conditions = null) {
    if (is_null($conditions)) {
      return $this->getConditions();
    }

    $this->setConditions(array_merge($this->getConditions(), $conditions));

    return $this;
  }

  /**
   * Get route conditions
   *
   * @return array
   */
  public function getConditions() {
    return $this->conditions;
  }

  /**
   * Set route conditions
   *
   * @param array $conditions
   */
  public function setConditions(array $conditions) {
    $this->conditions = $conditions;
  }

  /**
   * Dispatch route
   *
   * This method invokes the route object's callable. If middleware is
   * registered for the route, each callable middleware is invoked in
   * the order specified.
   *
   * @param Webim\App $app
   *
   * @return bool
   */
  public function dispatch($app) {
    foreach ($this->middleware as $mw) {
      $return = call_user_func(
        \Closure::bind($mw, $app),
        array($this)
      );

      if ($return === false) {
        return false;
      }
    }

    $return = call_user_func(
      \Closure::bind($this->getCallable(), $app),
      $this->getParams()
    );

    return $return;
  }

  /**
   * Get route callable
   *
   * @return mixed
   */
  public function getCallable() {
    return $this->callable;
  }

  /**
   * Set route callable
   *
   * @param mixed $callable
   *
   * @throws \InvalidArgumentException If argument is not callable
   */
  public function setCallable($callable) {
    $matches = array();

    if (is_string($callable) && preg_match('!^([^\:]+)\:{2}([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)$!', $callable, $matches)) {
      $class = $matches[1];
      $method = $matches[2];
      $callable = function () use ($class, $method) {
        static $obj = null;
        if ($obj === null) {
          if (method_exists($class, 'init')) {
            $obj = $class::init();
          } else {
            $obj = new $class;
          }
        }

        return call_user_func_array(array($obj, $method), func_get_args());
      };
    }

    if (!is_callable($callable)) {
      throw new \InvalidArgumentException('Route callable must be callable');
    }

    $this->callable = $callable;
  }

  /**
   * Get route parameters
   *
   * @return array
   */
  public function getParams() {
    return $this->params;
  }

  /**
   * Set route parameters
   *
   * @param array $params
   */
  public function setParams(array $params) {
    $this->params = $params;
  }

  /**
   * Convert a URL parameter (e.g. ":id", ":id+", ":id#[a-z]{2}#) into a regular expression
   *
   * @param array $m URL parameters
   *
   * @return string Regular expression for URL parameter
   */
  protected function matchesCallback($m) {
    $this->paramNames[] = $m[1];

    if (isset($this->conditions[$m[1]])) {
      return '(?P<' . $m[1] . '>' . $this->conditions[$m[1]] . ')';
    }

    if (isset($m[2])) {
      return '(?P<' . $m[1] . '>' . $m[2] . ')';
    } elseif (substr($m[0], -1) === '+') {
      return '(?P<' . $m[1] . '>[^/]+)';
    }

    return '(?P<' . $m[1] . '>[^/]+)?';
  }

}