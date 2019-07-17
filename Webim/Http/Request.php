<?php
/**
 * @author Orhan POLAT
 */

namespace Webim\Http;

use Webim\Library\Collection;

class Request {
  const METHOD_HEAD = 'HEAD';
  const METHOD_GET = 'GET';
  const METHOD_POST = 'POST';
  const METHOD_PUT = 'PUT';
  const METHOD_PATCH = 'PATCH';
  const METHOD_DELETE = 'DELETE';
  const METHOD_OPTIONS = 'OPTIONS';
  const METHOD_OVERRIDE = '_METHOD';
  /**
   * Request formats
   *
   * @var array
   */
  public static $formats = array(
    'html' => array('text/html', 'application/xhtml+xml'),
    'txt' => array('text/plain'),
    'js' => array('application/javascript', 'application/x-javascript', 'text/javascript'),
    'css' => array('text/css'),
    'json' => array('application/json', 'application/x-json'),
    'xml' => array('text/xml', 'application/xml', 'application/x-xml'),
    'rdf' => array('application/rdf+xml'),
    'atom' => array('application/atom+xml'),
    'rss' => array('application/rss+xml'),
  );
  /**
   * Instance
   *
   * @var Request
   */
  protected static $instance;
  /**
   * Override method
   *
   * @var bool
   */
  protected static $httpMethodParameterOverride = false;
  /**
   * $_GET
   *
   * @var Collection
   */
  protected $query;
  /**
   * $_POST
   *
   * @var Collection
   */
  protected $request;
  /**
   * $_FILES
   *
   * @var Collection
   */
  protected $files;
  /**
   * $_SERVER
   *
   * @var Collection
   */
  protected $server;
  /**
   * Header vars from $_SERVER
   *
   * @var Collection
   */
  protected $headers;
  /**
   * $_COOKIE
   *
   * @var Cookie
   */
  protected $cookie;
  /**
   * Request body
   *
   * @var string
   */
  protected $content;
  /**
   * Request path info
   *
   * @var string
   */
  protected $pathInfo;
  /**
   * Request uri
   *
   * @var string
   */
  protected $requestUri;
  /**
   * Request base url
   *
   * @var string
   */
  protected $baseUrl;
  /**
   * Request base path
   *
   * @var string
   */
  protected $basePath;
  /**
   * Request method (GET, POST, ...)
   * @var string
   */
  protected $method;

  /**
   * Constructor
   */
  public function __construct() {
    if (!static::$instance) {
      $this->query = new Collection($_GET);
      $this->request = new Collection($_POST);
      $this->files = new Collection($_FILES);
      $this->server = new Collection($_SERVER);
      $this->headers = new Collection($this->parseHeaders());
      $this->cookie = new Cookie($this->headers);

      static::$instance = $this;
    }
  }

  /**
   * Parse headers from server vars
   *
   * @return array
   */
  private function parseHeaders() {
    $headers = array();
    $specials = array(
      'CONTENT_TYPE',
      'CONTENT_MD5',
      'CONTENT_LENGTH',
      'PHP_AUTH_USER',
      'PHP_AUTH_PW',
      'PHP_AUTH_DIGEST',
      'AUTH_TYPE'
    );

    foreach ($_SERVER as $key => $value) {
      if ((strpos($key, 'HTTP_') === 0) || in_array($key, $specials)) {
        $key = str_replace(' ', '-', ucwords(strtolower(str_replace(array('_', '-'), ' ', substr($key, 5)))));

        $headers[$key] = $value;
      }
    }

    return $headers;
  }

  /**
   * Constructor
   *
   * @return Request
   */
  public static function current() {
    return (static::$instance ? static::$instance : new static());
  }

  /**
   * Enables support for the _method request parameter to determine the intended HTTP method.
   *
   * Be warned that enabling this feature might lead to CSRF issues in your code.
   * Check that you are using CSRF tokens when required.
   *
   * The HTTP method can only be overridden when the real HTTP method is POST.
   */
  public static function enableHttpMethodParameterOverride() {
    static::$httpMethodParameterOverride = true;
  }

  /**
   * Checks whether support for the _method request parameter is enabled.
   *
   * @return bool  True when the _method request parameter is enabled, false otherwise
   */
  public static function getHttpMethodParameterOverride() {
    return static::$httpMethodParameterOverride;
  }

  /**
   * Get a subset of the items from the input data.
   *
   * @param array $keys
   *
   * @return array
   */
  public function only($keys) {
    $keys = is_array($keys) ? $keys : func_get_args();

    $results = [];

    $input = $this->all();

    foreach ($keys as $key) {
      array_set($results, $key, array_get($input, $key, null));
    }

    return $results;
  }

  /**
   * Get all of the input and files for the request.
   *
   * @return array
   */
  public function all() {
    return array_replace_recursive($this->input(), $this->files->all());
  }

  /**
   * Retrieve an input item from the request.
   *
   * @param string $key
   * @param mixed $default
   *
   * @return string
   */
  public function input($key = null, $default = null) {
    $input = $this->getInputSource()->all() + $this->query->all();

    return array_get($input, $key, $default);
  }

  /**
   * Get the input source for the request.
   *
   * @return Webim\Library\Collection
   */
  protected function getInputSource() {
    if ($this->isJson()) return $this->json();

    return ($this->isGet() ? $this->query : $this->request);
  }

  /**
   * Determine if the request is sending JSON.
   *
   * @return bool
   */
  public function isJson() {
    return str_contains($this->header('Content-Type'), '/json');
  }

  /**
   * Header vars
   *
   * @param null|string $key
   * @param null|string $default
   *
   * @return mixed
   */
  public function header($key = null, $default = null) {
    return $this->retrieveItem('headers', $key, $default);
  }

  /**
   * Retrieve a parameter item from a given source.
   *
   * @param string $source
   * @param null|string $key
   * @param null|mixed $default
   *
   * @return string
   */
  protected function retrieveItem($source, $key = null, $default = null) {
    if (is_null($key)) {
      return $this->$source;
    } else {
      return $this->$source->get($key, $default);
    }
  }

  /**
   * Get the JSON payload for the request.
   *
   * @param string $key
   * @param mixed $default
   *
   * @return mixed
   */
  public function json($key = null, $default = null) {
    $json = new Collection((array)json_decode($this->getContent(), true));

    if (is_null($key)) return $json;

    return $json->get($key, $default);
  }

  /**
   * Returns the request body content.
   *
   * @param bool $asResource If true, a resource will be returned
   *
   * @return string|resource The request body content or a resource to read the body stream.
   *
   * @throws \LogicException
   */
  public function getContent($asResource = false) {
    if ((false === $this->content) || (true === ($asResource && (null !== $this->content)))) {
      throw new \LogicException('getContent() can only be called once when using the resource return type.');
    }

    if (true === $asResource) {
      $this->content = null;

      return fopen('php://input', 'rb');
    }

    if (null === $this->content) {
      $this->content = file_get_contents('php://input');
    }

    return $this->content;
  }

  /**
   * Is this a GET request?
   *
   * @return bool
   */
  public function isGet() {
    return $this->isMethod(static::METHOD_GET);
  }

  /**
   * Checks if the request method is of specified type.
   *
   * @param string $method Uppercase request method (GET, POST etc).
   *
   * @return bool
   */
  public function isMethod($method) {
    return ($this->getMethod() === strtoupper($method));
  }

  /**
   * Gets the request "intended" method.
   *
   * If the X-HTTP-Method-Override header is set, and if the method is a POST,
   * then it is used to determine the "real" intended HTTP method.
   *
   * The _method request parameter can also be used to determine the HTTP method,
   * but only if enableHttpMethodParameterOverride() has been called.
   *
   * The method is always an uppercased string.
   *
   * @return string The request method
   *
   * @see getRealMethod
   */
  public function getMethod() {
    if (null === $this->method) {
      $this->method = strtoupper($this->server->get('REQUEST_METHOD', 'GET'));

      if ('POST' === $this->method) {
        if ($method = $this->headers->get('X-Http-Method-Override')) {
          $this->method = strtoupper($method);
        } elseif (self::$httpMethodParameterOverride) {
          $this->method = strtoupper($this->request->get(static::METHOD_OVERRIDE, $this->query->get(static::METHOD_OVERRIDE, 'POST')));
        }
      }
    }

    return $this->method;
  }

  /**
   * Sets the request method.
   *
   * @param string $method
   */
  public function setMethod($method) {
    $this->method = null;
    $this->server->set('REQUEST_METHOD', $method);
  }

  /**
   * Get all of the input except for a specified array of items.
   *
   * @param array $keys
   *
   * @return array
   */
  public function except($keys) {
    $keys = is_array($keys) ? $keys : func_get_args();

    $results = $this->all();

    array_forget($results, $keys);

    return $results;
  }

  /**
   * File input
   *
   * @param null|string $key
   * @param null|string $default
   *
   * @return mixed
   */
  public function file($key = null, $default = null) {
    //File
    $file = array();

    foreach ($this->files() as $input => $files) {
      $file[$input] = array_first($files);
    }

    return array_get($file, $key, $default);
  }

  /**
   * Files input array
   *
   * @param null|string $key
   * @param null|string $default
   *
   * @return mixed
   */
  public function files($key = null, $default = null) {
    //Fix files list
    $files = array();

    foreach ($this->retrieveItem('files')->all() as $input => $list) {
      foreach ($list as $name => $value) {
        if (is_array($value)) {
          foreach ($value as $num => $item) {
            $files[$input][$num][$name] = $item;
          }
        } else {
          $files[$input][0][$name] = $value;
        }
      }
    }

    //Filter content existence
    foreach ($files as $input => $list) {
      $files[$input] = array_filter($list, function ($file) {
        return isset($file['tmp_name']) && strlen($file['tmp_name']);
      });
    }

    //Filter file count
    $files = array_filter($files, function ($list) {
      return count($list) > 0;
    });

    return array_get($files, $key, $default);
  }

  /**
   * Has input file
   *
   * @param null|string $key
   *
   * @return bool
   */
  public function hasFile($key = null) {
    $has = $this->files($key);

    return !is_null($has) && count($has);
  }

  /**
   * Server vars
   *
   * @param null|string $key
   * @param null|string $default
   *
   * @return mixed
   */
  public function server($key = null, $default = null) {
    return $this->retrieveItem('server', $key, $default);
  }

  /**
   * Cookie vars
   *
   * @param null|string $key
   * @param null|string $default
   *
   * @return mixed
   */
  public function cookie($key = null, $default = null) {
    return $this->retrieveItem('cookie', $key, $default);
  }

  /**
   * Get the root URL for the application.
   *
   * @return string
   */
  public function root() {
    return rtrim($this->getSchemeAndHttpHost() . $this->getBaseUrl(), '/');
  }

  /**
   * Gets the scheme and HTTP host.
   *
   * If the URL was called with basic authentication, the user
   * and the password are not added to the generated string.
   *
   * @return string The scheme and HTTP host
   */
  public function getSchemeAndHttpHost() {
    return $this->getScheme() . '://' . $this->getHttpHost();
  }

  /**
   * Gets the request's scheme.
   *
   * @return string
   */
  public function getScheme() {
    return $this->isSecure() ? 'https' : 'http';
  }

  /**
   * Checks whether the request is secure or not.
   *
   * @return bool
   */
  public function isSecure() {
    $https = $this->server->get('HTTPS');

    return (!empty($https) && ('off' !== strtolower($https)));
  }

  /**
   * Returns the HTTP host being requested.
   *
   * The port name will be appended to the host if it's non-standard.
   *
   * @return string
   */
  public function getHttpHost() {
    $scheme = $this->getScheme();
    $port = $this->getPort();

    if ((('http' == $scheme) && ($port == 80)) || (('https' == $scheme) && ($port == 443))) {
      return $this->getHost();
    }

    return $this->getHost() . ':' . $port;
  }

  /**
   * Returns the port on which the request is made.
   *
   * @return string
   */
  public function getPort() {
    if ($host = $this->headers->get('Host')) {
      if ($host[0] === '[') {
        $pos = strpos($host, ':', strrpos($host, ']'));
      } else {
        $pos = strrpos($host, ':');
      }

      if (false !== $pos) {
        return intval(substr($host, $pos + 1));
      }

      return (('https' === $this->getScheme()) ? 443 : 80);
    }

    return $this->server->get('SERVER_PORT');
  }

  /**
   * Returns the host name.
   *
   * This method can read the client port from the "X-Forwarded-Host" header
   * when trusted proxies were set via "setTrustedProxies()".
   *
   * The "X-Forwarded-Host" header must contain the client host name.
   *
   * If your reverse proxy uses a different header name than "X-Forwarded-Host",
   * configure it via "setTrustedHeaderName()" with the "client-host" key.
   *
   * @return string
   *
   * @throws \UnexpectedValueException when the host name is invalid
   */
  public function getHost() {
    if (!$host = $this->headers->get('Host')) {
      if (!$host = $this->server->get('SERVER_NAME')) {
        $host = $this->server->get('SERVER_ADDR', '');
      }
    }

    // trim and remove port number from host
    // host is lowercase as per RFC 952/2181
    $host = strtolower(preg_replace('/:\d+$/', '', trim($host)));

    // as the host can come from the user (HTTP_HOST and depending on the configuration, SERVER_NAME too can come from the user)
    // check that it does not contain forbidden characters (see RFC 952 and RFC 2181)
    if ($host && !preg_match('/^\[?(?:[a-zA-Z0-9-:\]_]+\.?)+$/', $host)) {
      throw new \UnexpectedValueException(sprintf('Invalid Host "%s"', $host));
    }

    return $host;
  }

  /**
   * Returns the root URL from which this request is executed.
   *
   * The base URL never ends with a /.
   *
   * This is similar to getBasePath(), except that it also includes the
   * script filename (e.g. index.php) if one exists.
   *
   * @return string The raw URL (i.e. not urldecoded)
   */
  public function getBaseUrl() {
    if (null === $this->baseUrl) {
      $this->baseUrl = $this->prepareBaseUrl();
    }

    return $this->baseUrl;
  }

  /**
   * Prepares the base URL.
   *
   * @return string
   */
  private function prepareBaseUrl() {
    $filename = basename($this->server->get('SCRIPT_FILENAME'));

    if (basename($this->server->get('SCRIPT_NAME')) === $filename) {
      $baseUrl = $this->server->get('SCRIPT_NAME');
    } elseif (basename($this->server->get('PHP_SELF')) === $filename) {
      $baseUrl = $this->server->get('PHP_SELF');
    } elseif (basename($this->server->get('ORIG_SCRIPT_NAME')) === $filename) {
      $baseUrl = $this->server->get('ORIG_SCRIPT_NAME'); // 1and1 shared hosting compatibility
    } else {
      // Backtrack up the script_filename to find the portion matching
      // php_self
      $path = $this->server->get('PHP_SELF', '');
      $file = $this->server->get('SCRIPT_FILENAME', '');

      $segs = array_reverse(explode('/', trim($file, '/')));
      $index = 0;
      $last = count($segs);
      $baseUrl = '';
      do {
        $seg = $segs[$index];
        $baseUrl = '/' . $seg . $baseUrl;
        ++$index;
      } while ($last > $index && (false !== $pos = strpos($path, $baseUrl)) && 0 != $pos);
    }

    // Does the baseUrl have anything in common with the request_uri?
    $requestUri = $this->getRequestUri();

    if ($baseUrl && (false !== ($prefix = $this->getUrlencodedPrefix($requestUri, $baseUrl)))) {
      // full $baseUrl matches
      return $prefix;
    }

    if ($baseUrl && (false !== ($prefix = $this->getUrlencodedPrefix($requestUri, dirname($baseUrl))))) {
      // directory portion of $baseUrl matches
      return rtrim($prefix, '/');
    }

    $truncatedRequestUri = $requestUri;

    if (false !== $pos = strpos($requestUri, '?')) {
      $truncatedRequestUri = substr($requestUri, 0, $pos);
    }

    $basename = basename($baseUrl);

    if (empty($basename) || !strpos(rawurldecode($truncatedRequestUri), $basename)) {
      // no match whatsoever; set it blank
      return '';
    }

    // If using mod_rewrite or ISAPI_Rewrite strip the script filename
    // out of baseUrl. $pos !== 0 makes sure it is not matching a value
    // from PATH_INFO or QUERY_STRING
    if ((strlen($requestUri) >= strlen($baseUrl)) && (false !== ($pos = strpos($requestUri, $baseUrl))) && ($pos !== 0)) {
      $baseUrl = substr($requestUri, 0, $pos + strlen($baseUrl));
    }

    return rtrim($baseUrl, '/');
  }

  /**
   * Returns the requested URI.
   *
   * @return string The raw URI (i.e. not urldecoded)
   */
  public function getRequestUri() {
    if (null === $this->requestUri) {
      $this->requestUri = $this->prepareRequestUri();
    }

    return $this->requestUri;
  }

  /**
   * The following methods are derived from code of the Zend Framework (1.10dev - 2010-01-24)
   *
   * Code subject to the new BSD license (http://framework.zend.com/license/new-bsd).
   *
   * Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
   */
  private function prepareRequestUri() {
    $requestUri = '';

    if ($this->headers->has('X-Original-Url')) {
      // IIS with Microsoft Rewrite Module
      $requestUri = $this->headers->get('X-Original-Url');
      $this->headers->remove('X-Original-Url');
      $this->server->remove('HTTP_X_ORIGINAL_URL');
      $this->server->remove('UNENCODED_URL');
      $this->server->remove('IIS_WasUrlRewritten');
    } elseif ($this->headers->has('X-Rewrite-Url')) {
      // IIS with ISAPI_Rewrite
      $requestUri = $this->headers->get('X-Rewrite-Url');
      $this->headers->remove('X-Rewrite-Url');
    } elseif (($this->server->get('IIS_WasUrlRewritten') == '1') && ($this->server->get('UNENCODED_URL') != '')) {
      // IIS7 with URL Rewrite: make sure we get the unencoded URL (double slash problem)
      $requestUri = $this->server->get('UNENCODED_URL');
      $this->server->remove('UNENCODED_URL');
      $this->server->remove('IIS_WasUrlRewritten');
    } elseif ($this->server->has('REQUEST_URI')) {
      $requestUri = $this->server->get('REQUEST_URI');
      // HTTP proxy reqs setup request URI with scheme and host [and port] + the URL path, only use URL path
      $schemeAndHttpHost = $this->getSchemeAndHttpHost();

      if (strpos($requestUri, $schemeAndHttpHost) === 0) {
        $requestUri = substr($requestUri, strlen($schemeAndHttpHost));
      }
    } elseif ($this->server->has('ORIG_PATH_INFO')) {
      // IIS 5.0, PHP as CGI
      $requestUri = $this->server->get('ORIG_PATH_INFO');

      if ('' != $this->server->get('QUERY_STRING')) {
        $requestUri .= '?' . $this->server->get('QUERY_STRING');
      }

      $this->server->remove('ORIG_PATH_INFO');
    }

    // normalize the request URI to ease creating sub-requests from this request
    $this->server->set('REQUEST_URI', $requestUri);

    return $requestUri;
  }

  /**
   * Returns the prefix as encoded in the string when the string starts with
   * the given prefix, false otherwise.
   *
   * @param string $string The urlencoded string
   * @param string $prefix The prefix not encoded
   *
   * @return string|false The prefix as it is encoded in $string, or false
   */
  private function getUrlencodedPrefix($string, $prefix) {
    if (0 !== strpos(rawurldecode($string), $prefix)) {
      return false;
    }

    $len = strlen($prefix);

    if (preg_match('#^(%[[:xdigit:]]{2}|.){' . $len . '}#', $string, $match)) {
      return $match[0];
    }

    return false;
  }

  /**
   * Get the URI (without left slash) for the request.
   *
   * @return string
   */
  public function uri() {
    return ltrim(preg_replace('/\?.*/', '', $this->getRequestUri()), '/');
  }

  /**
   * Get the full URL for the request.
   *
   * @return string
   */
  public function fullUrl() {
    $query = $this->getQueryString();

    return ($query ? $this->url() . '?' . $query : $this->url());
  }

  /**
   * Generates the normalized query string for the Request.
   *
   * It builds a normalized query string, where keys/value pairs are alphabetized
   * and have consistent escaping.
   *
   * @return string|null A normalized query string for the Request
   */
  public function getQueryString() {
    $qs = $this->normalizeQueryString($this->server->get('QUERY_STRING'));

    return (('' === $qs) ? null : $qs);
  }

  /**
   * Normalizes a query string.
   *
   * It builds a normalized query string, where keys/value pairs are alphabetized,
   * have consistent escaping and unneeded delimiters are removed.
   *
   * @param string $qs Query string
   *
   * @return string A normalized query string for the Request
   */
  private function normalizeQueryString($qs) {
    if ('' == $qs) {
      return '';
    }

    $parts = array();
    $order = array();

    foreach (explode('&', $qs) as $param) {
      if (('' === $param) || ('=' === $param[0])) {
        // Ignore useless delimiters, e.g. "x=y&".
        // Also ignore pairs with empty key, even if there was a value, e.g. "=value", as such nameless values cannot be retrieved anyway.
        // PHP also does not include them when building _GET.
        continue;
      }

      $keyValuePair = explode('=', $param, 2);

      // GET parameters, that are submitted from a HTML form, encode spaces as "+" by default (as defined in enctype application/x-www-form-urlencoded).
      // PHP also converts "+" to spaces when filling the global _GET or when using the function parse_str. This is why we use urldecode and then normalize to
      // RFC 3986 with rawurlencode.
      $parts[] = isset($keyValuePair[1]) ?
        rawurlencode(urldecode($keyValuePair[0])) . '=' . rawurlencode(urldecode($keyValuePair[1])) :
        rawurlencode(urldecode($keyValuePair[0]));
      $order[] = urldecode($keyValuePair[0]);
    }

    array_multisort($order, SORT_ASC, $parts);

    return implode('&', $parts);
  }

  /**
   * Get the URL (no query string) for the request.
   *
   * @return string
   */
  public function url() {
    return rtrim(preg_replace('/\?.*/', '', $this->getPathInfo()), '/');
  }

  /**
   * Returns the path being requested relative to the executed script.
   *
   * The path info always starts with a /.
   *
   * Suppose this request is instantiated from /mysite on localhost:
   *
   *  * http://localhost/mysite              returns an empty string
   *  * http://localhost/mysite/about        returns '/about'
   *  * http://localhost/mysite/enco%20ded   returns '/enco%20ded'
   *  * http://localhost/mysite/about?var=1  returns '/about'
   *
   * @return string The raw path (i.e. not urldecoded)
   */
  public function getPathInfo() {
    if (null === $this->pathInfo) {
      $this->pathInfo = $this->preparePathInfo();
    }

    return $this->pathInfo;
  }

  /**
   * Prepares the path info.
   *
   * @return string path info
   */
  private function preparePathInfo() {
    $baseUrl = $this->getBaseUrl();

    if (null === ($requestUri = $this->getRequestUri())) {
      return '/';
    }

    $pathInfo = '/';

    // Remove the query string from REQUEST_URI
    if ($pos = strpos($requestUri, '?')) {
      $requestUri = substr($requestUri, 0, $pos);
    }

    if ((null !== $baseUrl) && (false === ($pathInfo = substr($requestUri, strlen($baseUrl))))) {
      // If substr() returns false then PATH_INFO is set to an empty string
      return '/';
    } elseif (null === $baseUrl) {
      return $requestUri;
    }

    return (string)$pathInfo;
  }

  /**
   * Get the current encoded path info for the request.
   *
   * @return string
   */
  public function decodedPath() {
    return rawurldecode($this->path());
  }

  /**
   * Get the current path info for the request.
   *
   * @return string
   */
  public function path() {
    $pattern = trim($this->getPathInfo(), '/');

    return (($pattern == '') ? '/' : $pattern);
  }

  /**
   * Get a segment from the URI (1 based index).
   *
   * @param string $index
   * @param mixed $default
   *
   * @return string
   */
  public function segment($index, $default = null) {
    return array_get($this->segments(), ($index - 1), $default);
  }

  /**
   * Get all of the segments for the request path.
   *
   * @return array
   */
  public function segments() {
    $segments = explode('/', $this->path());

    return array_values(array_filter($segments, function ($v) {
      return $v != '';
    }));
  }

  /**
   * Get method of the current request
   *
   * @return string
   */
  public function method() {
    return $this->getMethod();
  }

  /**
   * Determine if the current request URI matches a pattern.
   *
   * @param dynamic string
   *
   * @return bool
   */
  public function is() {
    foreach (func_get_args() as $pattern) {
      if (str_is($pattern, urldecode($this->path()))) {
        return true;
      }
    }

    return false;
  }

  /**
   * Determine if the request is the result of an AJAX call.
   *
   * @return bool
   */
  public function isAjax() {
    return ('XMLHttpRequest' == $this->headers->get('X-Requested-With'));
  }

  /**
   * Gets the "real" request method.
   *
   * @return string The request method
   *
   * @see getMethod
   */
  public function getRealMethod() {
    return strtoupper($this->server->get('REQUEST_METHOD', 'GET'));
  }

  /**
   * Checks whether the method is safe or not.
   *
   * @return bool
   */
  public function isMethodSafe() {
    return in_array($this->getMethod(), array('GET', 'HEAD'));
  }

  /**
   * Is this a POST request?
   *
   * @return bool
   */
  public function isPost() {
    return $this->isMethod(static::METHOD_POST);
  }

  /**
   * Is this a PUT request?
   *
   * @return bool
   */
  public function isPut() {
    return $this->isMethod(static::METHOD_PUT);
  }

  /**
   * Is this a PATCH request?
   *
   * @return bool
   */
  public function isPatch() {
    return $this->isMethod(static::METHOD_PATCH);
  }

  /**
   * Is this a DELETE request?
   *
   * @return bool
   */
  public function isDelete() {
    return $this->isMethod(static::METHOD_DELETE);
  }

  /**
   * Is this a HEAD request?
   *
   * @return bool
   */
  public function isHead() {
    return $this->isMethod(static::METHOD_HEAD);
  }

  /**
   * Is this a OPTIONS request?
   *
   * @return bool
   */
  public function isOptions() {
    return $this->isMethod(static::METHOD_OPTIONS);
  }

  /**
   * Gets the user info.
   *
   * @return string A user name and, optionally, scheme-specific information about how to gain authorization to access the server
   */
  public function getUserInfo() {
    $userinfo = $this->getUser();

    $pass = $this->getPassword();

    if ('' != $pass) {
      $userinfo .= ":$pass";
    }

    return $userinfo;
  }

  /**
   * Returns the user.
   *
   * @return string|null
   */
  public function getUser() {
    return $this->headers->get('Php-Auth-User');
  }

  /**
   * Returns the password.
   *
   * @return string|null
   */
  public function getPassword() {
    return $this->headers->get('Php-Auth-Pw');
  }

  /**
   * Returns the root path from which this request is executed.
   *
   * Suppose that an index.php file instantiates this request object:
   *
   *  * http://localhost/index.php         returns an empty string
   *  * http://localhost/index.php/page    returns an empty string
   *  * http://localhost/web/index.php     returns '/web'
   *  * http://localhost/we%20b/index.php  returns '/we%20b'
   *
   * @return string The raw path (i.e. not urldecoded)
   */
  public function getBasePath() {
    if (null === $this->basePath) {
      $this->basePath = $this->prepareBasePath();
    }

    return $this->basePath;
  }

  /**
   * Prepares the base path.
   *
   * @return string base path
   */
  private function prepareBasePath() {
    $filename = basename($this->server->get('SCRIPT_FILENAME'));
    $baseUrl = $this->getBaseUrl();

    if (empty($baseUrl)) {
      return '';
    }

    if (basename($baseUrl) === $filename) {
      $basePath = dirname($baseUrl);
    } else {
      $basePath = $baseUrl;
    }

    if ('\\' === DIRECTORY_SEPARATOR) {
      $basePath = str_replace('\\', '/', $basePath);
    }

    return rtrim($basePath, '/');
  }

  /**
   * Generates a normalized URL for the Request.
   *
   * @return string A normalized URL for the Request
   *
   * @see getQueryString()
   */
  public function getFullUrl() {
    if (null !== ($qs = $this->getQueryString())) {
      $qs = '?' . $qs;
    }

    return $this->getUrl() . $qs;
  }

  /**
   * Generates a normalized URL without query string for the Request.
   *
   * @return string A normalized URL
   */
  public function getUrl() {
    return $this->getSchemeAndHttpHost() . $this->getBaseUrl() . $this->getPathInfo();
  }

  /**
   * Generates a normalized URI for the given path.
   *
   * @param string $path A path to use instead of the current one
   *
   * @return string The normalized URI for the path
   */
  public function getUriForPath($path) {
    return $this->getSchemeAndHttpHost() . $this->getBaseUrl() . '/' . ltrim($path, '/');
  }

  /**
   * Gets a list of content types acceptable by the client browser
   *
   * @return array List of content types in preferable order
   */
  public function getContentTypes() {
    return $this->parseAcceptHeader($this->headers->get('Accept'));
  }

  /**
   * Parse accept header
   *
   * @param $string
   *
   * @return array
   */
  private function parseAcceptHeader($string) {
    $bits = preg_split('/\s*(?:;*("[^"]+");*|;*(\'[^\']+\');*|;+)\s*/', $string, 0, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);

    return explode(',', array_shift($bits));
  }

  /**
   * Gets a list of languages acceptable by the client browser.
   *
   * @return array Languages ordered in the user browser preferences
   */
  public function getLanguages() {
    return $this->parseAcceptHeader($this->headers->get('Accept-Language'));
  }

  /**
   * Gets a list of charsets acceptable by the client browser.
   *
   * @return array List of charsets in preferable order
   */
  public function getCharsets() {
    return $this->parseAcceptHeader($this->headers->get('Accept-Charset'));
  }

  /**
   * Gets a list of encodings acceptable by the client browser.
   *
   * @return array List of encodings in preferable order
   */
  public function getEncodings() {
    return $this->parseAcceptHeader($this->headers->get('Accept-Encoding'));
  }

  /**
   * Gets the mime type associated with the format.
   *
   * @param string $format The format
   *
   * @return string The associated mime type (null if not found)
   */
  public function getMimeType($format) {
    return isset(static::$formats[$format]) ? static::$formats[$format][0] : null;
  }

  /**
   * Returns the client IP address.
   *
   * This method can read the client IP address from the "X-Forwarded-For" header
   * when trusted proxies were set via "setTrustedProxies()". The "X-Forwarded-For"
   * header value is a comma+space separated list of IP addresses, the left-most
   * being the original client, and each successive proxy that passed the request
   * adding the IP address where it received the request from.
   *
   * If your reverse proxy uses a different header name than "X-Forwarded-For",
   * ("Client-Ip" for instance), configure it via "setTrustedHeaderName()" with
   * the "client-ip" key.
   *
   * @return string The client IP address
   *
   * @see getClientIps()
   * @see http://en.wikipedia.org/wiki/X-Forwarded-For
   */
  public function getClientIp() {
    $ipAddresses = $this->getClientIps();

    return $ipAddresses[0];
  }

  /**
   * Returns the client IP addresses.
   *
   * In the returned array the most trusted IP address is first, and the
   * least trusted one last. The "real" client IP address is the last one,
   * but this is also the least trusted one. Trusted proxies are stripped.
   *
   * Use this method carefully; you should use getClientIp() instead.
   *
   * @return array The client IP addresses
   *
   * @see getClientIp()
   */
  public function getClientIps() {
    $ip = $this->server->get('REMOTE_ADDR');

    $clientIps = array_map('trim', explode(',', $ip));

    return $clientIps;
  }

  /**
   * Returns the request as a string.
   *
   * @return string The request
   */
  public function __toString() {
    return
      sprintf('%s %s %s', $this->getMethod(), $this->getRequestUri(), $this->server->get('SERVER_PROTOCOL')) . "\r\n" .
      $this->headers . "\r\n" .
      $this->getContent();
  }

}