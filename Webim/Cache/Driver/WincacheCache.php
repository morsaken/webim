<?php
/**
 * @author Orhan POLAT
 */

namespace Webim\Cache\Driver;

use Webim\Cache\CacheProvider;
use Webim\Cache\Manager as Cache;

class WincacheCache extends CacheProvider {

  /**
   * {@inheritdoc}
   */
  protected function doGet($id) {
    return array_get(wincache_ucache_get($id), 'data');
  }

  /**
   * {@inheritdoc}
   */
  protected function doHas($id) {
    return wincache_ucache_exists($id) ? array_get(wincache_ucache_get($id), 'life') : false;
  }

  /**
   * {@inheritdoc}
   */
  protected function doSave($id, $data, $lifeTime = 0) {
    return (bool)wincache_ucache_set($id, array(
      'life' => $lifeTime,
      'data' => $data
    ), (int)$lifeTime);
  }

  /**
   * {@inheritdoc}
   */
  protected function doDelete($id) {
    return wincache_ucache_delete($id);
  }

  /**
   * {@inheritdoc}
   */
  protected function doFlush() {
    return wincache_ucache_clear();
  }

  /**
   * {@inheritdoc}
   */
  protected function doStats() {
    $info = wincache_ucache_info();
    $meminfo = wincache_ucache_meminfo();

    return array(
      Cache::STATS_HITS => $info['total_hit_count'],
      Cache::STATS_MISSES => $info['total_miss_count'],
      Cache::STATS_UPTIME => $info['total_cache_uptime'],
      Cache::STATS_MEMORY_USAGE => $meminfo['memory_total'],
      Cache::STATS_MEMORY_AVAILABLE => $meminfo['memory_free'],
    );
  }

}