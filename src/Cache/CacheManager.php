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
}
