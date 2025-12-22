<?php

/**
 * Partial Data Repository
 * Handle all partial template data operations
 */

namespace YamlCF\Data\Repositories;


// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
class PartialDataRepository {
  /**
   * Get all partial data
   *
   * @return array Partial data indexed by file name
   */
  public function getAllData() {
    $data = get_option('yaml_cf_partial_data', []);
    return is_array($data) ? $data : [];
  }

  /**
   * Get data for a specific partial
   *
   * @param string $partial_file Partial file name
   * @return array Partial data
   */
  public function getData($partial_file) {
    $all_data = $this->getAllData();
    return isset($all_data[$partial_file]) && is_array($all_data[$partial_file])
      ? $all_data[$partial_file]
      : [];
  }

  /**
   * Save data for a specific partial
   *
   * @param string $partial_file Partial file name
   * @param array $data Data to save
   * @return bool Success
   */
  public function saveData($partial_file, $data) {
    $all_data = $this->getAllData();
    $all_data[$partial_file] = $data;
    return update_option('yaml_cf_partial_data', $all_data, false);
  }

  /**
   * Delete data for a specific partial
   *
   * @param string $partial_file Partial file name
   * @return bool Success
   */
  public function deleteData($partial_file) {
    $all_data = $this->getAllData();
    if (isset($all_data[$partial_file])) {
      unset($all_data[$partial_file]);
      return update_option('yaml_cf_partial_data', $all_data, false);
    }
    return true;
  }

  /**
   * Delete all partial data
   *
   * @return bool Success
   */
  public function deleteAllData() {
    return delete_option('yaml_cf_partial_data');
  }
}
