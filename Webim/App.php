<?php
/**
 * @author Orhan POLAT
 */

namespace Webim;

use Closure;
use Exception;
use Webim\Cache\Manager as Cache;
use Webim\Database\Manager as DB;
use Webim\Http\Redirect;
use Webim\Http\Request;
use Webim\Http\Response;
use Webim\Http\Route;
use Webim\Http\Router;
use Webim\Http\Session;
use Webim\Library\Config;
use Webim\Library\Crypt;
use Webim\Library\File;
use Webim\Library\Flash;
use Webim\Library\Language;
use Webim\Library\Str;
use Webim\View\Manager as View;

class PassException extends Exception {
}

class StopException extends Exception {
}

class App {

  /**
   * Instance
   *
   * @var App
   */
  protected static $instance;

  /**
   * Session handler
   *
   * @var Http\Session
   */
  public $session;

  /**
   * Flash messages
   *
   * @var Library\Flash
   */
  public $flash;

  /**
   * Request handler
   *
   * @var Http\Request
   */
  public $request;

  /**
   * Response handler
   *
   * @var Http\Response
   */
  public $response;

  /**
   * Default settings
   *
   * @var array
   */
  protected $defaults = array(
    'mode' => 'development',
    'encryption_key' => 'O9s_lWeIn7cOL0M]S6Xg4aR^GwovA&UN'
  );

  /**
   * Has the app response been sent to the client?
   *
   * @var bool
   */
  protected $responded = false;

  /**
   * Application hooks
   *
   * @var array
   */
  protected $hooks = array(
    'before' => array(array()),
    'after' => array(array()),
    'notFound' => array(array())
  );

  /**
   * Router handler
   *
   * @var Router
   */
  protected $router;

  /**
   * Middleware functions
   *
   * @var array
   */
  protected $middleware;

  /**
   * Not found handler
   *
   * @var callable
   */
  protected $notFound;

  /**
   * Error handler
   *
   * @var callable
   */
  protected $error;

  /**
   * Not found and error template
   *
   * @var array[callable]
   */
  protected $template = array(
    'notFound' => null,
    'error' => null
  );

  /**
   * Constructor
   *
   * @param array $settings
   */
  public function __construct($settings = array()) {
    if (!static::$instance) {
      //Set file root and php extension
      File::setRoot(array_get($settings, 'root', '/'));
      File::setGlobalPHPFileExt(array_get($settings, 'ext', '.php'));

      //Configuration files
      $configFiles = File::in('config')
        ->fileIn('*' . File::getGlobalPHPFileExt())
        ->fileNotIn('index' . File::getGlobalPHPFileExt())
        ->files();

      //Override settings
      foreach ($settings as $key => $value) {
        if (isset($this->defaults[$key])) {
          $this->defaults[$key] = $value;
        }
      }

      //Configuration
      Config::init($this->defaults)->load($configFiles);

      $this->crypt = new Crypt(Config::get('encryption_key'));

      //Set session
      $this->session = Session::current();

      //Set flash messages
      $this->flash = new Flash($this->session);

      //Set request
      $this->request = Request::current();

      //Decrypt all cookies
      $this->request->cookie()->decrypt($this->crypt);

      //Set response
      $this->response = Response::create();

      //Set router
      $this->router = Router::make();

      //Set instance
      static::$instance = $this;
    }
  }

  /**
   * Init app
   *
   * @param array $settings
   *
   * @return static
   */
  public static function make($settings = array()) {
    return (static::$instance ? static::$instance : new static($settings));
  }

  /**
   * Convert errors into ErrorException objects
   *
   * This method catches PHP errors and converts them into \ErrorException objects;
   * these \ErrorException objects are then thrown and caught by Webim's
   * built-in or custom error handlers.
   *
   * @param int $errno The numeric type of the Error
   * @param string $errstr The error message
   * @param string $errfile The absolute path to the affected file
   * @param int $errline The line number of the error in the affected file
   *
   * @return bool
   *
   * @throws \ErrorException
   */
  public static function handleErrors($errno, $errstr = '', $errfile = '', $errline = null) {
    if (!($errno & error_reporting())) {
      return true;
    }

    throw new \ErrorException($errstr, $errno, 0, $errfile, $errline);
  }

  /**
   * Get the application
   *
   * @return null|App
   */
  public static function current() {
    return static::$instance ?: null;
  }

  /**
   * Start application with
   *
   * @param string|array $module
   * @param array|\Closure $options
   *
   * @return $this
   */
  public function with($module, $options = array()) {
    try {
      if ($module instanceof \Closure) {
        call_user_func(\Closure::bind($module, $this));
      } elseif (is_array($module)) {
        foreach ($module as $key => $name) {
          call_user_func(__METHOD__, $name, array_get($options, $key, array()));
        }
      } else {
        switch ($module) {
          case 'db':

            //Set database configuration
            DB::setConfig(Config::get('database', array()));

            //Start default connection
            DB::connection();

            break;
          case 'cache':

            $cache_dir = Config::get('cache.dir');

            if (!is_null($cache_dir)) {
              $cache_dir = File::path($cache_dir)->create();

              Config::set('cache.dir', $cache_dir);
            }

            //Set cache configuration
            Cache::setConfig(Config::get('cache', array()));

            if (DB::hasConnection()) {
              //Set database cache manager
              DB::setCacheManager(Cache::getManager());
            }

            //Set view path
            View::setCachePath(Config::get('cache.dir'));

            break;
          case 'lang':

            //Available
            $list = array();

            foreach (File::in('language')->folders() as $path => $folder) {
              $list[$folder->name] = File::in($path)
                ->fileIn('*' . File::getGlobalPHPFileExt())
                ->fileNotIn('index' . File::getGlobalPHPFileExt())
                ->allFiles();
            }

            if (!isset($list[$lang = $this->request->segment(1)])) {
              $browserLang = substr(array_get($this->request->getLanguages(), 0), 0, 2);
              $lang = array_get($options, 'lang', Config::get('default.language'));

              if (!strlen($lang) || !isset($list[$lang])) {
                if (isset($list[$browserLang])) {
                  $lang = $browserLang;
                } else {
                  $lang = current(array_keys($list));
                }
              }
            }

            //Set languages
            Language::init($lang)->vars($list);

            //Set locale
            setlocale(LC_TIME, Language::get('locale', 'tr_TR'));
            date_default_timezone_set(Language::get('time_zone', 'Europe/Istanbul'));

            //Set characters
            Str::setChars(Language::getVars('chars'));

            break;
        }
      }
    } catch (\Exception $e) {
      //Respond as error
      $this->respond($this->callErrorHandler($e));
      exit(0);
    }

    return $this;
  }

  /**
   * Respond result
   *
   * @param string $content
   */
  protected function respond($content) {
    if (!$this->responded) {
      $this->responded = true;

      //Save flash messages
      $this->flash->save();
      $this->cleanBuffer();

      //Set content and send
      $this->response->setContent($content, true);
      $this->response->send();
    }
  }

  /**
   * Clean current output buffer
   */
  protected function cleanBuffer() {
    if (ob_get_level() !== 0) {
      ob_clean();
    }
  }

  /**
   * Call error handler
   *
   * This will invoke the custom or default error handler
   * and RETURN its output.
   *
   * @param \Exception|null $argument
   *
   * @return string
   */
  protected function callErrorHandler($argument = null) {
    $this->response->setStatus(500);

    ob_start();

    if ($this->error && is_callable($this->error)) {
      call_user_func_array(array($this, 'error'), array($argument));
    } else {
      call_user_func_array(array($this, 'defaultError'), array($argument));
    }

    return ob_get_clean();
  }

  /**
   * Add route without HTTP method
   *
   * @return Http\Route
   */
  public function map() {
    return $this->mapRoute(func_get_args());
  }

  /**
   * Add GET|POST|PUT|PATCH|DELETE route
   *
   * Adds a new route to the router with associated callable. This
   * route will only be invoked when the HTTP request's method matches
   * this route's method.
   *
   * ARGUMENTS:
   *
   * First:       string  The URL pattern (REQUIRED)
   * In-Between:  mixed   Anything that returns TRUE for `is_callable` (OPTIONAL)
   * Last:        mixed   Anything that returns TRUE for `is_callable` (REQUIRED)
   *
   * The first argument is required and must always be the
   * route pattern (ie. '/books/:id').
   * route pattern (ie. '/books(/:id#[a-z0-9]+#)').
   *
   * The last argument is required and must always be the callable object
   * to be invoked when the route matches an HTTP request.
   *
   * You may also provide an unlimited number of in-between arguments;
   * each interior argument must be callable and will be invoked in the
   * order specified before the route's callable is invoked.
   *
   * USAGE:
   *
   * App::get('/foo'[, middleware, middleware, ...], callable);
   *
   * @param array
   *
   * @return Http\Route
   */
  protected function mapRoute($args) {
    $pattern = array_shift($args);

    $settings = array();

    foreach ($args as $key => $arg) {
      if (is_array($arg)) {
        $settings = array_merge($settings, $arg);
        array_forget($args, $key);
      }
    }

    $callable = array_pop($args);

    $route = new Route($pattern, $callable, $settings, true);
    $this->router->map($route);

    if (count($args) > 0) {
      $route->setMiddleware($args);
    }

    return $route;
  }

  /**
   * Add GET route
   *
   * @return $this
   */
  public function get() {
    $this->mapRoute(func_get_args())->via(Request::METHOD_GET, Request::METHOD_HEAD);

    return $this;
  }

  /**
   * Add POST route
   *
   * @return $this
   */
  public function post() {
    $this->mapRoute(func_get_args())->via(Request::METHOD_POST);

    return $this;
  }

  /**
   * Add PUT route
   *
   * @return $this
   */
  public function put() {
    $this->mapRoute(func_get_args())->via(Request::METHOD_PUT);

    return $this;
  }

  /**
   * Add PATCH route
   *
   * @return $this
   */
  public function patch() {
    $this->mapRoute(func_get_args())->via(Request::METHOD_PATCH);

    return $this;
  }

  /**
   * Add DELETE route
   *
   * @return $this
   */
  public function delete() {
    $this->mapRoute(func_get_args())->via(Request::METHOD_DELETE);

    return $this;
  }

  /**
   * Add OPTIONS route
   *
   * @return $this
   */
  public function options() {
    $this->mapRoute(func_get_args())->via(Request::METHOD_OPTIONS);

    return $this;
  }

  /**
   * Route Groups
   *
   * This method accepts a route pattern and a callback. All route
   * declarations in the callback will be prepended by the group(s)
   * that it is in.
   *
   * Accepts the same parameters as a standard route so:
   * (pattern, middleware1, middleware2, ..., $callback)
   *
   * @return $this
   */
  public function group() {
    $args = func_get_args();
    $pattern = array_shift($args);
    $callable = array_pop($args);
    $this->router->pushGroup($pattern, $args);

    if (is_callable($callable)) {
      call_user_func(\Closure::bind($callable, $this));
    }

    $this->router->popGroup();

    return $this;
  }

  /**
   * Add route for any HTTP method
   *
   * @return $this
   */
  public function any() {
    $this->mapRoute(func_get_args())->via('ANY');

    return $this;
  }

  /**
   * Run the application
   */
  public function run() {
    set_error_handler(array('Webim\App', 'handleErrors'));

    ob_start();

    // Invoke middleware and application stack
    try {
      $content = $this->call();
    } catch (\Exception $e) {
      $content = $this->callErrorHandler($e);
    }

    if (ob_get_length()) ob_end_clean();

    $this->respond($content);

    restore_error_handler();
  }

  /**
   * Dispatch request and build response
   *
   * This method will route the provided Request object against all available
   * application routes. The provided response will reflect the status, header, and body
   * set by the invoked matching route.
   *
   * The provided Request and Response objects are updated by reference. There is no
   * value returned by this method.
   *
   * @return string
   */
  public function call() {
    //Default not dispatched
    $dispatched = false;

    //Content container
    $content = '';

    //Apply hook before check
    $this->applyHook('before');

    $matchedRoutes = $this->router->getMatchedRoutes($this->request->getMethod(), $this->request->getPathInfo());

    foreach ($matchedRoutes as $route) {
      if (($dispatched = $route->dispatch($this)) !== false) {
        $content = $dispatched;
        break;
      }
    }

    if (!$dispatched) {
      //Apply hook not found
      $this->applyHook('notFound');

      //Set content as not found
      $content = $this->notFound();
    }

    //Apply hook after check
    $this->applyHook('after');

    return $content;
  }

  /**
   * Invoke hook
   *
   * @param string $name The hook name
   * @param mixed $hookArg (Optional) Argument for hooked functions
   */
  public function applyHook($name, $hookArg = null) {
    if (!isset($this->hooks[$name])) {
      $this->hooks[$name] = array(array());
    }

    if (!empty($this->hooks[$name])) {
      // Sort by priority, low to high, if there's more than one priority
      if (count($this->hooks[$name]) > 1) {
        ksort($this->hooks[$name]);
      }

      foreach ($this->hooks[$name] as $priority) {
        if (!empty($priority)) {
          foreach ($priority as $callable) {
            call_user_func(\Closure::bind($callable, $this), $hookArg);
          }
        }
      }
    }
  }

  /**
   * Not Found Handler
   *
   * This method defines or invokes the application-wide Not Found handler.
   * There are two contexts in which this method may be invoked:
   *
   * 1. When declaring the handler:
   *
   * If the $callable parameter is not null and is callable, this
   * method will register the callable to be invoked when no
   * routes match the current HTTP request. It WILL NOT invoke the callable.
   *
   * 2. When invoking the handler:
   *
   * If the $callable parameter is null, Webim assumes you want
   * to invoke an already-registered handler. If the handler has been
   * registered and is callable, it is invoked and sends a 404 HTTP Response
   * whose body is the output of the Not Found handler.
   *
   * @return  mixed
   */
  public function notFound() {
    $this->response->setStatus(404);

    ob_start();

    if ($this->notFound && is_callable($this->notFound)) {
      call_user_func(array($this, 'notFound'));
    } else {
      call_user_func(array($this, 'defaultNotFound'));
    }

    return ob_get_clean();
  }

  /**
   * Assign hook
   *
   * @param string $name The hook name
   * @param mixed $callable A callable object
   * @param int $priority The hook priority; 0 = high, 10 = low
   *
   * @return $this
   */
  public function addHook($name, $callable, $priority = 10) {
    if (!isset($this->hooks[$name])) {
      $this->hooks[$name] = array(array());
    }

    if (is_callable($callable)) {
      $this->hooks[$name][(int)$priority][] = $callable;
    }

    return $this;
  }

  /**
   * Get hook listeners
   *
   * Return an array of registered hooks. If `$name` is a valid
   * hook name, only the listeners attached to that hook are returned.
   * Else, all listeners are returned as an associative array whose
   * keys are hook names and whose values are arrays of listeners.
   *
   * @param string $name A hook name (Optional)
   *
   * @return array|null
   */
  public function getHooks($name = null) {
    if (!is_null($name)) {
      return isset($this->hooks[(string)$name]) ? $this->hooks[(string)$name] : null;
    } else {
      return $this->hooks;
    }
  }

  /**
   * Clear hook listeners
   *
   * Clear all listeners for all hooks. If `$name` is
   * a valid hook name, only the listeners attached
   * to that hook will be cleared.
   *
   * @param string $name A hook name (Optional)
   *
   * @return $this
   */
  public function clearHooks($name = null) {
    if (!is_null($name) && isset($this->hooks[(string)$name])) {
      $this->hooks[(string)$name] = array(array());
    } else {
      foreach ($this->hooks as $key => $value) {
        $this->hooks[$key] = array(array());
      }
    }

    return $this;
  }

  /**
   * Redirect
   *
   * This method immediately redirects to a new URL. By default,
   * this issues a 302 Found response; this is considered the default
   * generic redirect response. You may also specify another valid
   * 3xx status code if you want. This method will automatically set the
   * HTTP Location header for you using the URL parameter.
   *
   * @param string $url The destination URL
   * @param int $status The HTTP redirect status code (optional)
   *
   * @throws StopException
   */
  public function redirect($url, $status = 302) {
    Redirect::create($url, $status)->send();
    $this->halt($status);
  }

  /**
   * Halt
   *
   * Stop the application and immediately send the response with a
   * specific status and body to the HTTP client. This may send any
   * type of response: info, success, redirect, client error, or server error.
   * If you need to render a template AND customize the response status,
   * use the application's `render()` method instead.
   *
   * @param int $status The HTTP response status
   * @param string $message The HTTP response body
   *
   * @throws StopException
   */
  protected function halt($status, $message = '') {
    $this->cleanBuffer();
    $this->response->setStatus($status);
    $this->response->setContent($message, true);
    $this->stop();
  }

  /**
   * Stop
   *
   * The thrown exception will be caught in application's `call()` method
   * and the response will be sent as is to the HTTP client.
   *
   * @throws StopException
   */
  public function stop() {
    throw new StopException();
  }

  /**
   * Flash message
   *
   * @param mixed $key
   * @param null|string $value
   *
   * @return $this
   */
  public function flash($key, $value = null) {
    if (is_array($key)) {
      foreach ($key as $k => $v) {
        $this->flash->next($k, $v);
      }
    } else {
      $this->flash->next($key, $value);
    }

    $this->flash->save();

    return $this;
  }

  /**
   * Get the URL for a named route
   *
   * @param string $name The route name
   * @param array $params Associative array of URL parameters and replacement values
   *
   * @return string
   */
  public function urlFor($name, $params = array()) {
    return $this->request->root() . $this->router->urlFor($name, $params);
  }

  /**
   * Error Handler
   *
   * This method defines or invokes the application-wide Error handler.
   * There are two contexts in which this method may be invoked:
   *
   * 1. When declaring the handler:
   *
   * If the $argument parameter is callable, this
   * method will register the callable to be invoked when an uncaught
   * Exception is detected, or when otherwise explicitly invoked.
   * The handler WILL NOT be invoked in this context.
   *
   * 2. When invoking the handler:
   *
   * If the $argument parameter is not callable, Webim assumes you want
   * to invoke an already-registered handler. If the handler has been
   * registered and is callable, it is invoked and passed the caught Exception
   * as its one and only argument. The error handler's output is captured
   * into an output buffer and sent as the body of a 500 HTTP Response.
   *
   * @param mixed $argument A callable or an exception
   *
   * @throws StopException
   */
  public function error($argument = null) {
    if (is_callable($argument)) {
      //Register error handler
      $this->error = function () use ($argument) {
        return $argument;
      };
    } else {
      //Invoke error handler
      $this->response->setContent($this->callErrorHandler($argument), true);
      $this->stop();
    }
  }

  /**
   * Set not found template
   *
   * @param Closure $template
   *
   * @return $this
   */
  public function setNotFoundTemplate(Closure $template) {
    return $this->setTemplate('notFound', $template);
  }

  /**
   * Set template
   *
   * @param string $type
   * @param Closure $template
   *
   * @return $this
   */
  protected function setTemplate($type, Closure $template) {
    $this->template[$type] = $template;

    return $this;
  }

  /**
   * Set error template
   *
   * @param Closure $template
   *
   * @return $this
   */
  public function setErrorTemplate(Closure $template) {
    return $this->setTemplate('error', $template);
  }

  /**
   * Pass
   *
   * The thrown exception is caught in the application's `call()` method causing
   * the router's current iteration to stop and continue to the subsequent route if available.
   * If no subsequent matching routes are found, a 404 response will be sent to the client.
   *
   * @throws PassException
   */
  public function pass() {
    $this->cleanBuffer();
    throw new PassException();
  }

  /**
   * Default Not Found handler
   */
  protected function defaultNotFound() {
    $title = '404 Page Not Found';
    $body = '<p>The page you are looking for could not be found. '
      . 'Check the address bar to ensure your URL is spelled correctly. '
      . 'If all else fails, you can visit our home page at the link below.</p>'
      . '<a href="' . url('/') . '">Visit the Home Page</a>';

    if ($this->template['notFound'] && is_callable($this->template['notFound'])) {
      echo call_user_func($this->template['notFound'], $title, $body);
    } else {
      echo $this->generateTemplateMarkup($title, $body);
    }
  }

  /**
   * Generate diagnostic template markup
   *
   * This method accepts a title and body content to generate an HTML document layout.
   *
   * @param string $title The title of the HTML template
   * @param string $body The body content of the HTML template
   *
   * @return string
   */
  protected function generateTemplateMarkup($title, $body) {
    $html = "<!DOCTYPE html>\n"
      . "<html>\n"
      . "<head>\n"
      . "<title>%s</title>\n"
      . "<meta charset=\"utf-8\" />\n"
      . "<style>\n"
      . "body{ margin:0; padding:30px; font:12px/1.5 Helvetica,Arial,Verdana,sans-serif; }\n"
      . "h1{ margin:0; font-size:48px; font-weight:normal; line-height:48px; }\n"
      . "strong{ display:inline-block; width:65px; }\n"
      . "</style>\n"
      . "</head>\n"
      . "<body>\n"
      . "<h1>%s</h1>\n"
      . "%s\n"
      . "</body>\n"
      . "</html>";

    return sprintf($html, $title, $title, $body);
  }

  /**
   * Default Error handler
   *
   * @param Exception|string $e
   */
  protected function defaultError($e) {
    //Error title
    $title = 'Web-IM Application Error';

    if (Config::get('mode') === 'development') {
      if ($e instanceof Exception) {
        $code = $e->getCode();
        $message = $e->getMessage();
        $file = $e->getFile();
        $line = $e->getLine();
        $trace = str_replace(array(
          '#', '\n'
        ), array(
          '<div>#', '</div>'
        ), $e->getTraceAsString());

        $body = '<p>The application could not run because of the following error:</p>';
        $body .= '<h2>Details</h2>';
        $body .= sprintf('<div><strong>Type:</strong> %s</div>', get_class($e));

        if ($code) {
          $body .= sprintf('<div><strong>Code:</strong> %s</div>', $code);
        }

        if ($message) {
          $body .= sprintf('<div><strong>Message:</strong> %s</div>', $message);
        }

        if ($file) {
          $body .= sprintf('<div><strong>File:</strong> %s</div>', $file);
        }

        if ($line) {
          $body .= sprintf('<div><strong>Line:</strong> %s</div>', $line);
        }

        if ($trace) {
          $body .= '<h2>Trace</h2>';
          $body .= sprintf('<pre>%s</pre>', $trace);
        }
      } else {
        $body = sprintf('<p>%s</p>', $e);
      }
    } else {
      $body = '<p>A website error has occurred. The website administrator has been notified '
        . 'of the issue. Sorry for the temporary inconvenience.</p>';
    }

    if ($this->template['error'] && is_callable($this->template['error'])) {
      echo call_user_func($this->template['error'], $title, $body);
    } else {
      echo $this->generateTemplateMarkup($title, $body);
    }
  }

}