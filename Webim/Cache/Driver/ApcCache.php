<?php
/**
 * @author Orhan POLAT
 */

namespace Webim\Cache\Driver;

use Webim\Cache\CacheProvider;
use Webim\Cache\Manager as Cache;

class ApcCache extends CacheProvider {

  /**
   * {@inheritdoc}
   */
  protected function doGet($id) {
    return array_get(apc_fetch($id), 'data');
  }

  /**
   * {@inheritdoc}
   */
  protected function doHas($id) {
    return apc_exists($id) ? array_get(apc_fetch($id), 'life') : false;
  }

  /**
   * {@inheritdoc}
   */
  protected function doSave($id, $data, $lifeTime = 0) {
    return (bool)apc_store($id, array(
      'life' => (int)$lifeTime,
      'data' => $data
    ), (int)$lifeTime);
  }

  /**
   * {@inheritdoc}
   */
  protected function doDelete($id) {
    return apc_delete($id);
  }

  /**
   * {@inheritdoc}
   */
  protected function doFlush() {
    return apc_clear_cache() && apc_clear_cache('user');
  }

  /**
   * {@inheritdoc}
   */
  protected function doStats() {
    $info = apc_cache_info();
    $sma = apc_sma_info();

    // @TODO - Temporary fix @see https://github.com/krakjoe/apcu/pull/42
    if (PHP_VERSION_ID >= 50500) {
      $info['num_hits'] = isset($info['num_hits']) ? $info['num_hits'] : $info['nhits'];
      $info['num_misses'] = isset($info['num_misses']) ? $info['num_misses'] : $info['nmisses'];
      $info['start_time'] = isset($info['start_time']) ? $info['start_time'] : $info['stime'];
    }

    return array(
      Cache::STATS_HITS => $info['num_hits'],
      Cache::STATS_MISSES => $info['num_misses'],
      Cache::STATS_UPTIME => $info['start_time'],
      Cache::STATS_MEMORY_USAGE => $info['mem_size'],
      Cache::STATS_MEMORY_AVAILABLE => $sma['avail_mem'],
    );
  }

}