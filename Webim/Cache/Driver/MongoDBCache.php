<?php
/**
 * @author Orhan POLAT
 */

namespace Webim\Cache\Driver;

use MongoBinData;
use MongoCollection;
use MongoDate;
use Webim\Cache\CacheProvider;
use Webim\Cache\Manager as Cache;

class MongoDBCache extends CacheProvider {

  /**
   * The data field will store the serialized PHP value.
   */
  const DATA_FIELD = 'd';

  /**
   * The expiration field will store a MongoDate value indicating when the
   * cache entry should expire.
   *
   * With MongoDB 2.2+, entries can be automatically deleted by MongoDB by
   * indexing this field wit the "expireAfterSeconds" option equal to zero.
   * This will direct MongoDB to regularly query for and delete any entries
   * whose date is older than the current time. Entries without a date value
   * in this field will be ignored.
   *
   * The cache provider will also check dates on its own, in case expired
   * entries are fetched before MongoDB's TTLMonitor pass can expire them.
   *
   * @see http://docs.mongodb.org/manual/tutorial/expire-data/
   */
  const EXPIRATION_FIELD = 'e';

  /**
   * @var MongoCollection
   */
  private $collection;

  /**
   * This provider will default to the write concern and read preference
   * options set on the MongoCollection instance (or inherited from MongoDB or
   * MongoClient). Using an unacknowledged write concern (< 1) may make the
   * return values of delete() and save() unreliable. Reading from secondaries
   * may make contain() and fetch() unreliable.
   *
   * @see http://www.php.net/manual/en/mongo.readpreferences.php
   * @see http://www.php.net/manual/en/mongo.writeconcerns.php
   *
   * @param MongoCollection $mongo
   *
   * @return void
   */
  public function setMongoCache(MongoCollection $mongo) {
    $this->collection = $mongo;
  }

  /**
   * Gets the mongo collection instance used by the cache.
   *
   * @return MongoCollection|null
   */
  public function getMongoCache() {
    return $this->collection;
  }

  /**
   * {@inheritdoc}
   */
  protected function doGet($id) {
    $document = $this->collection->findOne(array('_id' => $id), array(self::DATA_FIELD, self::EXPIRATION_FIELD));

    if ($document === null) {
      return false;
    }

    if ($this->isExpired($document)) {
      $this->doDelete($id);

      return false;
    }

    return unserialize($document[self::DATA_FIELD]->bin);
  }

  /**
   * Check if the document is expired.
   *
   * @param array $document
   *
   * @return bool
   */
  private function isExpired(array $document) {
    return isset($document[self::EXPIRATION_FIELD]) &&
      $document[self::EXPIRATION_FIELD] instanceof MongoDate &&
      $document[self::EXPIRATION_FIELD]->sec < time();
  }

  /**
   * {@inheritdoc}
   */
  protected function doDelete($id) {
    $result = $this->collection->remove(array('_id' => $id));

    return isset($result['n']) ? $result['n'] == 1 : true;
  }

  /**
   * {@inheritdoc}
   */
  protected function doHas($id) {
    $document = $this->collection->findOne(array('_id' => $id), array(self::EXPIRATION_FIELD));

    if ($document === null) {
      return false;
    }

    if ($this->isExpired($document)) {
      $this->doDelete($id);

      return false;
    }

    return true;
  }

  /**
   * {@inheritdoc}
   */
  protected function doSave($id, $data, $lifeTime = 0) {
    $result = $this->collection->update(
      array('_id' => $id),
      array('$set' => array(
        self::EXPIRATION_FIELD => ($lifeTime > 0 ? new MongoDate(time() + $lifeTime) : null),
        self::DATA_FIELD => new MongoBinData(serialize($data), MongoBinData::BYTE_ARRAY),
      )),
      array('upsert' => true, 'multiple' => false)
    );

    return isset($result['ok']) ? $result['ok'] == 1 : true;
  }

  /**
   * {@inheritdoc}
   */
  protected function doFlush() {
    // Use remove() in lieu of drop() to maintain any collection indexes
    $result = $this->collection->remove();

    return isset($result['ok']) ? $result['ok'] == 1 : true;
  }

  /**
   * {@inheritdoc}
   */
  protected function doStats() {
    $serverStatus = $this->collection->db->command(array(
      'serverStatus' => 1,
      'locks' => 0,
      'metrics' => 0,
      'recordStats' => 0,
      'repl' => 0
    ));

    $collStats = $this->collection->db->command(array('collStats' => 1));

    return array(
      Cache::STATS_HITS => null,
      Cache::STATS_MISSES => null,
      Cache::STATS_UPTIME => (isset($serverStatus['uptime']) ? (integer)$serverStatus['uptime'] : null),
      Cache::STATS_MEMORY_USAGE => (isset($collStats['size']) ? (integer)$collStats['size'] : null),
      Cache::STATS_MEMORY_AVAILABLE => null,
    );
  }

}