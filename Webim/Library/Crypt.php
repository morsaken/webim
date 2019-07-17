<?php
/**
 * @author Orhan POLAT
 */

namespace Webim\Library;

class Crypt {

  /**
   * The key used to decrypt.
   *
   * @var string
   */
  public $key;

  /**
   * The original encrypted payload
   *
   * @var string
   */
  public $payload;

  /**
   * The cipher types to try for decryption.
   *
   * @var array
   */
  protected $ciphers = [
    'AES-128-CBC',
    'AES-256-CBC'
  ];

  /**
   * Construct a new Crypt
   *
   * @param null $key
   */
  public function __construct($key = null) {
    if (!is_null($key))
      $this->key = $key;
  }

  /**
   * Encrypt a payload.
   *
   * @param mixed $payload
   * @param string $cipher
   * @param int $length
   *
   * @return string
   */
  public function encrypt($payload, $cipher = 'AES-256-CBC', $length = 16) {
    $iv = openssl_random_pseudo_bytes($length, $strong);
    $value = openssl_encrypt(serialize($payload), $cipher, $this->key, 0, $iv);
    $mac = $this->hmac($iv = base64_encode($iv), $value, $this->key);

    return base64_encode(json_encode(compact('iv', 'value', 'mac')));
  }

  /**
   * Generate a sha256 keyed hash value.
   *
   * @param $iv
   * @param $value
   * @param $key
   *
   * @return string
   */
  public function hmac($iv, $value, $key) {
    return hash_hmac('sha256', $iv . $value, $key);
  }

  /**
   * Attempt to decrypt a payload.
   *
   * @param string $payload
   *
   * @return mixed|string
   */
  public function decrypt($payload) {
    if ((gettype($payload) !== 'string') || !strlen($payload)) {
      return $payload;
    }

    $payload = json_decode(base64_decode($payload), true);

    if ($this->invalidPayload($payload)) {
      return $payload;
    }

    $iv = base64_decode($payload['iv']);

    foreach ($this->ciphers as $cipher) {
      $decrypted = openssl_decrypt($payload['value'], $cipher, $this->key, 0, $iv);

      if ($decrypted) {
        return unserialize($decrypted);
      }
    }

    return 'Failed to decrypt the payload.';
  }

  /**
   * Check if a payload is in the correct format.
   *
   * @param $data
   *
   * @return bool
   */
  public function invalidPayload($data) {
    return !is_array($data) || !isset($data['iv']) || !isset($data['value']) || !isset($data['mac']);
  }

}