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
   * Whether saving site-wide content clears the cache of every page with fields
   *
   * Site details, shared content and site parts show on many pages at once.
   * clean_post_cache() on each of those pages is what tells a page-cache plugin
   * to purge them, but on a large site it is one call per page, every save.
   * On by default, which is how the plugin has always behaved.
   */
  const CLEAR_ALL_POSTS_OPTION = 'yaml_cf_clear_post_caches_on_site_save';

  /**
   * Whether a site-wide save should clear every post's cache
   *
   * @return bool
   */
  public static function clearsAllPostsOnSiteSave() {
    // Stored as '1' or '0'. update_option() skips saving a false that matches a
    // missing option, so a plain false could never switch this off.
    $enabled = (string) get_option(self::CLEAR_ALL_POSTS_OPTION, '1') !== '0';

    /**
     * Whether saving site details, shared content or a site part clears the
     * cache of every page that has fields.
     *
     * @param bool $enabled The choice made on the plugin's Settings screen.
     */
    return (bool) apply_filters('yaml_cf_clear_post_caches_on_site_save', $enabled);
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
   * Clear plugin data caches after field data changes
   *
   * @param int|null $post_id Specific post to clear, or null for every post with YAML CF data
   */
  public function clearDataCaches($post_id = null) {
    // Values already worked out for this request (yaml_cf_resolve_post_fields)
    \YamlCF\Core\Plugin::getInstance()->get('field_resolver')->flush($post_id);

    // Admin list caches
    wp_cache_delete('yaml_cf_validation_posts', 'yaml-custom-fields');
    wp_cache_delete('yaml_cf_posts_with_data', 'yaml-custom-fields');

    if ($post_id) {
      wp_cache_delete($post_id, 'post_meta');
      clean_post_cache($post_id);
      return;
    }

    // Content shown on every page changed. Field values are always read fresh
    // from the options, so skipping the pages below only means a page-cache
    // plugin is not told to purge them.
    if (!self::clearsAllPostsOnSiteSave()) {
      /**
       * Fires after site-wide content is saved without clearing each page's
       * cache, so a page-cache plugin can be purged in one go instead.
       */
      do_action('yaml_cf_site_content_saved');
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
