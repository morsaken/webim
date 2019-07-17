<?php
/**
 * @author Orhan POLAT
 */

namespace Webim\Cache\Driver;

use Webim\Cache\CacheProvider;
use Webim\Cache\Manager as Cache;

class XcacheCache extends CacheProvider {

  /**
   * {@inheritdoc}
   */
  protected function doGet($id) {
    return xcache_isset($id) ? array_get(unserialize(xcache_get($id)), 'data') : null;
  }

  /**
   * {@inheritdoc}
   */
  protected function doHas($id) {
    return xcache_isset($id) ? array_get(unserialize(xcache_get($id)), 'life') : false;
  }

  /**
   * {@inheritdoc}
   */
  protected function doSave($id, $data, $lifeTime = 0) {
    $data = array(
      'life' => (int)$lifeTime,
      'data' => $data
    );

    return xcache_set($id, serialize($data), (int)$lifeTime);
  }

  /**
   * {@inheritdoc}
   */
  protected function doDelete($id) {
    return xcache_unset($id);
  }

  /**
   * {@inheritdoc}
   */
  protected function doFlush() {
    $this->checkAuthorization();

    xcache_clear_cache(XC_TYPE_VAR, 0);

    return true;
  }

  /**
   * Checks that xcache.admin.enable_auth is Off.
   *
   * @return void
   *
   * @throws \BadMethodCallException When xcache.admin.enable_auth is On.
   */
  protected function checkAuthorization() {
    if (ini_get('xcache.admin.enable_auth')) {
      throw new \BadMethodCallException('To use all features of \Core\Cache\XcacheCache, you must set "xcache.admin.enable_auth" to "Off" in your php.ini.');
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function doStats() {
    $this->checkAuthorization();

    $info = xcache_info(XC_TYPE_VAR, 0);

    return array(
      Cache::STATS_HITS => $info['hits'],
      Cache::STATS_MISSES => $info['misses'],
      Cache::STATS_UPTIME => null,
      Cache::STATS_MEMORY_USAGE => $info['size'],
      Cache::STATS_MEMORY_AVAILABLE => $info['avail'],
    );
  }

}