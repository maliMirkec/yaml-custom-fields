<?php
/**
 * Post Data Repository
 * Handle all post meta operations for YAML custom fields
 */

namespace YamlCF\Data\Repositories;

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
    return update_post_meta($post_id, '_yaml_cf_data', $data);
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
    return update_post_meta($post_id, '_yaml_cf_use_template_global_fields', $fields);
  }
}
