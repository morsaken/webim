<?php
/**
 * @author Orhan POLAT
 */

namespace Webim\Library;

use Webim\Http\Request;

class Paging {
  /**
   * Paging navigation
   *
   * @param int $offset
   * @param int $limit
   * @param int $total
   * @param array $labels
   * @param int $wrapper
   *
   * @return array
   */
  public static function nav($offset, $limit, $total, $labels = array(), $wrapper = 0) {
    //Limit, offset and total
    $calc = static::calc($offset, $limit, $total);

    //Values
    $offset = $calc->offset;
    $limit = $calc->limit;
    $total = $calc->total;

    //Labels
    $firstLabel = array_get($labels, 'first', lang('paging.label.first', '|&gt;'));
    $previousLabel = array_get($labels, 'previous', lang('paging.label.previous', '&gt;'));
    $nextLabel = array_get($labels, 'next', lang('paging.label.next', '&lt;'));
    $lastLabel = array_get($labels, 'last', lang('paging.label.last', '&lt;|'));

    //Return
    $nav = new \stdClass();

    $nav->offset = $offset;
    $nav->limit = $limit;
    $nav->total = $total;

    $nav->first = static::button($firstLabel);
    $nav->previous = static::button($previousLabel);
    $nav->next = static::button($nextLabel);
    $nav->last = static::button($lastLabel);

    $nav->pages = array();

    //Navigation config
    $first = 0;
    $previous = $offset - $limit;
    $next = $offset + $limit;
    $last = ($total - (($total % $limit == 0) ? $limit : $total % $limit));

    //First
    if ($first < $offset) {
      $nav->first->offset = $first;
      $nav->first->link = static::url($first);
    }

    //Previous
    if ($offset > 0) {
      $nav->previous->offset = $previous;
      $nav->previous->link = static::url($previous);
    }

    //Next
    if ($offset < ($total - $limit)) {
      $nav->next->offset = $next;
      $nav->next->link = static::url($next);
    }

    //Last
    if (($offset + $limit) < $total) {
      $nav->last->offset = $last;
      $nav->last->link = static::url($last);
    }

    //Pages
    $nav->pages = static::pages($offset, $limit, $total, $wrapper);

    return $nav;
  }

  /**
   * Calculate paging values
   *
   * @param int $offset
   * @param int $limit
   * @param int $total
   *
   * @return array
   */
  public static function calc($offset, $limit, $total) {
    //Set default limit value
    if (!is_numeric($offset)) {
      $offset = 0;
    }

    //Set default offset value
    if (!is_numeric($limit)) {
      $limit = null;
    }

    //Set default total value
    if (!is_numeric($total)) {
      $total = 0;
    }

    //Return
    $calc = new \stdClass();
    $calc->offset = $offset;
    $calc->limit = $limit;
    $calc->total = $total;

    if ($total < 0) {
      $total = 0;
      $calc->total = $total;
    }

    if ($limit && (($limit < 0) || ($limit > 100))) {
      $limit = 20;
      $calc->limit = $limit;
    }

    if (is_null($limit) && ($offset < 1)) {
      $calc->offset = $total;
    } elseif (($offset < 0) || ($limit && ($offset % $limit > 0))) {
      $calc->offset = 0;
    } elseif ($offset >= $total) {
      $calc->offset = ((($offset - $limit) > -1) ? ($offset - $limit) : 0);
    }

    return $calc;
  }

  /**
   * Button object
   *
   * @param string $label
   * @param null|int $offset
   * @param null|string $link
   *
   * @return \stdClass
   */
  protected static function button($label, $offset = null, $link = null) {
    $button = new \stdClass();
    $button->label = $label;
    $button->offset = $offset;
    $button->link = $link;

    return $button;
  }

  /**
   * URL string
   *
   * @param int $offset
   *
   * @return string
   */
  protected static function url($offset = 0) {
    //Query string
    $query = array();

    foreach (array_filter(explode('&', Request::current()->getQueryString()), function ($value) {
      return strlen($value);
    }) as $data) {
      $values = explode('=', $data, 2);

      if ($values[0] !== 'offset') {
        $query[$values[0]] = $values[0] . (strlen(array_get($values, 1)) ? '=' . array_get($values, 1) : '');
      }
    }

    $query['offset'] = 'offset=' . $offset;

    return url() . '?' . implode('&', $query);
  }

  /**
   * Pages
   *
   * @param int $offset
   * @param int $limit
   * @param int $total
   * @param int $wrapper
   *
   * @return array
   */
  protected static function pages($offset, $limit, $total, $wrapper) {
    //Pages
    $pages = array();

    //Current page and count pages
    $current = ceil($offset / $limit) + 1;
    $count = ceil($total / $limit);

    if (($wrapper > 0) && ($count > $wrapper * 2 + 1)) {
      if (($current + 1 > $wrapper) && ($current - 2 < $count - $wrapper)) {
        if ($current > $wrapper + $wrapper / 2) {
          for ($page = 1; $page <= $wrapper / 2; $page++) {
            $start = ($page - 1) * $limit;

            $pages[] = static::page($page, $start, static::url($start), ($page == $current));
          }
        }

        $pages[] = static::page('...');

        for ($page = $current - ceil($wrapper / 2); $page <= $current - ceil($wrapper / 2) + $wrapper - 1; $page++) {
          $start = ($page - 1) * $limit;

          $pages[] = static::page($page, $start, static::url($start), ($page == $current));
        }

        $pages[] = static::page('...');

        for ($page = $count - floor($wrapper / 2) + 1; $page <= $count; $page++) {
          $start = ($page - 1) * $limit;

          $pages[] = static::page($page, $start, static::url($start), ($page == $current));
        }
      } else {
        for ($page = 1; $page <= $wrapper; $page++) {
          $start = ($page - 1) * $limit;

          $pages[] = static::page($page, $start, static::url($start), ($page == $current));
        }

        $pages[] = static::page('...');

        for ($page = $count - $wrapper + 1; $page <= $count; $page++) {
          $start = ($page - 1) * $limit;

          $pages[] = static::page($page, $start, static::url($start), ($page == $current));
        }
      }
    } else {
      for ($page = 1; $page <= $count; $page++) {
        $start = ($page - 1) * $limit;

        $pages[] = static::page($page, $start, static::url($start), ($page == $current));
      }
    }

    return $pages;
  }

  /**
   * Page object
   *
   * @param string $label
   * @param null|int $offset
   * @param null|string $link
   * @param null|bool $active
   *
   * @return \stdClass
   */
  protected static function page($label, $offset = null, $link = null, $active = null) {
    $page = new \stdClass();
    $page->label = $label;
    $page->offset = $offset;
    $page->link = $link;
    $page->active = $active;

    return $page;
  }

}