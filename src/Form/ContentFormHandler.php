<?php

/**
 * Content Form Handler
 * Saving a site part, the site details, or a template's shared content
 */

namespace YamlCF\Form;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use YamlCF\Helpers\RequestHelper;

/**
 * Each is sanitised against its schema, as a page is, and a field dropped from
 * the schema keeps its content, as on a page.
 */
class ContentFormHandler {
  private $notifications;
  private $parser;
  private $sanitizer;
  private $schemaDiff;
  private $cacheManager;

  /**
   * @param \YamlCF\Core\NotificationManager $notifications Queued notices
   * @param \YamlCF\Schema\SchemaParser $parser Reads the schema
   * @param \YamlCF\Form\DataSanitizer $sanitizer Cleans values against it
   * @param \YamlCF\Schema\SchemaDiff $schemaDiff Keeps values of dropped fields
   * @param \YamlCF\Cache\CacheManager $cacheManager Clears what the change affects
   */
  public function __construct($notifications, $parser, $sanitizer, $schemaDiff, $cacheManager) {
    $this->notifications = $notifications;
    $this->parser = $parser;
    $this->sanitizer = $sanitizer;
    $this->schemaDiff = $schemaDiff;
    $this->cacheManager = $cacheManager;
  }

  /**
   * Save a site part's content
   *
   * @return void
   */
  public function savePartialData() {
    // Handle partial data save
    if (isset($_POST['yaml_cf_partial_nonce'])) {
      if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['yaml_cf_partial_nonce'])), 'yaml_cf_save_partial')) {
        RequestHelper::stopStaleForm();
      }

      if (!current_user_can('manage_options')) {
        wp_die(esc_html__('This action needs administrator permissions.', 'yaml-custom-fields'));
      }

      $template = isset($_POST['template']) ? sanitize_text_field(wp_unslash($_POST['template'])) : '';
      $field_data = [];

      // Get schema for validation
      $schemas = get_option('yaml_cf_schemas', []);
      $schema = null;
      if (isset($schemas[$template])) {
        $schema = $this->parser->parse($schemas[$template]);
      }

      // Collect all field data
      // Use post_raw() to get unslashed data safely without PHPCS warnings
      // Data will be sanitized against the schema by YamlCF\Form\DataSanitizer
      $posted_data = RequestHelper::postRaw('yaml_cf', []);
      if (!empty($posted_data) && is_array($posted_data)) {
        $field_data = $this->sanitizer->sanitize($posted_data, $schema);
      }

      $partial_data = get_option('yaml_cf_partial_data', []);

      // A field dropped from the schema keeps its content, the same as on a
      // page. Restoring before the write also means "nothing posted" is no
      // longer the same as "delete everything", which is what it used to mean
      // here: these screens wrote an empty array whenever a form sent nothing.
      $field_data = $this->schemaDiff->restoreStranded(
        $field_data,
        isset($partial_data[$template]) && is_array($partial_data[$template]) ? $partial_data[$template] : [],
        $schema
      );

      $partial_data[$template] = $field_data;
      update_option('yaml_cf_partial_data', $partial_data, false);

      // Clear caches
      $this->cacheManager->clearDataCaches();

      /* translators: %s: template file name */
      $this->notify('success', sprintf(__('Content for %s saved.', 'yaml-custom-fields'), $template));

      wp_safe_redirect(add_query_arg([
        'page' => 'yaml-cf-edit-partial',
        'template' => rawurlencode($template)
      ], admin_url('admin.php')));
      exit;
    }
  }

  /**
   * Save the site details
   *
   * @return void
   */
  public function saveGlobalData() {
    // Handle global data save
    if (isset($_POST['yaml_cf_global_data_nonce'])) {
      if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['yaml_cf_global_data_nonce'])), 'yaml_cf_save_global_data')) {
        RequestHelper::stopStaleForm();
      }

      if (!current_user_can('manage_options')) {
        wp_die(esc_html__('This action needs administrator permissions.', 'yaml-custom-fields'));
      }

      $field_data = [];

      // Get global schema for validation
      $global_schema_yaml = get_option('yaml_cf_global_schema', '');
      $global_schema = null;
      if (!empty($global_schema_yaml)) {
        $global_schema = $this->parser->parse($global_schema_yaml);
      }

      // Collect all field data
      // Use post_raw() to get unslashed data safely without PHPCS warnings
      // Data will be sanitized against the schema by YamlCF\Form\DataSanitizer
      $posted_data = RequestHelper::postRaw('yaml_cf', []);
      if (!empty($posted_data) && is_array($posted_data)) {
        $field_data = $this->sanitizer->sanitize($posted_data, $global_schema);
      }

      // A field dropped from the schema keeps its content, the same as on a
      // page. Restoring before the write also means "nothing posted" is no
      // longer the same as "delete everything", which is what it used to mean
      // here: these screens wrote an empty array whenever a form sent nothing.
      $field_data = $this->schemaDiff->restoreStranded(
        $field_data,
        (array) get_option('yaml_cf_global_data', []),
        $global_schema
      );

      update_option('yaml_cf_global_data', $field_data, false);

      // Clear caches
      $this->cacheManager->clearDataCaches();

      $this->notify('success', __('Site details saved.', 'yaml-custom-fields'));

      wp_safe_redirect(add_query_arg([
        'page' => 'yaml-cf-manage-global-data'
      ], admin_url('admin.php')));
      exit;
    }
  }

  /**
   * Save a template's shared content
   *
   * @return void
   */
  public function saveTemplateGlobalData() {
    // Handle template global data save
    if (isset($_POST['yaml_cf_save_template_global_data_nonce'])) {
      if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['yaml_cf_save_template_global_data_nonce'])), 'yaml_cf_save_template_global_data')) {
        RequestHelper::stopStaleForm();
      }

      if (!current_user_can('manage_options')) {
        wp_die(esc_html__('This action needs administrator permissions.', 'yaml-custom-fields'));
      }

      $template = isset($_POST['template']) ? sanitize_text_field(wp_unslash($_POST['template'])) : '';
      $field_data = [];

      // Get template global schema for validation
      $template_global_schemas = get_option('yaml_cf_template_global_schemas', []);
      $template_global_schema = null;
      if (isset($template_global_schemas[$template]) && !empty($template_global_schemas[$template])) {
        $template_global_schema = $this->parser->parse($template_global_schemas[$template]);
      }

      // Collect all field data
      // Use post_raw() to get unslashed data safely without PHPCS warnings
      // Data will be sanitized against the schema by YamlCF\Form\DataSanitizer
      $posted_data = RequestHelper::postRaw('yaml_cf', []);
      if (!empty($posted_data) && is_array($posted_data)) {
        $field_data = $this->sanitizer->sanitize($posted_data, $template_global_schema);
      }

      $template_global_data = get_option('yaml_cf_template_global_data', []);

      // A field dropped from the schema keeps its content, the same as on a
      // page. Restoring before the write also means "nothing posted" is no
      // longer the same as "delete everything", which is what it used to mean
      // here: these screens wrote an empty array whenever a form sent nothing.
      $field_data = $this->schemaDiff->restoreStranded(
        $field_data,
        isset($template_global_data[$template]) && is_array($template_global_data[$template]) ? $template_global_data[$template] : [],
        $template_global_schema
      );

      $template_global_data[$template] = $field_data;
      update_option('yaml_cf_template_global_data', $template_global_data, false);

      // Clear caches
      $this->cacheManager->clearDataCaches();

      /* translators: %s: template file name */
      $this->notify('success', sprintf(__('Shared content for %s saved.', 'yaml-custom-fields'), $template));

      wp_safe_redirect(add_query_arg([
        'page' => 'yaml-cf-manage-template-global',
        'template' => rawurlencode($template)
      ], admin_url('admin.php')));
      exit;
    }
  }

  /**
   * Queue a notice for the current user, shown on the next page load
   *
   * @param string $type success|error|warning|info
   * @param string $message Message
   * @param array $args Optional 'title' and 'details'
   * @return void
   */
  private function notify($type, $message, $args = []) {
    $this->notifications->add($message, $type, $args);
  }
}
