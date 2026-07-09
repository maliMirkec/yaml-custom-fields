<?php

namespace YamlCF\Admin\Controllers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

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
   * Localize page initialization data
   * Used to pass data to admin-page-init.js (replaces inline scripts)
   *
   * @param array $data Data to pass to page initialization script
   * @param string $object_name JavaScript object name (default: yamlCFPageInit)
   */
  protected function localizePageInit($data, $object_name = 'yamlCFPageInit') {
    wp_localize_script('yaml-cf-admin-page-init', $object_name, $data);
  }

  /**
   * Render a fallback notice when a required selection (type_id, template, etc.)
   * is missing or invalid.
   *
   * This is a defensive fallback only: the normal path redirects on admin_init
   * (see HookManager::redirectInvalidPageSelections()), before WordPress sends
   * any output, so this should rarely execute. It exists so a render() method
   * never falls through to an actually-blank page if it's ever reached with an
   * invalid selection.
   *
   * @param string $title Page title
   * @param string $message Notice message
   * @param string $back_url URL for the "go back" link
   * @param string $back_label Label for the "go back" link
   */
  protected function renderSelectionRequiredNotice($title, $message, $back_url, $back_label) {
    ?>
    <div class="wrap">
      <div class="yaml-cf-admin-container">
        <div class="yaml-cf-header">
          <div class="yaml-cf-header-content">
            <img src="<?php echo esc_url(YAML_CF_PLUGIN_URL . 'assets/icon-256x256.png'); ?>" alt="YAML Custom Fields" class="yaml-cf-logo" />
            <div class="yaml-cf-header-text">
              <h1><?php echo esc_html($title); ?></h1>
            </div>
          </div>
        </div>
        <div class="notice notice-warning inline">
          <p><?php echo esc_html($message); ?></p>
        </div>
        <p>
          <a href="<?php echo esc_url($back_url); ?>" class="button button-primary">
            <?php echo esc_html($back_label); ?>
          </a>
        </p>
      </div>
    </div>
    <?php
  }

  /**
   * Render the admin page
   * Must be implemented by child classes
   */
  abstract public function render();
}
