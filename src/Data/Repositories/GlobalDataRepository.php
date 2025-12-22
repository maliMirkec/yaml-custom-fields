<?php

/**
 * Global Data Repository
 * Handle all global/site-wide data operations
 */

namespace YamlCF\Data\Repositories;


// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
class GlobalDataRepository {
  /**
   * Get global data
   *
   * @return array Global data
   */
  public function getData() {
    $data = get_option('yaml_cf_global_data', []);
    return is_array($data) ? $data : [];
  }

  /**
   * Save global data
   *
   * @param array $data Data to save
   * @return bool Success
   */
  public function saveData($data) {
    return update_option('yaml_cf_global_data', $data, false);
  }

  /**
   * Get global schema
   *
   * @return string YAML schema
   */
  public function getSchema() {
    return get_option('yaml_cf_global_schema', '');
  }

  /**
   * Save global schema
   *
   * @param string $schema YAML schema
   * @return bool Success
   */
  public function saveSchema($schema) {
    return update_option('yaml_cf_global_schema', $schema, false);
  }

  /**
   * Delete global data
   *
   * @return bool Success
   */
  public function deleteData() {
    return delete_option('yaml_cf_global_data');
  }

  /**
   * Delete global schema
   *
   * @return bool Success
   */
  public function deleteSchema() {
    return delete_option('yaml_cf_global_schema');
  }
}
