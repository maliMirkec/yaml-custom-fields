<?php
namespace YamlCF\Admin\Controllers;

/**
 * Base class for admin page controllers
 */
abstract class AdminController {
  /**
   * Check if current user has required capability
   *
   * @param string $capability Required capability (default: manage_options)
   */
  protected function checkPermission($capability = 'manage_options') {
    if (!current_user_can($capability)) {
      wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'yaml-custom-fields'));
    }
  }

  /**
   * Load a template file
   *
   * @param string $template_name Template file name (without path)
   * @param array $data Data to extract for template
   */
  protected function loadTemplate($template_name, $data = []) {
    // Extract data for use in template
    if (!empty($data)) {
      extract($data, EXTR_SKIP);
    }

    $template_path = YAML_CF_PLUGIN_DIR . 'templates/' . $template_name;
    if (file_exists($template_path)) {
      include $template_path;
    } else {
      wp_die(esc_html__('Template file not found.', 'yaml-custom-fields'));
    }
  }

  /**
   * Localize JavaScript data
   *
   * @param array $data Data to localize
   */
  protected function localizeScript($data = []) {
    $defaults = [
      'ajax_url' => admin_url('admin-ajax.php'),
      'admin_url' => admin_url(),
      'nonce' => wp_create_nonce('yaml_cf_nonce'),
    ];

    $localized_data = array_merge($defaults, $data);
    wp_localize_script('yaml-cf-admin', 'yamlCF', $localized_data);
  }

  /**
   * Render the admin page
   * Must be implemented by child classes
   */
  abstract public function render();
}
