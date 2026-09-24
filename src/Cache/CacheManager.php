<?php

namespace YamlCF\Cache;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * General cache management for the plugin
 */
class CacheManager {
  /**
   * Clear all plugin caches
   */
  public function clearAll() {
    // Clear template cache
    $this->clearTemplateCache();

    // Clear post meta cache for tracked posts
    $this->clearPostMetaCache();

    do_action('yaml_cf_cache_cleared');
  }

  /**
   * Clear template cache
   */
  public function clearTemplateCache() {
    $cache_key = 'yaml_cf_templates_' . get_stylesheet();
    delete_transient($cache_key);

    do_action('yaml_cf_template_cache_cleared');
  }

  /**
   * Clear post meta cache for all tracked posts
   */
  public function clearPostMetaCache() {
    $tracked_posts = get_option('yaml_cf_tracked_posts', []);

    foreach ($tracked_posts as $post_id) {
      wp_cache_delete($post_id, 'post_meta');
    }

    do_action('yaml_cf_post_meta_cache_cleared', count($tracked_posts));
  }

  /**
   * Clear post meta cache for a specific post
   *
   * @param int $post_id Post ID
   */
  public function clearPostCache($post_id) {
    wp_cache_delete($post_id, 'post_meta');

    do_action('yaml_cf_post_cache_cleared', $post_id);
  }

  /**
   * Clear plugin data caches after field data changes
   *
   * @param int|null $post_id Specific post to clear, or null for every post with YAML CF data
   */
  public function clearDataCaches($post_id = null) {
    // Values already worked out for this request (yaml_cf_resolve_post_fields)
    if (function_exists('yaml_cf_flush_resolved_post_fields')) {
      yaml_cf_flush_resolved_post_fields($post_id);
    }

    // Admin list caches
    wp_cache_delete('yaml_cf_validation_posts', 'yaml-custom-fields');
    wp_cache_delete('yaml_cf_posts_with_data', 'yaml-custom-fields');

    if ($post_id) {
      wp_cache_delete($post_id, 'post_meta');
      clean_post_cache($post_id);
      return;
    }

    $post_ids = PostTracker::getAll();
    if (empty($post_ids)) {
      // Tracking is empty (e.g. data saved by older versions): find the posts by meta key
      $repository = new \YamlCF\Data\Repositories\PostDataRepository();
      $result = $repository->queryPostsWithData([
        'post_type' => 'any',
        'post_status' => 'any',
        'per_page' => -1,
        'fields' => 'ids',
      ]);
      $post_ids = $result['posts'];
    }

    foreach ($post_ids as $pid) {
      wp_cache_delete($pid, 'post_meta');
      clean_post_cache($pid);
    }
  }
}
