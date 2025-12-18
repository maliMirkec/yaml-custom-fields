<?php
/**
 * Template Settings Repository
 * Handle all template-related settings (schemas, enabled status, use_global flags)
 */

namespace YamlCF\Data\Repositories;

class TemplateSettingsRepository {
  /**
   * Get all template settings
   *
   * @return array All template settings
   */
  public function getSettings() {
    $settings = get_option('yaml_cf_template_settings', []);
    return is_array($settings) ? $settings : [];
  }

  /**
   * Get a specific setting
   *
   * @param string $key Setting key
   * @param mixed $default Default value
   * @return mixed Setting value
   */
  public function getSetting($key, $default = null) {
    $settings = $this->getSettings();
    return isset($settings[$key]) ? $settings[$key] : $default;
  }

  /**
   * Save a specific setting
   *
   * @param string $key Setting key
   * @param mixed $value Setting value
   * @return bool Success
   */
  public function saveSetting($key, $value) {
    $settings = $this->getSettings();
    $settings[$key] = $value;
    return update_option('yaml_cf_template_settings', $settings, false);
  }

  /**
   * Delete a specific setting
   *
   * @param string $key Setting key
   * @return bool Success
   */
  public function deleteSetting($key) {
    $settings = $this->getSettings();
    if (isset($settings[$key])) {
      unset($settings[$key]);
      return update_option('yaml_cf_template_settings', $settings, false);
    }
    return true;
  }

  /**
   * Get all schemas
   *
   * @return array Schemas from old storage location
   */
  public function getSchemas() {
    $schemas = get_option('yaml_cf_schemas', []);
    return is_array($schemas) ? $schemas : [];
  }

  /**
   * Save all schemas
   *
   * @param array $schemas Schemas to save
   * @return bool Success
   */
  public function saveSchemas($schemas) {
    return update_option('yaml_cf_schemas', $schemas, false);
  }

  /**
   * Get template global data for all templates
   *
   * @return array Template global data indexed by template
   */
  public function getTemplateGlobalData() {
    $data = get_option('yaml_cf_template_global_data', []);
    return is_array($data) ? $data : [];
  }

  /**
   * Get template global data for a specific template
   *
   * @param string $template Template file name
   * @return array Template global data
   */
  public function getTemplateGlobalDataForTemplate($template) {
    $all_data = $this->getTemplateGlobalData();
    return isset($all_data[$template]) && is_array($all_data[$template])
      ? $all_data[$template]
      : [];
  }

  /**
   * Save template global data for a specific template
   *
   * @param string $template Template file name
   * @param array $data Data to save
   * @return bool Success
   */
  public function saveTemplateGlobalDataForTemplate($template, $data) {
    $all_data = $this->getTemplateGlobalData();
    $all_data[$template] = $data;
    return update_option('yaml_cf_template_global_data', $all_data, false);
  }

  /**
   * Delete all settings
   *
   * @return bool Success
   */
  public function deleteAllSettings() {
    return delete_option('yaml_cf_template_settings');
  }

  /**
   * Delete all schemas
   *
   * @return bool Success
   */
  public function deleteAllSchemas() {
    return delete_option('yaml_cf_schemas');
  }

  /**
   * Delete all template global data
   *
   * @return bool Success
   */
  public function deleteAllTemplateGlobalData() {
    return delete_option('yaml_cf_template_global_data');
  }
}
