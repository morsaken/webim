<?php
/**
 * @author Orhan POLAT
 */

namespace Webim\Http;

class Router {

  /**
   * Instance
   *
   * @var Router
   */
  protected static $instance;

  /**
   * The current (most recently dispatched) route
   *
   * @var Webim\Http\Route
   */
  protected $currentRoute;

  /**
   * All route objects, numerically indexed
   *
   * @var array[Webim\Http\Route]
   */
  protected $routes = array();

  /**
   * Named route objects, indexed by route name
   *
   * @var array[Webim\Http\Route]
   */
  protected $namedRoutes = array();

  /**
   * Route objects that match the request URI
   *
   * @var array[Webim\Http\Route]
   */
  protected $matchedRoutes = array();

  /**
   * Route groups
   *
   * @var array
   */
  protected $routeGroups = array();

  /**
   * Constructor
   */
  public function __construct() {
    if (!static::$instance) {
      $this->routes = array();
      $this->routeGroups = array();

      static::$instance = $this;
    }
  }

  /**
   * Add a route
   *
   * @param Webim\Http\Route $route The route object
   */
  public static function mapRoute(Route $route) {
    static::make()->map($route);
  }

  /**
   * Add a route
   *
   * This method registers a Webim\Http\Route object with the router.
   *
   * @param Webim\Http\Route $route The route object
   */
  public function map(Route $route) {
    list($groupPattern, $groupMiddleware) = $this->processGroups();

    $route->setPattern($groupPattern . $route->getPattern());
    $this->routes[] = $route;

    foreach ($groupMiddleware as $middleware) {
      $route->setMiddleware($middleware);
    }
  }

  /**
   * Process route groups
   *
   * A helper method for processing the group's pattern and middleware.
   *
   * @return array An array with the elements: pattern, middlewareArr
   */
  protected function processGroups() {
    $pattern = '';
    $middleware = array();

    foreach ($this->routeGroups as $group) {
      $k = key($group);
      $pattern .= $k;

      if (is_array($group[$k])) {
        $middleware = array_merge($middleware, $group[$k]);
      }
    }

    return array($pattern, $middleware);
  }

  /**
   * Constructor
   *
   * @return Router
   */
  public static function make() {
    return (static::$instance ? static::$instance : new static());
  }

  /**
   * Route list
   *
   * @return array
   */
  public static function getRoutes() {
    return static::make()->routes;
  }

  /**
   * Get similar route
   *
   * @return Webim\Http\Route
   */
  public function getSimilarRoute() {
    if (!$this->currentRoute) {
      return $this->routes[0];
    }

    return $this->getCurrentRoute();
  }

  /**
   * Get current route
   *
   * This method will return the current Webim\Library\Route object. If a route
   * has not been dispatched, but route matching has been completed, the
   * first matching Webim\Library\Route object will be returned. If route matching
   * has not completed, null will be returned.
   *
   * @return Webim\Http\Route|null
   */
  public function getCurrentRoute() {
    if ($this->currentRoute !== null) {
      return $this->currentRoute;
    }

    if (is_array($this->matchedRoutes) && (count($this->matchedRoutes) > 0)) {
      return $this->matchedRoutes[0];
    }

    return null;
  }

  /**
   * Get route objects that match a given HTTP method and URI
   *
   * This method is responsible for finding and returning all Webim\Http\Route
   * objects that match a given HTTP method and URI. Webim uses this method to
   * determine which Webim\Http\Route objects are candidates to be
   * dispatched for the current HTTP request.
   *
   * @param string $httpMethod The HTTP request method
   * @param string $resourceUri The resource URI
   *
   * @return array[Webim\Http\Route]
   */
  public function getMatchedRoutes($httpMethod, $resourceUri) {
    $matchedRoutes = array();

    foreach ($this->routes as $route) {
      if (!$route->supportsHttpMethod($httpMethod) && !$route->supportsHttpMethod('ANY')) {
        continue;
      }

      if ($route->matches($resourceUri)) {
        $matchedRoutes[] = $route;
      }
    }

    $this->matchedRoutes = $matchedRoutes;

    return $matchedRoutes;
  }

  /**
   * Add a route group to the array
   *
   * @param string $group The group pattern (ie. "/books/:id")
   * @param array|null $middleware Optional parameter array of middleware
   *
   * @return int The index of the new group
   */
  public function pushGroup($group, $middleware = array()) {
    return array_push($this->routeGroups, array($group => $middleware));
  }

  /**
   * Removes the last route group from the array
   *
   * @return bool True if successful, else False
   */
  public function popGroup() {
    return (array_pop($this->routeGroups) !== null);
  }

  /**
   * Get URL for named route
   *
   * @param string $name The name of the route
   * @param array $params Associative array of URL parameter names and replacement values
   *
   * @return string  The URL for the given route populated with provided replacement values
   *
   * @throws \RuntimeException If named route not found
   */
  public function urlFor($name, $params = array()) {
    if (!$this->hasNamedRoute($name)) {
      throw new \RuntimeException('Named route not found for name: ' . $name);
    }

    $search = array();

    foreach ($params as $key => $value) {
      $search[] = '~:' . preg_quote($key, '~') . '\+?(?!\w)~';
    }

    $pattern = preg_replace($search, $params, preg_replace('~\#.*?\#~', '', $this->getNamedRoute($name)->getPattern()));

    //Remove remnants of unpopulated, trailing optional pattern segments, escaped special characters
    $pattern = preg_replace('~\(/?:.+\)|\??\(|\)|\\\\|\?~', '', $pattern);

    //Remove last double slashes
    return preg_replace('~\/{2,}~', '/', $pattern);
  }

  /**
   * Has named route
   *
   * @param string $name The route name
   *
   * @return bool
   */
  public function hasNamedRoute($name) {
    $this->getNamedRoutes();

    return isset($this->namedRoutes[(string)$name]);
  }

  /**
   * Get external iterator for named routes
   *
   * @return \ArrayIterator
   */
  public function getNamedRoutes() {
    if (is_null($this->namedRoutes)) {
      $this->namedRoutes = array();

      foreach ($this->routes as $route) {
        if ($route->getName() !== null) {
          $this->addNamedRoute($route->getName(), $route);
        }
      }
    }

    return new \ArrayIterator($this->namedRoutes);
  }

  /**
   * Add named route
   *
   * @param string $name The route name
   * @param Webim\Http\Route $route The route object
   *
   * @throws \RuntimeException If a named route already exists with the same name
   */
  public function addNamedRoute($name, Route $route) {
    if ($this->hasNamedRoute($name)) {
      throw new \RuntimeException('Named route already exists with name: ' . $name);
    }

    $this->namedRoutes[(string)$name] = $route;
  }

  /**
   * Get named route
   *
   * @param string $name
   *
   * @return Webim\Http\Route|null
   */
  public function getNamedRoute($name) {
    $this->getNamedRoutes();

    if ($this->hasNamedRoute($name)) {
      return $this->namedRoutes[(string)$name];
    }

    return null;
  }

}