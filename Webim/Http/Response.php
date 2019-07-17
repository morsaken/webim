<?php
/**
 * @author Orhan POLAT
 */

namespace Webim\Http;

use Webim\Library\Crypt;

class Response {

  /**
   * Response instance
   *
   * @var Response
   */
  protected static $instance;
  /**
   * Response codes and associated messages
   * @var array
   */
  protected static $messages = array(
    //Informational 1xx
    100 => '100 Continue',
    101 => '101 Switching Protocols',
    102 => '102 Processing',
    //Successful 2xx
    200 => '200 OK',
    201 => '201 Created',
    202 => '202 Accepted',
    203 => '203 Non-Authoritative Information',
    204 => '204 No Content',
    205 => '205 Reset Content',
    206 => '206 Partial Content',
    207 => '207 Multi-Status',
    208 => '208 Already Reported',
    226 => '226 IM Used',
    //Redirection 3xx
    300 => '300 Multiple Choices',
    301 => '301 Moved Permanently',
    302 => '302 Found',
    303 => '303 See Other',
    304 => '304 Not Modified',
    305 => '305 Use Proxy',
    306 => '306 (Unused)',
    307 => '307 Temporary Redirect',
    308 => '308 Permanent Redirect',
    //Client Error 4xx
    400 => '400 Bad Request',
    401 => '401 Unauthorized',
    402 => '402 Payment Required',
    403 => '403 Forbidden',
    404 => '404 Not Found',
    405 => '405 Method Not Allowed',
    406 => '406 Not Acceptable',
    407 => '407 Proxy Authentication Required',
    408 => '408 Request Timeout',
    409 => '409 Conflict',
    410 => '410 Gone',
    411 => '411 Length Required',
    412 => '412 Precondition Failed',
    413 => '413 Request Entity Too Large',
    414 => '414 Request-URI Too Long',
    415 => '415 Unsupported Media Type',
    416 => '416 Requested Range Not Satisfiable',
    417 => '417 Expectation Failed',
    418 => '418 I\'m a teapot',
    422 => '422 Unprocessable Entity',
    423 => '423 Locked',
    424 => '424 Failed Dependency',
    426 => '426 Upgrade Required',
    428 => '428 Precondition Required',
    429 => '429 Too Many Requests',
    431 => '431 Request Header Fields Too Large',
    //Server Error 5xx
    500 => '500 Internal Server Error',
    501 => '501 Not Implemented',
    502 => '502 Bad Gateway',
    503 => '503 Service Unavailable',
    504 => '504 Gateway Timeout',
    505 => '505 HTTP Version Not Supported',
    506 => '506 Variant Also Negotiates',
    507 => '507 Insufficient Storage',
    508 => '508 Loop Detected',
    510 => '510 Not Extended',
    511 => '511 Network Authentication Required'
  );
  /**
   * Response protocol version
   *
   * @var string
   */
  protected $version = '1.1';
  /**
   * Response status code
   *
   * @var int
   */
  protected $status = 200;
  /**
   * Response charset
   *
   * @var string
   */
  protected $charset;
  /**
   * Response content type
   *
   * @var string
   */
  protected $contentType;
  /**
   * Request
   *
   * @var Webim\Http\Request
   */
  protected $request;
  /**
   * Response headers
   *
   * @var Webim\Http\Request
   */
  protected $headers;
  /**
   * Response cookies
   *
   * @var Webim\Http\Cookie
   */
  protected $cookies;
  /**
   * Response body
   *
   * @var Webim\Http\Stream
   */
  protected $content;

  /**
   * Constructor
   *
   * @param string $content The HTTP response body
   * @param int $status The HTTP response status
   * @param array $headers
   */
  public function __construct($content = '', $status = 200, $headers = array()) {
    $this->request = Request::current();

    $this->headers = $this->request->header();
    $this->headers->add($headers);

    $this->cookies = $this->request->cookie();

    $this->setStatus($status);
    $this->setContent($content);

    static::$instance = $this;
  }

  /**
   * Constructor
   *
   * @param string $content
   * @param int $status
   * @param array $headers
   *
   * @return Response
   */
  public static function create($content = '', $status = 200, $headers = array()) {
    return new static($content, $status, $headers);
  }

  /**
   * Current instance
   *
   * @return Response
   */
  public static function current() {
    return (static::$instance ? static::$instance : new static());
  }

  /**
   * Redirect
   *
   * This method prepares the response object to return an HTTP Redirect response
   * to the client.
   *
   * @param string $url The redirect destination
   * @param int $status The redirect HTTP status code
   */
  public static function redirect($url, $status = 302) {
    $redirect = new static('', $status, array(
      'Location' => $url
    ));

    $redirect->sendHeaders();
  }

  /**
   * Send headers
   *
   * @return $this
   */
  public function sendHeaders() {
    // Send headers
    if (headers_sent() === false) {
      //Prepare headers before sent
      $this->prepare();

      if (strpos(PHP_SAPI, 'cgi') === 0) {
        header(sprintf('Status: %s', $this->getReasonPhrase()));
      } else {
        header(sprintf('HTTP/%s %s', $this->getVersion(), $this->getReasonPhrase()));
      }

      //Headers
      foreach ($this->headers->all() as $name => $value) {
        header($name . ': ' . $value, false, $this->getStatus());
      }

      //Cookies
      foreach ($this->cookies->all() as $name => $values) {
        setcookie($name,
          array_get($values, 'value'),
          array_get($values, 'expires'),
          array_get($values, 'path'),
          array_get($values, 'domain'),
          array_get($values, 'secure'),
          array_get($values, 'httponly')
        );
      }
    }

    return $this;
  }

  /**
   * Prepares the Response before it is sent to the client.
   *
   * This method tweaks the Response to ensure that it is
   * compliant with RFC 2616. Most of the changes are based on
   * the Request that is "associated" with this Response.
   *
   * @return Response The current response.
   */
  public function prepare() {
    $request = $this->request;
    $headers = $this->headers;

    if ($this->isInformational() || in_array($this->status, array(204, 304))) {
      $this->setContent('', true);
      $headers->remove('Content-Type');
      $headers->remove('Content-Length');
    } else {
      // Fix Content-Type
      $charset = $this->charset ?: 'UTF-8';

      // Content-type based on the Request
      if ($this->contentType || !$headers->has('Content-Type')) {
        $format = $this->getContentType();
        if ((null !== $format) && ($mimeType = $request->getMimeType($format))) {
          $headers->set('Content-Type', $mimeType . '; charset=' . $charset);
        }
      } elseif ((0 === stripos($headers->get('Content-Type'), 'text/')) && (false === stripos($headers->get('Content-Type'), 'charset'))) {
        // add the charset
        $headers->set('Content-Type', $headers->get('Content-Type') . '; charset=' . $charset);
      }

      // Fix Content-Length
      if ($headers->has('Transfer-Encoding')) {
        $headers->remove('Content-Length');
      }

      if ($request->isHead()) {
        // cf. RFC2616 14.13
        $length = $headers->get('Content-Length');
        $this->setContent('', true);
        if ($length) {
          $headers->set('Content-Length', $length);
        }
      }
    }

    // Fix protocol
    if ('HTTP/1.0' != $request->server('SERVER_PROTOCOL')) {
      $this->setVersion('1.1');
    }

    // Check if we need to send extra expire info headers
    if (('1.0' == $this->getVersion()) && ('no-cache' == $this->headers->get('Cache-Control'))) {
      $this->headers->set('pragma', 'no-cache');
      $this->headers->set('expires', -1);
    }

    $this->ensureIEOverSSLCompatibility($request);

    return $this;
  }

  /**
   * Helpers: Informational?
   *
   * @return bool
   */
  public function isInformational() {
    return (($this->status >= 100) && ($this->status < 200));
  }

  /**
   * Get content type
   *
   * @param string $format
   *
   * @return string
   */
  public function getContentType($format = 'html') {
    if (null === $this->contentType) {
      $this->contentType = $format;
    }

    return $this->contentType;
  }

  /**
   * Set content type
   *
   * @param $format
   *
   * @return $this
   */
  public function setContentType($format) {
    $this->contentType = $format;

    return $this;
  }

  /**
   * Get HTTP protocol version
   *
   * @return string
   */
  public function getVersion() {
    return $this->version;
  }

  /**
   * Set HTTP protocol version
   *
   * @param string $version Either "1.1" or "1.0"
   *
   * @return $this
   */
  public function setVersion($version) {
    $this->version = $version;

    return $this;
  }

  /**
   * Checks if we need to remove Cache-Control for SSL encrypted downloads when using IE < 9
   *
   * @link http://support.microsoft.com/kb/323308
   */
  protected function ensureIEOverSSLCompatibility() {
    if ((false !== stripos($this->headers->get('Content-Disposition'), 'attachment'))
      && (preg_match('/MSIE (.*?);/i', $this->request->server('HTTP_USER_AGENT'), $match) == 1)
      && (true === $this->request->isSecure())
    ) {
      if (intval(preg_replace("/(MSIE )(.*?);/", "$2", $match[0])) < 9) {
        $this->headers->remove('Cache-Control');
      }
    }
  }

  /**
   * Get response reason phrase
   *
   * @return string
   */
  public function getReasonPhrase() {
    if (isset(static::$messages[$this->status]) === true) {
      return static::$messages[$this->status];
    }

    return null;
  }

  /**
   * Get response status code
   *
   * @return int
   * @api
   */
  public function getStatus() {
    return $this->status;
  }

  /**
   * Set response status code
   *
   * @param int $status
   *
   * @return $this
   */
  public function setStatus($status) {
    $this->status = (int)$status;

    return $this;
  }

  /**
   * Retrieves the response charset.
   *
   * @return string Character set
   */
  public function getCharset() {
    return $this->charset;
  }

  /**
   * Sets the response charset.
   *
   * @param string $charset Character set
   *
   * @return $this
   */
  public function setCharset($charset) {
    $this->charset = $charset;

    return $this;
  }

  /**
   * Get HTTP headers
   *
   * @return array
   */
  public function getHeaders() {
    return $this->headers->all();
  }

  /**
   * Set multiple header values
   *
   * @param array $headers
   *
   * @api
   */
  public function setHeaders(array $headers) {
    $this->headers->replace($headers);
  }

  /**
   * Does this request have a given header?
   *
   * @param string $name
   *
   * @return bool
   */
  public function hasHeader($name) {
    return $this->headers->has($name);
  }

  /**
   * Get header value
   *
   * @param string $name
   *
   * @return string
   */
  public function getHeader($name) {
    return $this->headers->get($name);
  }

  /**
   * Set header value
   *
   * @param string $name
   * @param string $value
   *
   * @return $this
   */
  public function setHeader($name, $value) {
    $this->headers->put($name, $value);

    return $this;
  }

  /**
   * Remove header
   *
   * @param string $name
   *
   * @return $this
   */
  public function removeHeader($name) {
    $this->headers->forget($name);

    return $this;
  }

  /**
   * Get cookies
   *
   * @return array
   */
  public function getCookies() {
    return $this->cookies->all();
  }

  /**
   * Set multiple cookies
   *
   * @param array $cookies
   *
   * @return $this
   */
  public function setCookies(array $cookies) {
    $this->cookies->replace($cookies);

    return $this;
  }

  /**
   * Does this request have a given cookie?
   *
   * @param string $name
   *
   * @return bool
   */
  public function hasCookie($name) {
    return $this->cookies->has($name);
  }

  /**
   * Get cookie value
   *
   * @param string $name
   * @param null|string $default
   *
   * @return array
   */
  public function getCookie($name, $default = null) {
    return $this->cookies->get($name, $default);
  }

  /**
   * Set cookie
   *
   * @param string $name
   * @param array|string $value
   * @param mixed $expires
   * @param string $path
   * @param null|string $domain
   * @param bool $secure
   * @param bool $httponly
   *
   * @return $this
   */
  public function setCookie($name, $value, $expires = 0, $path = '/', $domain = null, $secure = false, $httponly = true) {
    $this->cookies->set($name, $value, $expires, $path, $domain, $secure, $httponly);

    return $this;
  }

  /**
   * Remove cookie
   *
   * @param string $name
   * @param array $settings
   *
   * @return $this
   */
  public function removeCookie($name, $settings = array()) {
    $this->cookies->remove($name, $settings);

    return $this;
  }

  /**
   * Encrypt cookies
   *
   * @param Crypt $crypt
   *
   * @return $this
   */
  public function encryptCookies(Crypt $crypt) {
    $this->cookies->encrypt($crypt);

    return $this;
  }

  /**
   * Get the response body size if known
   *
   * @return int|false
   */
  public function getSize() {
    return $this->content->getSize();
  }

  /**
   * Send HTTP response headers and body
   *
   * @return Webim\Http\Response
   */
  public function send() {
    $this->sendHeaders();
    $this->sendContent();

    if (function_exists('fastcgi_finish_request')) {
      fastcgi_finish_request();
    } elseif ('cli' !== PHP_SAPI) {
      static::closeOutputBuffers(0, true);
      flush();
    }

    return $this;
  }

  /**
   * Send content body
   *
   * @return $this
   */
  public function sendContent() {
    // Send content body
    echo $this->getContent();

    return $this;
  }

  /**
   * Get response body
   *
   * @return string
   */
  public function getContent() {
    $content = '';

    $this->content->seek(0);

    while ($this->content->eof() === false) {
      $content .= $this->content->read(1024);
    }

    return $content;
  }

  /**
   * Set response body
   *
   * @param Webim\Http\Stream|string $body
   * @param bool $overwrite
   *
   * @return $this
   */
  public function setContent($body, $overwrite = false) {
    if (!($this->content instanceof Stream)) {
      $this->content = new Stream(fopen('php://temp', 'r+'));
    }

    if ($overwrite === true) {
      $this->content->close();
      $this->content = new Stream(fopen('php://temp', 'r+'));
    }

    $this->content->write($body);

    return $this;
  }

  /**
   * Cleans or flushes output buffers up to target level.
   *
   * Resulting level can be greater than target level if a non-removable buffer has been encountered.
   *
   * @param int $targetLevel The target output buffering level
   * @param bool $flush Whether to flush or clean the buffers
   */
  public static function closeOutputBuffers($targetLevel, $flush) {
    $status = ob_get_status(true);
    $level = count($status);

    while ($level-- > $targetLevel
      && (!empty($status[$level]['del'])
        || (isset($status[$level]['flags'])
          && ($status[$level]['flags'] & PHP_OUTPUT_HANDLER_REMOVABLE)
          && ($status[$level]['flags'] & ($flush ? PHP_OUTPUT_HANDLER_FLUSHABLE : PHP_OUTPUT_HANDLER_CLEANABLE))
        )
      )
    ) {
      if ($flush) {
        ob_end_flush();
      } else {
        ob_end_clean();
      }
    }
  }

  /**
   * Helpers: Empty?
   *
   * @return bool
   */
  public function isEmpty() {
    return in_array($this->status, array(201, 204, 304));
  }

  /**
   * Helpers: OK?
   *
   * @return bool
   */
  public function isOk() {
    return ($this->status === 200);
  }

  /**
   * Helpers: Successful?
   *
   * @return bool
   */
  public function isSuccessful() {
    return (($this->status >= 200) && ($this->status < 300));
  }

  /**
   * Helpers: Redirect?
   *
   * @return bool
   */
  public function isRedirect() {
    return in_array($this->status, array(301, 302, 303, 307));
  }

  /**
   * Helpers: Redirection?
   *
   * @return bool
   */
  public function isRedirection() {
    return (($this->status >= 300) && ($this->status < 400));
  }

  /**
   * Helpers: Forbidden?
   *
   * @return bool
   */
  public function isForbidden() {
    return ($this->status === 403);
  }

  /**
   * Helpers: Not Found?
   *
   * @return bool
   */
  public function isNotFound() {
    return ($this->status === 404);
  }

  /**
   * Helpers: Client error?
   *
   * @return bool
   */
  public function isClientError() {
    return (($this->status >= 400) && ($this->status < 500));
  }

  /**
   * Helpers: Server Error?
   *
   * @return bool
   */
  public function isServerError() {
    return (($this->status >= 500) && ($this->status < 600));
  }

  /**
   * Convert response to string
   *
   * @return string
   */
  public function __toString() {
    $output = sprintf('HTTP/%s %s', $this->getVersion(), $this->getReasonPhrase()) . PHP_EOL;

    foreach ($this->headers as $name => $value) {
      $output .= sprintf('%s: %s', $name, $value) . PHP_EOL;
    }

    $content = (string)$this->getContent();

    if ($content) {
      $output .= PHP_EOL . $content;
    }

    return $output;
  }

}