<?php
/**
 * @author Orhan POLAT
 */

namespace Webim\Cache;

use Webim\Cache\Manager as Cache;

abstract class FileCache extends CacheProvider {

  /**
   * The cache directory.
   *
   * @var string
   */
  protected $directory;

  /**
   * The cache file extension.
   *
   * @var string|null
   */
  protected $extension;

  /**
   * Constructor.
   *
   * @param string $directory The cache directory.
   * @param string|null $extension The cache file extension.
   *
   * @throws \InvalidArgumentException
   */
  public function __construct($directory, $extension = null) {
    if (!is_dir($directory) && !@mkdir($directory, 0777, true)) {
      throw new \InvalidArgumentException(sprintf(
        'The directory "%s" does not exist and could not be created.',
        $directory
      ));
    }

    if (!is_writable($directory)) {
      throw new \InvalidArgumentException(sprintf(
        'The directory "%s" is not writable.',
        $directory
      ));
    }

    $this->directory = realpath($directory);
    $this->extension = $extension ?: $this->extension;
  }

  /**
   * Gets the cache directory.
   *
   * @return string
   */
  public function getDirectory() {
    return $this->directory;
  }

  /**
   * Gets the cache file extension.
   *
   * @return string|null
   */
  public function getExtension() {
    return $this->extension;
  }

  /**
   * {@inheritdoc}
   */
  protected function doDelete($id) {
    return @unlink($this->getFilename($id));
  }

  /**
   * @param string $id
   *
   * @return string
   */
  protected function getFilename($id) {
    $hash = hash('sha256', $id);
    $path = implode(str_split($hash, 16), DIRECTORY_SEPARATOR);
    $path = $this->directory . DIRECTORY_SEPARATOR . $path;
    $id = preg_replace('@[\\\/:"*?<>|]+@', '', $id);

    return $path . DIRECTORY_SEPARATOR . $id . $this->extension;
  }

  /**
   * {@inheritdoc}
   */
  protected function doFlush() {
    foreach ($this->getIterator() as $name => $file) {
      @unlink($name);
    }

    return true;
  }

  /**
   * @return \Iterator
   */
  private function getIterator() {
    $pattern = '/^.+\\' . $this->extension . '$/i';
    $iterator = new \RecursiveDirectoryIterator($this->directory);
    $iterator = new \RecursiveIteratorIterator($iterator);

    return new \RegexIterator($iterator, $pattern);
  }

  /**
   * {@inheritdoc}
   */
  protected function doStats() {
    $usage = 0;

    foreach ($this->getIterator() as $name => $file) {
      $usage += $file->getSize();
    }

    $free = disk_free_space($this->directory);

    return array(
      Cache::STATS_HITS => null,
      Cache::STATS_MISSES => null,
      Cache::STATS_UPTIME => null,
      Cache::STATS_MEMORY_USAGE => $usage,
      Cache::STATS_MEMORY_AVAILABLE => $free,
    );
  }

}