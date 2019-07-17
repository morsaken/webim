<?php
/**
 * @author Orhan POLAT
 */

namespace Webim\Http;

class Redirect extends Response {

  /**
   * Target url
   *
   * @var string
   */
  protected $targetUrl;

  /**
   * Creates a redirect response so that it conforms to the rules defined for a redirect status code.
   *
   * @param string $url The URL to redirect to
   * @param int $status The status code (302 by default)
   * @param array $headers The headers (Location is always set to the given URL)
   *
   * @throws \InvalidArgumentException
   *
   * @see http://tools.ietf.org/html/rfc2616#section-10.3
   */
  public function __construct($url, $status = 302, $headers = array()) {
    parent::__construct('', $status, $headers);

    $this->setTargetUrl($url);

    if (!$this->isRedirect()) {
      throw new \InvalidArgumentException(sprintf('The HTTP status code is not a redirect ("%s" given).', $status));
    }
  }

  /**
   * Constructor
   *
   * @param string $url
   * @param int $status
   * @param array $headers
   *
   * @return $this
   */
  public static function create($url = '', $status = 302, $headers = array()) {
    return new static($url, $status, $headers);
  }

  /**
   * Returns the target URL.
   *
   * @return string target URL
   */
  public function getTargetUrl() {
    return $this->targetUrl;
  }

  /**
   * Sets the redirect target of this response.
   *
   * @param string $url The URL to redirect to
   *
   * @return $this The current response.
   */
  public function setTargetUrl($url) {
    $this->targetUrl = $url;

    $content = sprintf('<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <meta http-equiv="refresh" content="1;url=%1$s" />

        <title>Redirecting to %1$s</title>
    </head>
    <body>
        Redirecting to <a href="%1$s">%1$s</a>.
    </body>
</html>', htmlspecialchars($url, ENT_QUOTES, 'UTF-8'));

    $this->setContent($content, true);

    $this->headers->set('Location', $url);

    return $this;
  }

}