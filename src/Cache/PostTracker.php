<?php
/**
 * Post Tracker
 * Track posts that have YAML custom field data for efficient cache clearing
 */

namespace YamlCF\Cache;

class PostTracker {
  /**
   * Track post ID that has YAML custom field data
   * This maintains a list for efficient cache clearing without slow meta_query
   *
   * @param int $post_id Post ID to track
   * @return void
   */
  public static function track($post_id) {
    $tracked_posts = get_option('yaml_cf_tracked_posts', []);

    if (!in_array($post_id, $tracked_posts, true)) {
      $tracked_posts[] = $post_id;
      update_option('yaml_cf_tracked_posts', array_unique($tracked_posts), false);
    }
  }

  /**
   * Remove post ID from tracking when YAML data is deleted
   *
   * @param int $post_id Post ID to untrack
   * @return void
   */
  public static function untrack($post_id) {
    $tracked_posts = get_option('yaml_cf_tracked_posts', []);
    $key = array_search($post_id, $tracked_posts, true);

    if ($key !== false) {
      unset($tracked_posts[$key]);
      update_option('yaml_cf_tracked_posts', array_values($tracked_posts), false);
    }
  }

  /**
   * Get all tracked posts
   *
   * @return array Array of post IDs
   */
  public static function getAll() {
    return get_option('yaml_cf_tracked_posts', []);
  }
}
