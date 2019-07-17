<?php
/**
 * @author Orhan POLAT
 */

namespace Webim\Cache\Driver;

use Webim\Cache\FileCache;

class FilesystemCache extends FileCache {

  const EXTENSION = '.data';

  /**
   * {@inheritdoc}
   */
  protected $extension = self::EXTENSION;

  /**
   * {@inheritdoc}
   */
  protected function doGet($id) {
    $data = '';
    $lifetime = -1;
    $filename = $this->getFilename($id);

    if (!is_file($filename)) {
      return false;
    }

    $resource = fopen($filename, 'r');

    if (false !== ($line = fgets($resource))) {
      $lifetime = (integer)$line;
    }

    if ($lifetime !== 0 && $lifetime < time()) {
      fclose($resource);

      return false;
    }

    while (false !== ($line = fgets($resource))) {
      $data .= $line;
    }

    fclose($resource);

    return unserialize($data);
  }

  /**
   * {@inheritdoc}
   */
  protected function doHas($id) {
    $lifetime = -1;
    $filename = $this->getFilename($id);

    if (!is_file($filename)) {
      return false;
    }

    $resource = fopen($filename, 'r');

    if (false !== ($line = fgets($resource))) {
      $lifetime = (integer)$line;
    }

    fclose($resource);

    return (($lifetime === 0 || $lifetime > time()) ? $lifetime : false);
  }

  /**
   * {@inheritdoc}
   */
  protected function doSave($id, $data, $lifeTime = 0) {
    if ($lifeTime > 0) {
      $lifeTime = time() + $lifeTime;
    }

    $data = serialize($data);
    $filename = $this->getFilename($id);
    $filepath = pathinfo($filename, PATHINFO_DIRNAME);

    if (!is_dir($filepath)) {
      mkdir($filepath, 0777, true);
    }

    return file_put_contents($filename, $lifeTime . PHP_EOL . $data) !== false;
  }

}