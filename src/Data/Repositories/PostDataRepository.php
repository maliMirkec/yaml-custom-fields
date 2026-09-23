<?php

/**
 * Post Data Repository
 * Handle all post meta operations for YAML custom fields
 */

namespace YamlCF\Data\Repositories;


// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
class PostDataRepository {
  /**
   * Get YAML custom field data for a post
   *
   * @param int $post_id Post ID
   * @return array Post data
   */
  public function getData($post_id) {
    $data = get_post_meta($post_id, '_yaml_cf_data', true);
    return is_array($data) ? $data : [];
  }

  /**
   * Save YAML custom field data for a post
   *
   * @param int $post_id Post ID
   * @param array $data Data to save
   * @return bool Success
   */
  public function saveData($post_id, $data) {
    // update_post_meta() unslashes its value; slash first so backslashes survive
    return update_post_meta($post_id, '_yaml_cf_data', wp_slash($data));
  }

  /**
   * Delete YAML custom field data for a post
   *
   * @param int $post_id Post ID
   * @return bool Success
   */
  public function deleteData($post_id) {
    return delete_post_meta($post_id, '_yaml_cf_data');
  }

  /**
   * Get use template global flag for a post
   *
   * @param int $post_id Post ID
   * @return bool Whether to use template global
   */
  public function getUseTemplateGlobal($post_id) {
    return (bool) get_post_meta($post_id, '_yaml_cf_use_template_global', true);
  }

  /**
   * Set use template global flag for a post
   *
   * @param int $post_id Post ID
   * @param bool $use_global Whether to use template global
   * @return bool Success
   */
  public function setUseTemplateGlobal($post_id, $use_global) {
    return update_post_meta($post_id, '_yaml_cf_use_template_global', $use_global);
  }

  /**
   * Get use template global fields for a post
   *
   * @param int $post_id Post ID
   * @return array Field names to use from template global
   */
  public function getUseTemplateGlobalFields($post_id) {
    $fields = get_post_meta($post_id, '_yaml_cf_use_template_global_fields', true);
    return is_array($fields) ? $fields : [];
  }

  /**
   * Set use template global fields for a post
   *
   * @param int $post_id Post ID
   * @param array $fields Field names to use from template global
   * @return bool Success
   */
  public function setUseTemplateGlobalFields($post_id, $fields) {
    return update_post_meta($post_id, '_yaml_cf_use_template_global_fields', wp_slash($fields));
  }

  /**
   * Post types that can hold YAML CF data (public types, as in the post editor meta box)
   *
   * @return string[] Post type names
   */
  public function getSupportedPostTypes() {
    $post_types = get_post_types(['public' => true]);
    unset($post_types['attachment']);
    return array_values($post_types);
  }

  /**
   * Query posts that have YAML CF data, paginated in SQL
   *
   * The meta query uses the indexed meta_key column; it replaces loading every post
   * and filtering in PHP.
   *
   * @param array $args {
   *   @type string|string[] $post_type        Post types (default: supported post types)
   *   @type string|string[] $post_status      Post statuses (default: publish, draft, pending, private, future)
   *   @type int             $paged            Page number (default 1)
   *   @type int             $per_page         Posts per page, -1 for all (default 20)
   *   @type bool            $require_imported Only posts marked as imported (default false)
   *   @type string          $fields           'all' or 'ids' (default 'all')
   * }
   * @return array ['posts' => WP_Post[]|int[], 'total' => int, 'total_pages' => int, 'paged' => int]
   */
  public function queryPostsWithData($args = []) {
    $per_page = isset($args['per_page']) ? (int) $args['per_page'] : 20;
    if ($per_page === 0 || $per_page < -1) {
      $per_page = 20;
    }
    $paged = isset($args['paged']) ? max(1, (int) $args['paged']) : 1;

    $meta_query = [
      ['key' => '_yaml_cf_data', 'compare' => 'EXISTS'],
    ];
    if (!empty($args['require_imported'])) {
      $meta_query['relation'] = 'AND';
      $meta_query[] = ['key' => '_yaml_cf_imported', 'compare' => 'EXISTS'];
    }

    $query = new \WP_Query([
      'post_type' => isset($args['post_type']) ? $args['post_type'] : $this->getSupportedPostTypes(),
      'post_status' => isset($args['post_status']) ? $args['post_status'] : ['publish', 'draft', 'pending', 'private', 'future'],
      'posts_per_page' => $per_page,
      'paged' => $paged,
      'orderby' => ['post_type' => 'ASC', 'title' => 'ASC'],
      'meta_query' => $meta_query,
      'fields' => isset($args['fields']) && $args['fields'] === 'ids' ? 'ids' : 'all',
      'no_found_rows' => $per_page === -1,
      'ignore_sticky_posts' => true,
      'update_post_term_cache' => false,
    ]);

    $posts = $query->posts;
    return [
      'posts' => $posts,
      'total' => $per_page === -1 ? count($posts) : (int) $query->found_posts,
      'total_pages' => $per_page === -1 ? 1 : max(1, (int) $query->max_num_pages),
      'paged' => $paged,
    ];
  }
}
