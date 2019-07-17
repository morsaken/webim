<?php
/**
 * @author Orhan POLAT
 */

namespace Webim\Cache\Driver;

use Memcached;
use Webim\Cache\CacheProvider;
use Webim\Cache\Manager as Cache;

class MemcachedCache extends CacheProvider {

  /**
   * @var Memcached|null
   */
  private $memcached;

  /**
   * Gets the memcached instance used by the cache.
   *
   * @return Memcached|null
   */
  public function getMemcached() {
    return $this->memcached;
  }

  /**
   * Sets the memcached instance to use.
   *
   * @param Memcached $memcached
   *
   * @return void
   */
  public function setMemcached(Memcached $memcached) {
    $this->memcached = $memcached;
  }

  /**
   * {@inheritdoc}
   */
  protected function doGet($id) {
    return array_get($this->memcached->get($id), 'data');
  }

  /**
   * {@inheritdoc}
   */
  protected function doHas($id) {
    return array_get($this->memcached->get($id), 'life', false);
  }

  /**
   * {@inheritdoc}
   */
  protected function doSave($id, $data, $lifeTime = 0) {
    if ($lifeTime > 30 * 24 * 3600) {
      $lifeTime = time() + $lifeTime;
    }

    return $this->memcached->set($id, array(
      'life' => (int)$lifeTime,
      'data' => $data
    ), (int)$lifeTime);
  }

  /**
   * {@inheritdoc}
   */
  protected function doDelete($id) {
    return $this->memcached->delete($id);
  }

  /**
   * {@inheritdoc}
   */
  protected function doFlush() {
    return $this->memcached->flush();
  }

  /**
   * {@inheritdoc}
   */
  protected function doStats() {
    $stats = $this->memcached->getStats();

    return array(
      Cache::STATS_HITS => $stats['get_hits'],
      Cache::STATS_MISSES => $stats['get_misses'],
      Cache::STATS_UPTIME => $stats['uptime'],
      Cache::STATS_MEMORY_USAGE => $stats['bytes'],
      Cache::STATS_MEMORY_AVAILABLE => $stats['limit_maxbytes'],
    );
  }

}