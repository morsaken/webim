<?php
/**
 * @author Orhan POLAT
 */

namespace Webim\Cache\Driver;

use Memcache;
use Webim\Cache\CacheProvider;
use Webim\Cache\Manager as Cache;

class MemcacheCache extends CacheProvider {

  /**
   * @var Memcache|null
   */
  private $memcache;

  /**
   * Gets the memcache instance used by the cache.
   *
   * @return Memcache|null
   */
  public function getMemcache() {
    return $this->memcache;
  }

  /**
   * Sets the memcache instance to use.
   *
   * @param Memcache $memcache
   *
   * @return void
   */
  public function setMemcache(Memcache $memcache) {
    $this->memcache = $memcache;
  }

  /**
   * {@inheritdoc}
   */
  protected function doGet($id) {
    return array_get($this->memcache->get($id), 'data');
  }

  /**
   * {@inheritdoc}
   */
  protected function doHas($id) {
    return array_get($this->memcache->get($id), 'life', false);
  }

  /**
   * {@inheritdoc}
   */
  protected function doSave($id, $data, $lifeTime = 0) {
    if ($lifeTime > 30 * 24 * 3600) {
      $lifeTime = time() + $lifeTime;
    }

    return $this->memcache->set($id, array(
      'life' => (int)$lifeTime,
      'data' => $data
    ), 0, (int)$lifeTime);
  }

  /**
   * {@inheritdoc}
   */
  protected function doDelete($id) {
    return $this->memcache->delete($id);
  }

  /**
   * {@inheritdoc}
   */
  protected function doFlush() {
    return $this->memcache->flush();
  }

  /**
   * {@inheritdoc}
   */
  protected function doStats() {
    $stats = $this->memcache->getStats();

    return array(
      Cache::STATS_HITS => $stats['get_hits'],
      Cache::STATS_MISSES => $stats['get_misses'],
      Cache::STATS_UPTIME => $stats['uptime'],
      Cache::STATS_MEMORY_USAGE => $stats['bytes'],
      Cache::STATS_MEMORY_AVAILABLE => $stats['limit_maxbytes'],
    );
  }

}