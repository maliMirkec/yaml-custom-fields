<?php

/**
 * Collection Lock
 * One save at a time to a collection's list of items
 */

namespace YamlCF\Data;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * A collection's index (the id => label list every screen reads) is one option,
 * read, changed and written back whole. Two saves at the same moment both read
 * the same list, and the second write replaced the first: the first item was
 * stored but missing from the list, so nothing ever showed it again.
 *
 * This makes the second save wait until the first is done. It is the lock
 * WordPress itself uses to stop two core updates running at once
 * (WP_Upgrader::create_lock()): a row in the options table, created with
 * INSERT IGNORE, which MySQL lets exactly one request create.
 *
 * Direct SQL is the documented exception to rule 5 of .claude-rules.md. The
 * options API cannot do this: add_option() checks first and writes second, and
 * two requests can both pass the check. The queries are prepared and touch only
 * this plugin's lock rows.
 */
class CollectionLock {
  /** Prefix of the option row that holds a collection's lock */
  const PREFIX = 'yaml_cf_do_lock_';

  /**
   * Seconds after which a lock is taken to be left behind
   *
   * A save holds it for a fraction of a second. A lock this old belongs to a
   * request that died before releasing it.
   */
  const STALE_AFTER = 10;

  /** Longest a save waits for the lock, in seconds */
  const WAIT_FOR = 12;

  /** Pause between attempts, in microseconds */
  const RETRY_EVERY = 100000;

  /**
   * Locks this request holds, with how many times each was taken
   *
   * A save that moves a collection out of its old storage first takes the lock
   * twice, so taking it again is allowed and the row goes on the last release.
   *
   * @var array<string, int>
   */
  private static $held = [];

  /**
   * When this request last wrote the time into each lock it holds
   *
   * @var array<string, int>
   */
  private static $touched = [];

  /** Least number of seconds between two keepAlive() writes */
  const TOUCH_EVERY = 2;

  /**
   * Run some work while holding a collection's lock
   *
   * If the lock cannot be had within WAIT_FOR seconds the work runs anyway:
   * refusing the save would lose the editor's changes for certain, to prevent
   * a clash that is only possible.
   *
   * @param string $type_slug Collection slug
   * @param callable $work What to do with the lock held
   * @return mixed What $work returns
   */
  public static function run($type_slug, callable $work) {
    $acquired = self::acquire($type_slug);

    try {
      return $work();
    } finally {
      if ($acquired) {
        self::release($type_slug);
      }
    }
  }

  /**
   * Tell other requests this lock is still in use
   *
   * A lock older than STALE_AFTER is taken to be left behind by a request that
   * died. Replacing or moving a large collection can take longer than that, and
   * another save would then take the lock over in the middle of it. Work that
   * loops over items calls this as it goes; it writes at most once every
   * TOUCH_EVERY seconds, so calling it per item costs nothing.
   *
   * @param string $type_slug Collection slug
   * @return void
   */
  public static function keepAlive($type_slug) {
    if (!isset(self::$held[$type_slug])) {
      return;
    }

    $now = time();
    if (isset(self::$touched[$type_slug]) && $now - self::$touched[$type_slug] < self::TOUCH_EVERY) {
      return;
    }

    global $wpdb;
    $wpdb->query(
      $wpdb->prepare(
        "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s",
        (string) $now,
        self::PREFIX . $type_slug
      )
    );
    self::$touched[$type_slug] = $now;
  }

  /**
   * Take the lock, waiting for it if another request has it
   *
   * @param string $type_slug Collection slug
   * @return bool Whether it was taken
   */
  private static function acquire($type_slug) {
    if (isset(self::$held[$type_slug])) {
      self::$held[$type_slug]++;
      return true;
    }

    $deadline = microtime(true) + self::WAIT_FOR;

    do {
      if (self::tryLock($type_slug)) {
        self::$held[$type_slug] = 1;
        self::$touched[$type_slug] = time();
        return true;
      }

      usleep(self::RETRY_EVERY);
    } while (microtime(true) < $deadline);

    return false;
  }

  /**
   * One attempt at the lock
   *
   * @param string $type_slug Collection slug
   * @return bool Whether it was taken
   */
  private static function tryLock($type_slug) {
    global $wpdb;

    $name = self::PREFIX . $type_slug;
    $now = (string) time();

    // Only one request can create the row; for every other one this inserts nothing
    $inserted = $wpdb->query(
      $wpdb->prepare(
        "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
        $name,
        $now
      )
    );
    if ($inserted) {
      return true;
    }

    $since = (int) $wpdb->get_var(
      $wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name)
    );
    if ($since <= 0 || $since >= time() - self::STALE_AFTER) {
      return false;
    }

    // Left behind by a request that died. Taken over only if no other request
    // has taken it over first: the update matches the old time or nothing.
    $taken = $wpdb->query(
      $wpdb->prepare(
        "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s",
        $now,
        $name,
        (string) $since
      )
    );

    return (bool) $taken;
  }

  /**
   * Give the lock back
   *
   * @param string $type_slug Collection slug
   * @return void
   */
  private static function release($type_slug) {
    self::$held[$type_slug]--;
    if (self::$held[$type_slug] > 0) {
      return;
    }

    unset(self::$held[$type_slug], self::$touched[$type_slug]);
    delete_option(self::PREFIX . $type_slug);
  }
}
