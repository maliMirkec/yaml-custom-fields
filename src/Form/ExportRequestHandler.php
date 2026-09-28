<?php

/**
 * Export Request Handler
 * The download links: one post's fields, the settings, page content, collections
 */

namespace YamlCF\Form;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use YamlCF\Helpers\RequestHelper;

/**
 * Each checks its nonce and the user's permission before anything is read, and
 * a download ends the request.
 */
class ExportRequestHandler {
  public function singlePost() {
    // Skip during plugin management actions
    if (FormHandler::shouldSkip()) {
      return;
    }

    // Check if this is actually a post export request (not just any page with a nonce)
    $post_id = RequestHelper::getParamInt('yaml_cf_export_post', 0);
    if (!$post_id) {
      return;
    }

    // Now get and verify the nonce (only if we have the specific export parameter)
    $nonce = RequestHelper::getParam('_wpnonce');
    if (!$nonce) {
      return;
    }

    // Verify nonce BEFORE any operations
    if (!wp_verify_nonce($nonce, 'yaml_cf_export_post_' . $post_id)) {
      wp_die(esc_html__('This link has expired. Go back, reload the page and try again.', 'yaml-custom-fields'));
    }

    if (!current_user_can('edit_post', $post_id)) {
      wp_die(esc_html__('This action needs administrator permissions.', 'yaml-custom-fields'));
    }

    $post = get_post($post_id);
    if (!$post) {
      wp_die(esc_html__('This post does not exist.', 'yaml-custom-fields'));
    }

    $exporter = $this->service('page_data_exporter');
    $export_data = $exporter->exportPost($post_id);
    if (!$export_data) {
      wp_die(esc_html__('This post has no field content to export.', 'yaml-custom-fields'));
    }

    $exporter->download($export_data, 'yaml-cs-content-' . sanitize_file_name($post->post_name) . '-' . gmdate('Y-m-d-H-i-s') . '.json');
    exit;
  }
  public function settings() {
    // Skip during plugin management actions
    if (FormHandler::shouldSkip()) {
      return;
    }

    // Check if this is actually a settings export request (not just any page with a nonce)
    if (!RequestHelper::getParam('yaml_cf_export_settings')) {
      return;
    }

    // Now get and verify the nonce (only if we have the specific export parameter)
    $nonce = RequestHelper::getParam('_wpnonce');
    if (!$nonce) {
      return;
    }

    if (!wp_verify_nonce($nonce, 'yaml_cf_export_settings')) {
      wp_die(esc_html__('This link has expired. Go back, reload the page and try again.', 'yaml-custom-fields'));
    }

    if (!current_user_can('manage_options')) {
      wp_die(esc_html__('This action needs administrator permissions.', 'yaml-custom-fields'));
    }

    $exporter = $this->service('settings_exporter');
    $exporter->download($exporter->export());
    exit;
  }
  public function pageData() {
    // Skip during plugin management actions
    if (FormHandler::shouldSkip()) {
      return;
    }

    if (!isset($_POST['yaml_cf_export_page_data_nonce'])) {
      return;
    }

    if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['yaml_cf_export_page_data_nonce'])), 'yaml_cf_export_page_data')) {
      wp_die(esc_html__('This link has expired. Go back, reload the page and try again.', 'yaml-custom-fields'));
    }

    if (!current_user_can('manage_options')) {
      wp_die(esc_html__('This action needs administrator permissions.', 'yaml-custom-fields'));
    }

    $post_ids = isset($_POST['post_ids']) && is_array($_POST['post_ids']) ? array_filter(array_map('absint', wp_unslash($_POST['post_ids']))) : [];
    $match_by = isset($_POST['match_by']) ? sanitize_text_field(wp_unslash($_POST['match_by'])) : 'slug';

    if (empty($post_ids)) {
      wp_die(esc_html__('Choose at least one post.', 'yaml-custom-fields'));
    }

    $exporter = $this->service('page_data_exporter');
    $exporter->download($exporter->exportMultiple($post_ids, $match_by), 'yaml-cs-partials-' . gmdate('Y-m-d-H-i-s') . '.json');
    exit;
  }
  public function dataObjects() {
    // Skip during plugin management actions
    if (FormHandler::shouldSkip()) {
      return;
    }

    if (!isset($_POST['yaml_cf_export_data_objects_nonce'])) {
      return;
    }

    if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['yaml_cf_export_data_objects_nonce'])), 'yaml_cf_export_data_objects')) {
      wp_die(esc_html__('This link has expired. Go back, reload the page and try again.', 'yaml-custom-fields'));
    }

    if (!current_user_can('manage_options')) {
      wp_die(esc_html__('This action needs administrator permissions.', 'yaml-custom-fields'));
    }

    $exporter = $this->service('data_object_exporter');
    $exporter->download($exporter->export());
    exit;
  }
  /**
   * A service from the container, looked up only when a form needs it
   *
   * @param string $name Service name
   * @return mixed
   */
  private function service($name) {
    return \YamlCF\Core\Plugin::getInstance()->get($name);
  }
}
