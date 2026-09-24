<?php
/**
 * Plugin Name: YAML Custom Fields
 * Plugin URI: https://github.com/maliMirkec/yaml-custom-fields
 * Description: Add your own fields to pages, posts and every part of your site. You choose the fields; writers fill them in.
 * Version: 1.3.0
 * Author: Studio Cita
 * Author URI: https://www.cita.hr
 * License: GPL v2 or later
 * Text Domain: yaml-custom-fields
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
  exit;
}

// PHP version check (must be before autoloader loads Symfony)
if (version_compare(PHP_VERSION, '8.1.0', '<')) {
  add_action('admin_notices', function() {
    echo '<div class="notice notice-error"><p>';
    echo '<strong>YAML Custom Fields:</strong> This plugin needs PHP 8.1 or newer. This site runs PHP ' . esc_html(PHP_VERSION) . '. Ask the hosting provider to upgrade PHP.';
    echo '</p></div>';
  });
  return; // Stop loading the plugin
}

// Load scoped Composer dependencies to avoid conflicts with other plugins
if (file_exists(__DIR__ . '/build/vendor/scoper-autoload.php')) {
  require_once __DIR__ . '/build/vendor/scoper-autoload.php';
} else {
  add_action('admin_notices', function() {
    echo '<div class="notice notice-error"><p>';
    echo '<strong>YAML Custom Fields:</strong> Dependencies not found. Please run <code>./build-scoped.sh</code> in the plugin directory to build scoped dependencies.';
    echo '</p></div>';
  });
  return;
}

use YamlCF\Vendor\Symfony\Component\Yaml\Yaml;
use YamlCF\Vendor\Symfony\Component\Yaml\Exception\ParseException;

// Read version from plugin header (single source of truth)
$yaml_cf_plugin_headers = get_file_data(__FILE__, ['Version' => 'Version']);
define('YAML_CF_VERSION', $yaml_cf_plugin_headers['Version']);
define('YAML_CF_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('YAML_CF_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Text domain and translations
 *
 * Since WordPress 4.6, translations are automatically loaded from WordPress.org
 * for plugins hosted on the official repository. The 'Domain Path' header above
 * tells WordPress where to find translation files.
 *
 * For local development, you can place .mo files in the /languages/ directory.
 */

class YAML_Custom_Fields {
  private static $instance = null;

  public static function get_instance() {
    if (null === self::$instance) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  private function __construct() {
    $this->init_hooks();
  }

  /**
   * Get sanitized GET parameter (string)
   *
   * Uses PHP's filter_input() for safe parameter access without phpcs suppressions.
   * Nonce verification is not required here as this only reads GET parameters.
   * Methods that perform actions must verify nonces separately.
   *
   * @param string $key Parameter key
   * @param string $default Default value
   * @return string Sanitized value
   */
  public static function get_param($key, $default = '') {
    return \YamlCF\Helpers\RequestHelper::getParam($key, $default);
  }

  /**
   * Get integer GET parameter
   *
   * @param string $key Parameter key
   * @param int $default Default value
   * @return int Validated integer
   */
  public static function get_param_int($key, $default = 0) {
    return \YamlCF\Helpers\RequestHelper::getParamInt($key, $default);
  }

  /**
   * Get sanitized key from GET parameter
   *
   * @param string $key Parameter key
   * @param string $default Default value
   * @return string Sanitized key
   */
  public static function get_param_key($key, $default = '') {
    return \YamlCF\Helpers\RequestHelper::getParamKey($key, $default);
  }

  /**
   * Log debug message - only when WP_DEBUG_LOG is enabled
   * Uses do_action() for extensible logging without development functions
   */
  private static function log_debug($message) {
    if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
      // Allow developers to hook into logging
      do_action('yaml_cf_log_debug', $message);
    }
  }

  /**
   * Log error message - uses WordPress hooks for production-safe logging
   */
  private static function log_error($message) {
    // Allow developers to hook into error logging
    do_action('yaml_cf_log_error', $message);

    // Also trigger WordPress admin notice for critical errors
    if (is_admin()) {
      add_action('admin_notices', function() use ($message) {
        echo '<div class="notice notice-error"><p><strong>YAML Custom Fields Error:</strong> ' . esc_html($message) . '</p></div>';
      });
    }
  }

  /**
   * Get POST data with basic sanitization for further processing
   * Use this when data will be sanitized by a custom function (e.g., parse_yaml_schema, sanitize_field_data)
   *
   * Note: Caller must verify nonce before using this method
   *
   * @param string $key POST key to retrieve
   * @param mixed $default Default value if key doesn't exist
   * @return mixed Sanitized POST data
   */
  public static function post_raw($key, $default = '') {
    return \YamlCF\Helpers\RequestHelper::postRaw($key, $default);
  }

  /**
   * Get sanitized POST data
   */
  public static function post_sanitized($key, $default = '', $callback = 'sanitize_text_field') {
    return \YamlCF\Helpers\RequestHelper::postSanitized($key, $default, $callback);
  }

  /**
   * Queue a notice for the current user (shown as an admin notice on the next page load)
   *
   * @param string $type success|error|warning|info
   * @param string $message Message
   * @param array $args Optional 'title' and 'details'
   */
  private function notify($type, $message, $args = []) {
    \YamlCF\Core\Plugin::getInstance()->get('notification_manager')->add($message, $type, $args);
  }

  /**
   * Field names that a schema change is about to strand
   *
   * @param string $old_yaml The schema being replaced
   * @param string $new_yaml The schema being saved
   * @return string[] Field names present in the old schema but not the new one
   */
  private function get_removed_schema_fields($old_yaml, $new_yaml) {
    if (!is_string($old_yaml) || trim($old_yaml) === '') {
      return [];
    }

    $plugin = \YamlCF\Core\Plugin::getInstance();

    return $plugin->get('schema_diff')->removedFieldNames(
      yaml_cf_parse_schema_cached($old_yaml),
      is_string($new_yaml) && trim($new_yaml) !== '' ? yaml_cf_parse_schema_cached($new_yaml) : null
    );
  }

  /**
   * Say which fields lost their place, so vanished content is never a surprise
   *
   * The values stay in the database, so putting the field name back brings the
   * content back with it. That is worth saying: the alternative is an editor
   * who thinks the text is gone.
   *
   * @param string[] $removed_fields Field names no longer in the schema
   * @return void
   */
  private function notify_removed_schema_fields($removed_fields) {
    if (empty($removed_fields)) {
      return;
    }

    $this->notify(
      'warning',
      sprintf(
        /* translators: %s: comma-separated list of field names */
        _n(
          'The field %s is no longer in this schema.',
          'These fields are no longer in this schema: %s',
          count($removed_fields),
          'yaml-custom-fields'
        ),
        implode(', ', $removed_fields)
      ),
      [
        'title' => __('Content is no longer shown:', 'yaml-custom-fields'),
        'details' => [
          __('Whatever was filled in for them is still saved, but nothing displays it any more.', 'yaml-custom-fields'),
          __('Adding a field back under the same name brings its content back too.', 'yaml-custom-fields'),
        ],
      ]
    );
  }

  /**
   * Track post ID that has YAML custom field data
   * This maintains a list for efficient cache clearing without slow meta_query
   *
   * @param int $post_id Post ID to track
   */
  private function track_post_with_yaml_data($post_id) {
    \YamlCF\Cache\PostTracker::track($post_id);
  }

  /**
   * Remove post ID from tracking when YAML data is deleted
   *
   * @param int $post_id Post ID to untrack
   */
  private function untrack_post_with_yaml_data($post_id) {
    \YamlCF\Cache\PostTracker::untrack($post_id);
  }

  /**
   * Handle post deletion - remove from tracking
   *
   * @param int $post_id Post ID being deleted
   */
  public function handle_post_deletion($post_id) {
    // Remove from tracking when post is deleted
    $this->untrack_post_with_yaml_data($post_id);
  }

  /**
   * Build HTML attributes string from array
   * Properly escapes all values and returns PHPCS-compliant output
   *
   * @param array $attrs Attributes array
   * @return string Escaped HTML attributes string
   */
  private function build_html_attrs($attrs) {
    return \YamlCF\Helpers\HtmlHelper::buildAttrs($attrs);
  }

  /**
   * Output HTML attributes (escaping already done by build_html_attrs)
   * This wrapper makes it clear to PHPCS that output is safe
   *
   * @param array $attrs Attributes array
   * @return void
   */
  private function output_html_attrs($attrs) {
    \YamlCF\Helpers\HtmlHelper::outputAttrs($attrs);
  }

  /**
   * Check if we should skip admin_init processing
   * Prevents interference with WordPress plugin management actions
   */
  private function should_skip_admin_init() {
    global $pagenow;

    // Skip on plugin management pages (activation, deactivation, updates)
    if (in_array($pagenow, ['plugins.php', 'update.php', 'update-core.php', 'plugin-install.php'], true)) {
      return true;
    }

    // Skip during plugin upload/update operations
    if (isset($_GET['action'])) {
      $action = sanitize_text_field(wp_unslash($_GET['action']));
      if (in_array($action, ['activate', 'deactivate', 'upload-plugin', 'update-selected'], true)) {
        return true;
      }
    }

    // Skip during plugin file operations
    if (isset($_POST['action'])) {
      $action = sanitize_text_field(wp_unslash($_POST['action']));
      if (in_array($action, ['upload-plugin', 'update-plugin'], true)) {
        return true;
      }
    }

    return false;
  }

  private function init_hooks() {
    add_action('admin_init', [$this, 'handle_form_submissions']);
    add_action('admin_init', [$this, 'handle_single_post_export']);
    add_action('admin_init', [$this, 'handle_settings_export']);
    add_action('admin_init', [$this, 'handle_page_data_export']);
    add_action('admin_init', [$this, 'handle_data_objects_export']);
    add_action('admin_init', [$this, 'handle_data_object_type_submissions']);

    // Not registered here any more: the admin menu (admin_menu), the submenu and
    // title customisation (admin_head, admin_title, parent_file, submenu_file),
    // asset enqueuing (admin_enqueue_scripts) and template cache invalidation
    // (switch_theme). YamlCF\Core\HookManager owns all of those now.

    // Registered as a real meta box so the fields appear in the block editor too.
    // edit_form_after_title never fires there, which used to make the plugin
    // classic-editor-only; core posts meta boxes from the block editor as well,
    // so save_schema_data() keeps receiving $_POST['yaml_cf'] unchanged.
    add_action('add_meta_boxes', [$this, 'register_meta_box'], 10, 2);
    add_action('save_post', [$this, 'save_schema_data']);
    add_action('delete_post', [$this, 'handle_post_deletion']);
    // AJAX handlers live in src/Ajax/Handlers and are registered by YamlCF\Core\HookManager
  }



  public function handle_single_post_export() {
    // Skip during plugin management actions
    if ($this->should_skip_admin_init()) {
      return;
    }

    // Check if this is actually a post export request (not just any page with a nonce)
    $post_id = $this->get_param_int('yaml_cf_export_post', 0);
    if (!$post_id) {
      return;
    }

    // Now get and verify the nonce (only if we have the specific export parameter)
    $nonce = $this->get_param('_wpnonce');
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

    $exporter = \YamlCF\Core\Plugin::getInstance()->get('page_data_exporter');
    $export_data = $exporter->exportPost($post_id);
    if (!$export_data) {
      wp_die(esc_html__('This post has no field content to export.', 'yaml-custom-fields'));
    }

    $exporter->download($export_data, 'yaml-cs-content-' . sanitize_file_name($post->post_name) . '-' . gmdate('Y-m-d-H-i-s') . '.json');
    exit;
  }

  public function handle_settings_export() {
    // Skip during plugin management actions
    if ($this->should_skip_admin_init()) {
      return;
    }

    // Check if this is actually a settings export request (not just any page with a nonce)
    if (!$this->get_param('yaml_cf_export_settings')) {
      return;
    }

    // Now get and verify the nonce (only if we have the specific export parameter)
    $nonce = $this->get_param('_wpnonce');
    if (!$nonce) {
      return;
    }

    if (!wp_verify_nonce($nonce, 'yaml_cf_export_settings')) {
      wp_die(esc_html__('This link has expired. Go back, reload the page and try again.', 'yaml-custom-fields'));
    }

    if (!current_user_can('manage_options')) {
      wp_die(esc_html__('This action needs administrator permissions.', 'yaml-custom-fields'));
    }

    $exporter = \YamlCF\Core\Plugin::getInstance()->get('settings_exporter');
    $exporter->download($exporter->export());
    exit;
  }

  public function handle_page_data_export() {
    // Skip during plugin management actions
    if ($this->should_skip_admin_init()) {
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

    $exporter = \YamlCF\Core\Plugin::getInstance()->get('page_data_exporter');
    $exporter->download($exporter->exportMultiple($post_ids, $match_by), 'yaml-cs-partials-' . gmdate('Y-m-d-H-i-s') . '.json');
    exit;
  }

  public function handle_data_objects_export() {
    // Skip during plugin management actions
    if ($this->should_skip_admin_init()) {
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

    $this->export_data_objects();
    exit;
  }

  public function handle_data_object_type_submissions() {
    // Skip during plugin management actions
    if ($this->should_skip_admin_init()) {
      return;
    }

    $plugin = \YamlCF\Core\Plugin::getInstance();
    $controller = $plugin->get('data_object_controller');
    $controller->handleFormSubmissions();
  }

  public function handle_form_submissions() {
    // Skip during plugin management actions
    if ($this->should_skip_admin_init()) {
      return;
    }

    // Handle single post import
    if (isset($_POST['yaml_cf_import_post_nonce']) &&
        wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['yaml_cf_import_post_nonce'])), 'yaml_cf_import_post')) {

      $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

      if (!current_user_can('edit_post', $post_id)) {
        wp_die(esc_html__('This action needs administrator permissions.', 'yaml-custom-fields'));
      }

      $redirect_with_error = function($code) use ($post_id) {
        $error_messages = [
          'no_file' => __('No file was chosen. Choose a .json file exported from YAML Custom Fields.', 'yaml-custom-fields'),
          'upload_failed' => __('The file could not be uploaded. Please try again.', 'yaml-custom-fields'),
          'upload_error' => __('The file could not be uploaded. Please try again.', 'yaml-custom-fields'),
          'file_too_large' => __('The file is larger than 5 MB.', 'yaml-custom-fields'),
          'invalid_extension' => __('Only .json files exported from YAML Custom Fields can be imported.', 'yaml-custom-fields'),
          'invalid_mime' => __('Only .json files exported from YAML Custom Fields can be imported.', 'yaml-custom-fields'),
          'invalid_json' => __('This file is damaged or is not an export file.', 'yaml-custom-fields'),
          'invalid_format' => __('This is not a YAML Custom Fields export file.', 'yaml-custom-fields'),
          'no_data' => __('The file has no field content.', 'yaml-custom-fields'),
          'no_match' => __('The file has no content for this page. Its ID and web address do not match.', 'yaml-custom-fields'),
        ];
        $this->notify('error', isset($error_messages[$code]) ? $error_messages[$code] : __('Something went wrong.', 'yaml-custom-fields'), [
          'title' => __('The import did not work.', 'yaml-custom-fields'),
        ]);
        wp_safe_redirect(add_query_arg([
          'post' => $post_id,
          'action' => 'edit'
        ], admin_url('post.php')));
        exit;
      };

      // Validate that file was uploaded
      if (!isset($_FILES['yaml_cf_import_file']) || empty($_FILES['yaml_cf_import_file']['name'])) {
        $redirect_with_error('no_file');
      }

      // Validate file upload errors
      if (!isset($_FILES['yaml_cf_import_file']['error']) || $_FILES['yaml_cf_import_file']['error'] !== UPLOAD_ERR_OK) {
        $redirect_with_error('upload_error');
      }

      // Validate file size (5MB limit for JSON imports)
      $max_file_size = 5 * 1024 * 1024; // 5MB in bytes
      if (isset($_FILES['yaml_cf_import_file']['size']) && $_FILES['yaml_cf_import_file']['size'] > $max_file_size) {
        $redirect_with_error('file_too_large');
      }

      // Validate file extension
      $filename = sanitize_file_name(wp_unslash($_FILES['yaml_cf_import_file']['name']));
      if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'json') {
        $redirect_with_error('invalid_extension');
      }

      // The temporary path is generated by PHP; is_uploaded_file() confirms it came from this request.
      // The file is only read here and never moved into the public uploads directory.
      $tmp_file_path = isset($_FILES['yaml_cf_import_file']['tmp_name']) ? (string) $_FILES['yaml_cf_import_file']['tmp_name'] : '';
      if ($tmp_file_path === '' || !is_uploaded_file($tmp_file_path)) {
        $redirect_with_error('upload_failed');
      }

      // Validate MIME type server-side (some systems report JSON as text/plain)
      if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $tmp_file_path);
        // No finfo_close(): deprecated in PHP 8.5, and the finfo object is
        // released when $finfo goes out of scope

        if (!in_array($mime_type, ['application/json', 'text/plain'], true)) {
          $redirect_with_error('invalid_mime');
        }
      }

      $json_data = file_get_contents($tmp_file_path);
      if ($json_data === false) {
        $redirect_with_error('upload_failed');
      }

      // Validate JSON content can be decoded
      $import_data = json_decode($json_data, true);

      // Check for JSON parsing errors
      if (json_last_error() !== JSON_ERROR_NONE) {
        $redirect_with_error('invalid_json');
      }

      // Validate data structure and plugin identifier
      if (!$import_data || !isset($import_data['plugin']) || $import_data['plugin'] !== 'yaml-custom-fields') {
        $redirect_with_error('invalid_format');
      }

      // Find this post's entry and import it (sanitized against this site's schema)
      $importer = \YamlCF\Core\Plugin::getInstance()->get('page_data_importer');
      $current_post = get_post($post_id);
      if (!$current_post) {
        $redirect_with_error('no_data');
      }

      $match = $importer->findEntryForPost($import_data, $current_post);
      if (!$match['entry']) {
        $redirect_with_error($match['error']);
      }

      $importer->importEntry($current_post, $match['entry']);

      // Set transient for success message (shows only once)
      $this->notify('success', __('The fields on this page were filled in from the file.', 'yaml-custom-fields'), [
        'title' => __('Import done.', 'yaml-custom-fields'),
      ]);

      // Redirect without message in URL
      wp_safe_redirect(add_query_arg([
        'post' => $post_id,
        'action' => 'edit'
      ], admin_url('post.php')));
      exit;
    }

    // Handle schema save
    if (isset($_POST['yaml_cf_save_schema_nonce']) &&
        wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['yaml_cf_save_schema_nonce'])), 'yaml_cf_save_schema')) {

      if (!current_user_can('manage_options')) {
        wp_die(esc_html__('This action needs administrator permissions.', 'yaml-custom-fields'));
      }

      $template = isset($_POST['template']) ? sanitize_text_field(wp_unslash($_POST['template'])) : '';
      $schema = isset($_POST['schema']) ? sanitize_textarea_field(wp_unslash($_POST['schema'])) : '';

      // Validate YAML syntax before saving
      if (!empty($schema)) {
        $validation_result = $this->validate_yaml_schema($schema, $template);
        if (!$validation_result['valid']) {
          // Store the invalid schema in a transient so we can display it back
          set_transient('yaml_cf_invalid_schema_' . get_current_user_id(), $schema, 60);

          // Redirect back with error message (message kept out of the URL)
          $this->notify('error', $validation_result['message'], [
            'title' => __('Schema not saved:', 'yaml-custom-fields'),
            'details' => [__('The changes are still in the editor below. Fix the problem and save again.', 'yaml-custom-fields')],
          ]);
          wp_safe_redirect(add_query_arg([
            'page' => 'yaml-cf-edit-schema',
            'template' => rawurlencode($template),
            'error' => '1'
          ], admin_url('admin.php')));
          exit;
        }
      }

      $schemas = get_option('yaml_cf_schemas', []);

      // Work out what is about to be stranded before the old schema is gone
      $removed_fields = $this->get_removed_schema_fields(
        isset($schemas[$template]) ? $schemas[$template] : '',
        $schema
      );

      $schemas[$template] = $schema;
      update_option('yaml_cf_schemas', $schemas);

      // Clear any stored invalid schema
      delete_transient('yaml_cf_invalid_schema_' . get_current_user_id());

      /* translators: %s: template file name */
      $this->notify('success', sprintf(__('Schema for %s saved.', 'yaml-custom-fields'), $template));

      $this->notify_removed_schema_fields($removed_fields);

      wp_safe_redirect(add_query_arg([
        'page' => 'yaml-cf-edit-schema',
        'template' => rawurlencode($template)
      ], admin_url('admin.php')));
      exit;
    }

    // Handle partial data save
    if (isset($_POST['yaml_cf_partial_nonce']) &&
        wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['yaml_cf_partial_nonce'])), 'yaml_cf_save_partial')) {

      if (!current_user_can('manage_options')) {
        wp_die(esc_html__('This action needs administrator permissions.', 'yaml-custom-fields'));
      }

      $template = isset($_POST['template']) ? sanitize_text_field(wp_unslash($_POST['template'])) : '';
      $field_data = [];

      // Get schema for validation
      $schemas = get_option('yaml_cf_schemas', []);
      $schema = null;
      if (isset($schemas[$template])) {
        $schema = $this->parse_yaml_schema($schemas[$template]);
      }

      // Collect all field data
      // Use post_raw() to get unslashed data safely without PHPCS warnings
      // Data will be sanitized by schema-aware sanitize_field_data()
      $posted_data = self::post_raw('yaml_cf', []);
      if (!empty($posted_data) && is_array($posted_data)) {
        $field_data = $this->sanitize_field_data($posted_data, $schema);
      }

      $partial_data = get_option('yaml_cf_partial_data', []);
      $partial_data[$template] = $field_data;
      update_option('yaml_cf_partial_data', $partial_data);

      // Clear caches
      $this->clear_data_caches();

      /* translators: %s: template file name */
      $this->notify('success', sprintf(__('Content for %s saved.', 'yaml-custom-fields'), $template));

      wp_safe_redirect(add_query_arg([
        'page' => 'yaml-cf-edit-partial',
        'template' => rawurlencode($template)
      ], admin_url('admin.php')));
      exit;
    }

    // Handle global schema save
    if (isset($_POST['yaml_cf_save_global_schema_nonce']) &&
        wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['yaml_cf_save_global_schema_nonce'])), 'yaml_cf_save_global_schema')) {

      if (!current_user_can('manage_options')) {
        wp_die(esc_html__('This action needs administrator permissions.', 'yaml-custom-fields'));
      }

      $global_schema = isset($_POST['global_schema']) ? sanitize_textarea_field(wp_unslash($_POST['global_schema'])) : '';

      // Validate YAML syntax before saving
      if (!empty($global_schema)) {
        $validation_result = $this->validate_yaml_schema($global_schema);
        if (!$validation_result['valid']) {
          // Store the invalid schema in a transient so we can display it back
          set_transient('yaml_cf_invalid_global_schema_' . get_current_user_id(), $global_schema, 60);

          // Redirect back with error message (message kept out of the URL)
          $this->notify('error', $validation_result['message'], [
            'title' => __('Schema not saved:', 'yaml-custom-fields'),
            'details' => [__('The changes are still in the editor below. Fix the problem and save again.', 'yaml-custom-fields')],
          ]);
          wp_safe_redirect(add_query_arg([
            'page' => 'yaml-cf-edit-global-schema',
            'error' => '1'
          ], admin_url('admin.php')));
          exit;
        }
      }

      update_option('yaml_cf_global_schema', $global_schema);

      // Clear any stored invalid schema
      delete_transient('yaml_cf_invalid_global_schema_' . get_current_user_id());

      $this->notify('success', __('Site details schema saved.', 'yaml-custom-fields'));

      wp_safe_redirect(add_query_arg([
        'page' => 'yaml-cf-edit-global-schema'
      ], admin_url('admin.php')));
      exit;
    }

    // Handle global data save
    if (isset($_POST['yaml_cf_global_data_nonce']) &&
        wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['yaml_cf_global_data_nonce'])), 'yaml_cf_save_global_data')) {

      if (!current_user_can('manage_options')) {
        wp_die(esc_html__('This action needs administrator permissions.', 'yaml-custom-fields'));
      }

      $field_data = [];

      // Get global schema for validation
      $global_schema_yaml = get_option('yaml_cf_global_schema', '');
      $global_schema = null;
      if (!empty($global_schema_yaml)) {
        $global_schema = $this->parse_yaml_schema($global_schema_yaml);
      }

      // Collect all field data
      // Use post_raw() to get unslashed data safely without PHPCS warnings
      // Data will be sanitized by schema-aware sanitize_field_data()
      $posted_data = self::post_raw('yaml_cf', []);
      if (!empty($posted_data) && is_array($posted_data)) {
        $field_data = $this->sanitize_field_data($posted_data, $global_schema);
      }

      update_option('yaml_cf_global_data', $field_data);

      // Clear caches
      $this->clear_data_caches();

      $this->notify('success', __('Site details saved.', 'yaml-custom-fields'));

      wp_safe_redirect(add_query_arg([
        'page' => 'yaml-cf-manage-global-data'
      ], admin_url('admin.php')));
      exit;
    }

    // Handle template global schema save
    if (isset($_POST['yaml_cf_save_template_global_schema_nonce']) &&
        wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['yaml_cf_save_template_global_schema_nonce'])), 'yaml_cf_save_template_global_schema')) {

      if (!current_user_can('manage_options')) {
        wp_die(esc_html__('This action needs administrator permissions.', 'yaml-custom-fields'));
      }

      $template = isset($_POST['template']) ? sanitize_text_field(wp_unslash($_POST['template'])) : '';
      $template_global_schema = isset($_POST['template_global_schema']) ? sanitize_textarea_field(wp_unslash($_POST['template_global_schema'])) : '';

      // Validate YAML syntax before saving
      if (!empty($template_global_schema)) {
        $validation_result = $this->validate_yaml_schema($template_global_schema, $template);
        if (!$validation_result['valid']) {
          // Store the invalid schema in a transient so we can display it back
          set_transient('yaml_cf_invalid_template_global_schema_' . get_current_user_id(), $template_global_schema, 60);

          // Redirect back with error message (message kept out of the URL)
          $this->notify('error', $validation_result['message'], [
            'title' => __('Schema not saved:', 'yaml-custom-fields'),
            'details' => [__('The changes are still in the editor below. Fix the problem and save again.', 'yaml-custom-fields')],
          ]);
          wp_safe_redirect(add_query_arg([
            'page' => 'yaml-cf-edit-template-global',
            'template' => rawurlencode($template),
            'error' => '1'
          ], admin_url('admin.php')));
          exit;
        }
      }

      $template_global_schemas = get_option('yaml_cf_template_global_schemas', []);
      $template_global_schemas[$template] = $template_global_schema;
      update_option('yaml_cf_template_global_schemas', $template_global_schemas);

      // Clear any stored invalid schema
      delete_transient('yaml_cf_invalid_template_global_schema_' . get_current_user_id());

      /* translators: %s: template file name */
      $this->notify('success', sprintf(__('Shared fields schema for %s saved.', 'yaml-custom-fields'), $template));

      wp_safe_redirect(add_query_arg([
        'page' => 'yaml-cf-edit-template-global',
        'template' => rawurlencode($template)
      ], admin_url('admin.php')));
      exit;
    }

    // Handle template global data save
    if (isset($_POST['yaml_cf_save_template_global_data_nonce']) &&
        wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['yaml_cf_save_template_global_data_nonce'])), 'yaml_cf_save_template_global_data')) {

      if (!current_user_can('manage_options')) {
        wp_die(esc_html__('This action needs administrator permissions.', 'yaml-custom-fields'));
      }

      $template = isset($_POST['template']) ? sanitize_text_field(wp_unslash($_POST['template'])) : '';
      $field_data = [];

      // Get template global schema for validation
      $template_global_schemas = get_option('yaml_cf_template_global_schemas', []);
      $template_global_schema = null;
      if (isset($template_global_schemas[$template]) && !empty($template_global_schemas[$template])) {
        $template_global_schema = $this->parse_yaml_schema($template_global_schemas[$template]);
      }

      // Collect all field data
      // Use post_raw() to get unslashed data safely without PHPCS warnings
      // Data will be sanitized by schema-aware sanitize_field_data()
      $posted_data = self::post_raw('yaml_cf', []);
      if (!empty($posted_data) && is_array($posted_data)) {
        $field_data = $this->sanitize_field_data($posted_data, $template_global_schema);
      }

      $template_global_data = get_option('yaml_cf_template_global_data', []);
      $template_global_data[$template] = $field_data;
      update_option('yaml_cf_template_global_data', $template_global_data);

      // Clear caches
      $this->clear_data_caches();

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
   * Sanitize submitted or imported field data against a parsed schema
   * Delegates to YamlCF\Form\DataSanitizer (schema-aware, level by level)
   *
   * @param mixed $data Data to sanitize
   * @param array|null $schema Parsed schema with a 'fields' array
   * @param string $field_name Field name within $schema ('' for a set of fields)
   * @return mixed Sanitized data
   */
  public function sanitize_field_data($data, $schema = null, $field_name = '') {
    return \YamlCF\Core\Plugin::getInstance()->get('data_sanitizer')->sanitize($data, $schema, $field_name);
  }

  private function is_code_field($schema, $field_name) {
    if (!isset($schema['fields']) || !is_array($schema['fields'])) {
      return false;
    }

    foreach ($schema['fields'] as $field) {
      if (isset($field['name']) && $field['name'] === $field_name && isset($field['type']) && $field['type'] === 'code') {
        return true;
      }
      // Check nested fields in objects
      if (isset($field['fields']) && is_array($field['fields'])) {
        if ($this->is_code_field(['fields' => $field['fields']], $field_name)) {
          return true;
        }
      }
      // Check blocks
      if (isset($field['blocks']) && is_array($field['blocks'])) {
        foreach ($field['blocks'] as $block) {
          if (isset($block['fields']) && is_array($block['fields'])) {
            if ($this->is_code_field(['fields' => $block['fields']], $field_name)) {
              return true;
            }
          }
        }
      }
    }

    return false;
  }

  /**
   * Public wrapper for is_code_field to allow external access.
   *
   * Matches the field name at any depth (top level, object fields, block fields), so a
   * nested code field makes a same-named field elsewhere report true. It is kept as-is
   * for backward compatibility and is not used for sanitization; for an exact lookup use
   * YamlCF\Form\DataSanitizer::getFieldDefinition() on the right schema level.
   *
   * @param array $schema The schema to check.
   * @param string $field_name The field name to check.
   * @return bool True if the field is a code field.
   */
  public function is_code_field_public($schema, $field_name) {
    return $this->is_code_field($schema, $field_name);
  }

  // Marker prefix for encoded code fields
  const CODE_FIELD_MARKER = \YamlCF\Form\DataSanitizer::CODE_FIELD_MARKER;

  /**
   * Decode a code field value that may be base64 encoded.
   * Handles backward compatibility with non-encoded values.
   *
   * @param string $value The value to decode.
   * @return string The decoded value.
   */
  public function decode_code_field($value) {
    if (empty($value) || !is_string($value)) {
      return $value === null ? '' : $value;
    }

    // Check for marker prefix (new format)
    if (strpos($value, self::CODE_FIELD_MARKER) === 0) {
      $encoded = substr($value, strlen(self::CODE_FIELD_MARKER));
      $decoded = base64_decode($encoded, true);
      if ($decoded !== false) {
        return $decoded;
      }
    }

    // Return as-is if not encoded (legacy data or non-code fields)
    return $value;
  }

  private function get_theme_templates() {
    // Check cache first
    $cache_key = 'yaml_cf_templates_' . get_stylesheet();
    $cached = get_transient($cache_key);

    if ($cached !== false && !$this->get_param('refresh_ycf')) {
      return $cached;
    }

    $templates = [];
    $partials = [];
    $theme = wp_get_theme();

    // Get all template files
    $template_files = $theme->get_files('php', -1); // -1 for unlimited depth

    // WordPress template hierarchy - only templates tied to specific posts/pages
    $valid_template_patterns = [
      'page.php',
      'single.php',
      'attachment.php',
      // Specific templates with prefixes
      'page-*.php',
      'single-*.php',
    ];

    // Partial patterns (automatic detection)
    // These are templates with global data (not post-specific)
    $partial_patterns = [
      // Traditional partials
      'header.php',
      'footer.php',
      'sidebar.php',
      'header-*.php',
      'footer-*.php',
      'sidebar-*.php',
      'content.php',
      'content-*.php',
      'comments.php',
      'searchform.php',
      // Archive/listing templates (global data)
      'index.php',
      'front-page.php',
      'home.php',
      'archive.php',
      'archive-*.php',
      'category.php',
      'category-*.php',
      'tag.php',
      'tag-*.php',
      'taxonomy.php',
      'taxonomy-*.php',
      'author.php',
      'author-*.php',
      'date.php',
      'search.php',
      '404.php',
    ];

    foreach ($template_files as $file => $path) {
      $basename = basename($file);
      $relative_path = str_replace(get_template_directory() . '/', '', $path);

      // Check if it's a root-level main template
      if (dirname($file) === '.') {
        $is_valid_template = false;
        foreach ($valid_template_patterns as $pattern) {
          if ($pattern === $basename || fnmatch($pattern, $basename)) {
            $is_valid_template = true;
            break;
          }
        }

        if ($is_valid_template) {
          $templates[] = [
            'file' => $basename,
            'path' => $path,
            'name' => $this->format_template_name($basename)
          ];
        }
      }

      // Check if it's a partial (automatic detection by pattern)
      $is_partial = false;
      foreach ($partial_patterns as $pattern) {
        if ($pattern === $basename || fnmatch($pattern, $basename)) {
          $is_partial = true;
          break;
        }
      }

      // Also check for @ycf marker in file header (custom partials)
      if (!$is_partial && $this->has_ycf_marker($path)) {
        $is_partial = true;
      }

      if ($is_partial) {
        $partials[] = [
          'file' => $relative_path,
          'path' => $path,
          'name' => $this->format_template_name($basename)
        ];
      }
    }

    // Add custom page templates (templates with Template Name header)
    $page_templates = get_page_templates();
    foreach ($page_templates as $name => $file) {
      // Only include templates in the root directory
      if (strpos($file, '/') === false) {
        // Avoid duplicates
        $already_added = false;
        foreach ($templates as $existing) {
          if ($existing['file'] === $file) {
            $already_added = true;
            break;
          }
        }

        if (!$already_added) {
          $templates[] = [
            'file' => $file,
            'path' => get_template_directory() . '/' . $file,
            'name' => $name
          ];
        }
      }
    }

    $result = [
      'templates' => $templates,
      'partials' => $partials
    ];

    // Cache for 1 hour
    set_transient($cache_key, $result, HOUR_IN_SECONDS);

    return $result;
  }

  /**
   * Check if a file has the @ycf marker in its header
   * Only reads first 30 lines for performance
   */
  private function has_ycf_marker($file_path) {
    if (!file_exists($file_path) || !is_readable($file_path)) {
      return false;
    }

    $content = file_get_contents($file_path, false, null, 0, 8192); // Read first 8KB
    if ($content === false) {
      return false;
    }

    // Check first 30 lines for @ycf marker
    $lines = explode("\n", $content);
    $lines_to_check = min(30, count($lines));

    for ($i = 0; $i < $lines_to_check; $i++) {
      if (preg_match('/@ycf/i', $lines[$i])) {
        return true;
      }
    }

    return false;
  }

  /**
   * @deprecated Now handled by CacheManager::clearTemplateCache()
   * Kept for backward compatibility - delegates to new architecture
   */
  public function clear_template_cache() {
    // Delegate to new architecture if available
    if (class_exists('\YamlCF\Core\Plugin')) {
      $plugin = \YamlCF\Core\Plugin::getInstance();
      $cache_manager = $plugin->get('cache_manager');
      $cache_manager->clearTemplateCache();
      return;
    }

    // Fallback for backward compatibility
    $cache_key = 'yaml_cf_templates_' . get_stylesheet();
    delete_transient($cache_key);
  }

  private function format_template_name($filename) {
    $name = str_replace(['-', '_', '.php'], [' ', ' ', ''], $filename);
    return ucwords($name);
  }

  /**
   * Get the template file for a post based on its type and settings
   * Delegates to YamlCF\Template\TemplateResolver
   *
   * @param WP_Post $post The post object
   * @return string The template filename
   */
  public function get_template_for_post($post) {
    return \YamlCF\Core\Plugin::getInstance()->get('template_resolver')->getTemplateForPost($post);
  }

  /**
   * Find the schema that applies to a post, if any
   *
   * Shared by the meta box registration and its render callback so the box is
   * never registered for a post that would render nothing.
   *
   * @param WP_Post $post Post being edited
   * @return array|null ['template' => string, 'schema' => array] or null
   */
  private function get_schema_for_post($post) {
    $post_type_object = get_post_type_object($post->post_type);
    if (!$post_type_object || !$post_type_object->public) {
      return null;
    }

    $template = $this->get_template_for_post($post);

    $template_settings = get_option('yaml_cf_template_settings', []);
    if (!isset($template_settings[$template]) || !$template_settings[$template]) {
      return null;
    }

    $schemas = get_option('yaml_cf_schemas', []);
    if (!isset($schemas[$template]) || empty($schemas[$template])) {
      return null;
    }

    $schema = $this->parse_yaml_schema($schemas[$template]);
    if (!$schema || !isset($schema['fields'])) {
      return null;
    }

    // Normalize shorthand syntax (info boxes, section breaks)
    $schema['fields'] = $this->normalize_field_shorthand($schema['fields']);

    return ['template' => $template, 'schema' => $schema];
  }

  /**
   * Register the custom fields meta box for posts that have a schema
   *
   * A real meta box works in both editors: the block editor renders classic
   * meta boxes in its bottom panel and submits them with the post, so the
   * existing $_POST['yaml_cf'] save path is unchanged.
   *
   * @param string $post_type Post type being edited
   * @param WP_Post $post Post being edited
   * @return void
   */
  public function register_meta_box($post_type, $post) {
    if (!($post instanceof WP_Post)) {
      return;
    }

    if ($this->get_schema_for_post($post) === null) {
      return;
    }

    add_meta_box(
      'yaml-cf-meta-box',
      __('Custom fields', 'yaml-custom-fields'),
      [$this, 'render_schema_meta_box'],
      $post_type,
      'normal',
      'high'
    );
  }

  /**
   * Print the custom fields meta box
   *
   * Core prints the .postbox wrapper, its heading and the collapse toggle, and
   * remembers the open or closed state per user.
   *
   * @param WP_Post $post Post being edited
   * @return void
   */
  public function render_schema_meta_box($post) {
    $resolved = $this->get_schema_for_post($post);
    if ($resolved === null) {
      return;
    }

    $template = $resolved['template'];
    $schema = $resolved['schema'];
    $template_settings = get_option('yaml_cf_template_settings', []);

    wp_nonce_field('yaml_cf_meta_box', 'yaml_cf_meta_box_nonce');

    // Add link to edit schema
    $edit_schema_url = admin_url('admin.php?page=yaml-cf-edit-schema&template=' . rawurlencode($template));

    $export_url = wp_nonce_url(
      add_query_arg('yaml_cf_export_post', $post->ID, admin_url('post.php')),
      'yaml_cf_export_post_' . $post->ID
    );

    echo '<div class="yaml-cf-meta-box-header">';
    echo '<div class="yaml-cf-meta-box-intro">';
    echo '<span class="yaml-cf-template-chip"><span class="dashicons dashicons-layout" aria-hidden="true"></span>';
    /* translators: %s: template file name */
    echo esc_html(sprintf(__('Template: %s', 'yaml-custom-fields'), $template));
    echo '</span>';
    echo '<span class="yaml-cf-hint">' . esc_html__('Fill in the fields below. Click Update to save them.', 'yaml-custom-fields') . '</span>';
    echo '</div>';

    echo '<div class="yaml-cf-meta-box-actions">';
    if (current_user_can('manage_options')) {
      echo '<a href="' . esc_url($edit_schema_url) . '" target="_blank" class="button button-small">';
      echo '<span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>' . esc_html__('Edit schema', 'yaml-custom-fields');
      echo '<span class="screen-reader-text"> ' . esc_html__('(opens in a new tab)', 'yaml-custom-fields') . '</span>';
      echo '</a>';
    }

    echo '<a href="' . esc_url($export_url) . '" class="button button-small">';
    echo '<span class="dashicons dashicons-download" aria-hidden="true"></span>' . esc_html__('Export fields', 'yaml-custom-fields');
    echo '</a>';

    // Import: the button opens the hidden file input (handled by admin.js); a button also works with the keyboard
    echo '<input type="file" name="yaml_cf_import_file_hidden" accept=".json" id="yaml-cf-import-file-' . esc_attr($post->ID) . '" class="yaml-cf-hidden" data-post-id="' . esc_attr($post->ID) . '" data-nonce="' . esc_attr(wp_create_nonce('yaml_cf_import_post')) . '" tabindex="-1">';
    echo '<button type="button" class="button button-small yaml-cf-import-trigger" data-target="yaml-cf-import-file-' . esc_attr($post->ID) . '">';
    echo '<span class="dashicons dashicons-upload" aria-hidden="true"></span>' . esc_html__('Import fields', 'yaml-custom-fields');
    echo '</button>';

    echo '<a href="#" class="button button-small yaml-cf-reset-data" data-post-id="' . esc_attr($post->ID) . '">';
    echo '<span class="dashicons dashicons-trash" aria-hidden="true"></span>' . esc_html__('Empty all fields', 'yaml-custom-fields');
    echo '</a>';
    echo '</div>';
    echo '</div>';

    $saved_data = get_post_meta($post->ID, '_yaml_cf_data', true);
    if (empty($saved_data)) {
      $saved_data = [];
    }

    // Get template global schema if it exists
    $template_global_schemas = get_option('yaml_cf_template_global_schemas', []);
    $has_template_global = isset($template_global_schemas[$template]) && !empty($template_global_schemas[$template]);
    $template_global_schema = null;
    $template_global_data = [];
    $use_template_global_fields = get_post_meta($post->ID, '_yaml_cf_use_template_global_fields', true);
    if (empty($use_template_global_fields)) {
      $use_template_global_fields = [];
    }

    if ($has_template_global) {
      $template_global_schema = $this->parse_yaml_schema($template_global_schemas[$template]);
      $template_global_data_array = get_option('yaml_cf_template_global_data', []);
      $template_global_data = isset($template_global_data_array[$template]) ? $template_global_data_array[$template] : [];
    }

    echo '<div class="yaml-cf-fields">';

    // Fields are rendered one by one here (each may become a dual field), so the
    // section wrappers are opened and closed around the groups by hand.
    $grouper = \YamlCF\Core\Plugin::getInstance()->get('section_grouper');

    foreach ($grouper->group($schema['fields']) as $group) {
      if ($group['section'] !== null) {
        $this->render_section_open($group['section']);
      }

      foreach ($group['fields'] as $field) {
        $field_name = $field['name'];
        $has_template_global_field = $template_global_schema && isset($template_global_schema['fields']) && $this->field_exists_in_schema($field_name, $template_global_schema['fields']);

        if ($has_template_global_field) {
          // Render dual field (template global + local + checkbox)
          $this->render_dual_field($field, $saved_data, $template_global_data, $use_template_global_fields, $template);
        } else {
          // Render normal local-only field
          $context = ['type' => 'page'];
          $this->render_schema_fields([$field], $saved_data, '', $context);
        }
      }

      if ($group['section'] !== null) {
        echo '</div></section>';
      }
    }

    echo '</div>';

    // Render template-global-only fields as readonly
    if ($has_template_global && $template_global_schema && isset($template_global_schema['fields']) && isset($schema['fields'])) {
      $manage_template_global_url = admin_url('admin.php?page=yaml-cf-manage-template-global&template=' . rawurlencode($template));

      $has_global_only_fields = false;

      foreach ($template_global_schema['fields'] as $global_field) {
        $global_field_name = $global_field['name'];

        // Check if this field exists ONLY in template global (NOT in local schema)
        if (!$this->field_exists_in_schema($global_field_name, $schema['fields'])) {
          // First global-only field: render section header
          if (!$has_global_only_fields) {
            $has_global_only_fields = true;
            echo '<div class="yaml-cf-template-global-only-fields">';
            echo '<div class="yaml-cf-section-intro">';
            echo '<h3>' . esc_html__('Shared fields', 'yaml-custom-fields') . '</h3>';
            echo '<p>';
            echo esc_html__('These fields are the same on every page with this template. Edit them in one place.', 'yaml-custom-fields');
            echo ' <a href="' . esc_url($manage_template_global_url) . '" target="_blank">' . esc_html__('Edit shared content', 'yaml-custom-fields') . '</a>';
            echo '</p>';
            echo '</div>';
          }

          // Render the field as readonly with template global data
          $readonly_context = [
            'type' => 'template_global',
            'readonly' => true,
            'id_suffix' => '_global_only'
          ];
          $global_field_value = isset($template_global_data[$global_field_name]) ? $template_global_data[$global_field_name] : '';
          $this->render_schema_fields([$global_field], [$global_field_name => $global_field_value], '', $readonly_context);
        }
      }

      if ($has_global_only_fields) {
        echo '</div>'; // Close yaml-cf-template-global-only-fields
      }
    }

    // Check if this template uses global schema
    $use_global = isset($template_settings[$template . '_use_global']) && $template_settings[$template . '_use_global'];
    if ($use_global) {
      $global_schema_yaml = get_option('yaml_cf_global_schema', '');
      if (!empty($global_schema_yaml)) {
        $global_schema = $this->parse_yaml_schema($global_schema_yaml);
        if ($global_schema && isset($global_schema['fields'])) {
          $global_data = get_option('yaml_cf_global_data', []);
          $manage_global_url = admin_url('admin.php?page=yaml-cf-manage-global-data');

          echo '<div class="yaml-cf-global-fields">';
          echo '<div class="yaml-cf-section-intro">';
          echo '<h3>' . esc_html__('Site details', 'yaml-custom-fields') . '</h3>';
          echo '<p>';
          echo esc_html__('These details are the same on the whole website. Edit them in one place.', 'yaml-custom-fields');
          echo ' <a href="' . esc_url($manage_global_url) . '" target="_blank">' . esc_html__('Edit site details', 'yaml-custom-fields') . '</a>';
          echo '</p>';
          echo '</div>';

          $global_context = ['type' => 'global', 'readonly' => true];
          $this->render_schema_fields($global_schema['fields'], $global_data, '', $global_context);
          echo '</div>';
        }
      }
    }
  }

  /**
   * Print the custom fields box on edit_form_after_title
   *
   * @deprecated Use render_schema_meta_box(), registered via add_meta_boxes.
   * @param WP_Post $post Post being edited
   * @return void
   */
  public function render_schema_meta_box_after_title($post) {
    $this->render_schema_meta_box($post);
  }

  /**
   * Check if a field exists in a schema fields array
   */
  private function field_exists_in_schema($field_name, $fields) {
    foreach ($fields as $field) {
      if ($field['name'] === $field_name) {
        return true;
      }
    }
    return false;
  }

  /**
   * Render a dual field (template global + local + checkbox)
   */
  private function render_dual_field($field, $saved_data, $template_global_data, $use_template_global_fields, $template) {
    $field_name = $field['name'];
    $local_value = isset($saved_data[$field_name]) ? $saved_data[$field_name] : '';
    $template_global_value = isset($template_global_data[$field_name]) ? $template_global_data[$field_name] : '';
    $use_global = isset($use_template_global_fields[$field_name]) && $use_template_global_fields[$field_name] === '1';

    $manage_template_global_url = admin_url('admin.php?page=yaml-cf-manage-template-global&template=' . rawurlencode($template));

    echo '<div class="yaml-cf-dual-field" data-field-name="' . esc_attr($field_name) . '">';

    // Field label
    echo '<h4 class="yaml-cf-dual-field-title">' . esc_html($field['label'] ?? $field_name) . '</h4>';

    // Template global field (readonly, with edit link)
    echo '<div class="yaml-cf-template-global-part">';
    echo '<div class="yaml-cf-part-header">';
    echo '<strong class="yaml-cf-part-title is-global">' . esc_html__('Shared value (all pages with this template)', 'yaml-custom-fields') . '</strong>';
    echo '<a href="' . esc_url($manage_template_global_url) . '" target="_blank" class="button button-small">' . esc_html__('Edit', 'yaml-custom-fields') . '</a>';
    echo '</div>';
    $readonly_context = ['type' => 'template_global', 'readonly' => true, 'id_suffix' => '_global'];
    $this->render_schema_fields([$field], [$field_name => $template_global_value], '', $readonly_context);
    echo '</div>';

    // Local field (always editable in HTML, visually blocked by JavaScript/CSS when checkbox is checked)
    echo '<div class="yaml-cf-local-part">';
    echo '<div class="yaml-cf-part-header">';
    echo '<strong class="yaml-cf-part-title is-local">' . esc_html__('This page only', 'yaml-custom-fields') . '</strong>';
    echo '</div>';
    $local_context = ['type' => 'page'];
    $this->render_schema_fields([$field], [$field_name => $local_value], '', $local_context);
    echo '</div>';

    // Checkbox to toggle between global and local
    echo '<div class="yaml-cf-field-toggle">';
    echo '<label>';
    echo '<input type="checkbox" name="yaml_cf_use_template_global_fields[' . esc_attr($field_name) . ']" value="1" class="yaml-cf-use-global-checkbox" ' . checked($use_global, true, false) . ' />';
    echo '<span>' . esc_html__('Use the shared value on this page', 'yaml-custom-fields') . '</span>';
    echo '</label>';
    echo '</div>';

    echo '</div>'; // yaml-cf-dual-field
  }

  /**
   * Normalize shorthand field syntax to standard field structure
   *
   * Delegates to YamlCF\Schema\FieldNormalizer so the editor, the validator and
   * the renderer all read a schema the same way.
   * Transforms: "- info: text" and "- section: Title" into full field definitions.
   *
   * @param array $fields The fields array from parsed YAML
   * @return array Normalized fields array
   */
  private function normalize_field_shorthand($fields) {
    return \YamlCF\Core\Plugin::getInstance()->get('field_normalizer')->normalizeAll($fields);
  }

  /**
   * Parse a YAML schema (delegates to YamlCF\Schema\SchemaParser; returns null on errors)
   */
  private function parse_yaml_schema($yaml) {
    return \YamlCF\Core\Plugin::getInstance()->get('schema_parser')->parse($yaml);
  }

  /**
   * Parse basic markdown syntax for info fields
   * Supports: **bold**, _italic_, and [text](url)
   *
   * @param string $text The markdown text to parse
   * @return string HTML output with security filtering
   */
  private function parse_basic_markdown($text) {
    return \YamlCF\Helpers\MarkdownParser::parse($text);
  }

  /**
   * Validate a YAML schema (delegates to YamlCF\Schema\SchemaValidator)
   *
   * @param string $yaml YAML schema
   * @param string|null $template Template the schema belongs to (enables info field restrictions)
   * @return array ['valid' => bool, 'message' => string]
   */
  private function validate_yaml_schema($yaml, $template = null) {
    return \YamlCF\Core\Plugin::getInstance()->get('schema_validator')->validate($yaml, $template);
  }

  /**
   * Render a set of fields, split into the segments marked by section breaks
   *
   * Without any "- section:" in the schema this renders exactly as a flat list,
   * so existing schemas are untouched.
   *
   * @param array $fields Fields from the schema
   * @param array $saved_data Saved values keyed by field name
   * @param string $prefix Name prefix for nested fields
   * @param array|null $context Render context
   * @return void
   */
  public function render_schema_fields($fields, $saved_data, $prefix = '', $context = null) {
    if (!is_array($fields)) {
      return;
    }

    $plugin = \YamlCF\Core\Plugin::getInstance();
    $fields = $plugin->get('field_normalizer')->normalizeAll($fields);
    $grouper = $plugin->get('section_grouper');

    if (!$grouper->hasSections($fields)) {
      $this->render_fields_flat($fields, $saved_data, $prefix, $context);
      return;
    }

    foreach ($grouper->group($fields) as $group) {
      // Fields before the first section break keep the old, unwrapped markup
      if ($group['section'] === null) {
        $this->render_fields_flat($group['fields'], $saved_data, $prefix, $context);
        continue;
      }

      $this->render_section_open($group['section']);
      $this->render_fields_flat($group['fields'], $saved_data, $prefix, $context);
      echo '</div></section>';
    }
  }

  /**
   * Open a section wrapper and print its heading
   *
   * @param array $section Section field definition
   * @return void
   */
  private function render_section_open($section) {
    $is_break = !empty($section['break']);
    $label = (!$is_break && isset($section['label'])) ? trim((string) $section['label']) : '';
    $description = (!$is_break && isset($section['description'])) ? trim((string) $section['description']) : '';

    // "- break:" draws nothing at all, it only starts a new row
    if ($is_break) {
      $classes = 'yaml-cf-field-group is-break';
    } elseif ($label === '' && $description === '') {
      $classes = 'yaml-cf-field-group is-untitled';
    } else {
      $classes = 'yaml-cf-field-group';
    }

    echo '<section class="' . esc_attr($classes) . '">';

    if ($label !== '' || $description !== '') {
      echo '<div class="yaml-cf-field-group-intro">';
      if ($label !== '') {
        echo '<h3>' . esc_html($label) . '</h3>';
      }
      if ($description !== '') {
        echo '<p>' . esc_html($description) . '</p>';
      }
      echo '</div>';
    }

    echo '<div class="yaml-cf-field-group-fields">';
  }

  private function render_fields_flat($fields, $saved_data, $prefix = '', $context = null) {
    // Check if fields should be readonly
    $readonly = ($context && is_array($context) && isset($context['readonly']) && $context['readonly']);
    $readonly_class = $readonly ? ' yaml-cf-readonly' : '';

    // Get ID suffix if provided in context (for unique IDs in dual fields)
    $id_suffix = ($context && is_array($context) && isset($context['id_suffix'])) ? $context['id_suffix'] : '';

    foreach ($fields as $field) {
      // Handle nested object syntax: if prefix ends with '[', create proper nested array notation
      if (!empty($prefix) && substr($prefix, -1) === '[') {
        $field_name = substr($prefix, 0, -1) . '][' . $field['name'];
      } else {
        $field_name = $prefix . $field['name'];
      }
      $field_id = 'ycf_' . str_replace(['[', ']'], ['_', ''], $field_name) . $id_suffix;
      $field_value = isset($saved_data[$field['name']]) ? $saved_data[$field['name']] : (isset($field['default']) ? $field['default'] : '');
      $field_label = isset($field['label']) ? $field['label'] : ucfirst($field['name']);

      // Read-only fields sit in a disabled fieldset so their inputs are never submitted
      // (they share yaml_cf[...] names with editable fields and would overwrite them)
      if ($readonly) {
        echo '<fieldset class="yaml-cf-readonly-fieldset" disabled="disabled">';
      }

      echo '<div class="yaml-cf-field' . esc_attr($readonly_class) . '" data-type="' . esc_attr($field['type']) . '">';

      // Generate code snippet (skip for block type fields as they have their own snippet)
      // Also skip for data_object context (manage entries page)
      $code_snippet = '';
      $popover_id = '';
      if ($context && is_array($context) && isset($context['type']) && $context['type'] !== 'data_object' && $field['type'] !== 'block' && $field['type'] !== 'section') {
        // Determine the function name and parameters based on field type
        $function_name = 'ycf_get_field';
        $extra_params = '';
        $post_id_param = '';

        if (isset($field['type'])) {
          if ($field['type'] === 'image') {
            $function_name = 'ycf_get_image';
            // Show all parameters: field_name, post_id, size
            if ($context['type'] === 'partial' && isset($context['template'])) {
              $post_id_param = ", 'partial:" . esc_js($context['template']) . "'";
            } else {
              $post_id_param = ", null";
            }
            $extra_params = ", 'thumbnail'"; // Size options: thumbnail, medium, large, full
          } elseif ($field['type'] === 'file') {
            $function_name = 'ycf_get_file';
            // Show all parameters: field_name, post_id
            if ($context['type'] === 'partial' && isset($context['template'])) {
              $post_id_param = ", 'partial:" . esc_js($context['template']) . "'";
            } else {
              $post_id_param = ", null";
            }
          } elseif ($field['type'] === 'taxonomy') {
            $function_name = 'ycf_get_term';
            // Show all parameters: field_name, post_id
            if ($context['type'] === 'partial' && isset($context['template'])) {
              $post_id_param = ", 'partial:" . esc_js($context['template']) . "'";
            } else {
              $post_id_param = ", null";
            }
          } elseif ($field['type'] === 'post_type') {
            $function_name = 'ycf_get_post_type';
            // Show all parameters: field_name, post_id
            if ($context['type'] === 'partial' && isset($context['template'])) {
              $post_id_param = ", 'partial:" . esc_js($context['template']) . "'";
            } else {
              $post_id_param = ", null";
            }
          } else {
            // Regular fields
            if ($context['type'] === 'partial' && isset($context['template'])) {
              $post_id_param = ", 'partial:" . esc_js($context['template']) . "'";
            } else {
              $post_id_param = ", null";
            }
          }
        }

        $is_file_list_field = ($field['type'] === 'file') && isset($field['list']) && $field['list'];
        $is_image_list_field = ($field['type'] === 'image') && isset($field['list']) && $field['list'];

        if ($is_file_list_field) {
          $code_snippet = "\$files = ycf_get_file('" . esc_js($field['name']) . "'" . $post_id_param . ");\nif (!empty(\$files)) {\n  foreach (\$files as \$file) {\n    echo '<a href=\"' . esc_url(\$file['url']) . '\">' . esc_html(\$file['filename']) . '</a>';\n  }\n}";
        } elseif ($is_image_list_field) {
          $code_snippet = "\$images = ycf_get_image('" . esc_js($field['name']) . "'" . $post_id_param . ", 'medium');\nif (!empty(\$images)) {\n  foreach (\$images as \$image) {\n    echo '<img src=\"' . esc_url(\$image['url']) . '\" alt=\"' . esc_attr(\$image['alt']) . '\" />';\n  }\n}";
        } else {
          $code_snippet = $function_name . "('" . esc_js($field['name']) . "'" . $post_id_param . $extra_params . ")";
        }
        $popover_id = 'snippet-' . sanitize_html_class($field_id);
      }

      // Skip label rendering for block, info and section fields as they handle their own display
      if ($field['type'] !== 'block' && $field['type'] !== 'info' && $field['type'] !== 'section') {
        // Check if this is a taxonomy field with multiple checkboxes
        $is_multiple_taxonomy = false;
        if ($field['type'] === 'taxonomy') {
          $is_multiple_taxonomy = isset($field['multiple']) && $field['multiple'];
        }

        // Check if this is a data_object field with multiple checkboxes (list: true)
        $is_multiple_data_object = false;
        if ($field['type'] === 'data_object') {
          $is_multiple_data_object = isset($field['list']) && $field['list'];
          // Also check 'multiple' for consistency with taxonomy fields
          if (!$is_multiple_data_object) {
            $is_multiple_data_object = isset($field['multiple']) && $field['multiple'];
          }
        }

        // Check if this is an object field (container with nested fields)
        $is_object_field = ($field['type'] === 'object');

        if ($field['type'] === 'image' || $field['type'] === 'file') {
          echo '<p class="yaml-cf-field-label">' . esc_html($field_label) . '</p>';
        } elseif ($is_multiple_taxonomy || $is_multiple_data_object || $is_object_field) {
          // Use heading instead of label for:
          // - Multiple taxonomy fields (checkboxes) - no single input to associate with
          // - Multiple data_object fields (checkboxes) - no single input to associate with
          // - Object fields (container fields) - no input, just nested fields
          echo '<h4 class="yaml-cf-field-heading">' . esc_html($field_label) . '</h4>';
        } else {
          echo '<label for="' . esc_attr($field_id) . '">' . esc_html($field_label) . '</label>';
        }
        $this->render_field_help($field);
      }

      switch ($field['type']) {
        case 'boolean':
          echo '<input type="checkbox" name="yaml_cf[' . esc_attr($field_name) . ']" id="' . esc_attr($field_id) . '" value="1" ' . checked($field_value, 1, false) . ' />';
          break;

        case 'string':
          $options = isset($field['options']) ? $field['options'] : [];
          $attrs = [
            'type' => 'text',
            'name' => 'yaml_cf[' . $field_name . ']',
            'id' => $field_id,
            'value' => $field_value,
            'class' => 'regular-text',
          ];
          if (isset($options['minlength'])) {
            $attrs['minlength'] = intval($options['minlength']);
          }
          if (isset($options['maxlength'])) {
            $attrs['maxlength'] = intval($options['maxlength']);
          }
          echo '<input';
          $this->output_html_attrs($attrs);
          echo ' />';
          break;

        case 'text':
          $options = isset($field['options']) ? $field['options'] : [];
          $attrs = [
            'name' => 'yaml_cf[' . $field_name . ']',
            'id' => $field_id,
            'rows' => 5,
            'class' => 'large-text',
          ];
          if (isset($options['maxlength'])) {
            $attrs['maxlength'] = intval($options['maxlength']);
          }
          echo '<textarea';
          $this->output_html_attrs($attrs);
          echo '>' . esc_textarea($field_value) . '</textarea>';
          break;

        case 'rich-text':
          wp_editor($field_value, $field_id, [
            'textarea_name' => 'yaml_cf[' . $field_name . ']',
            'textarea_rows' => 10,
            'media_buttons' => true,
            'tinymce' => [
              'toolbar1' => 'formatselect,bold,italic,bullist,numlist,link,unlink',
            ],
            '_content_editor_dfw' => false
          ]);
          break;

        case 'code':
          $options = isset($field['options']) ? $field['options'] : [];
          $language = isset($options['language']) ? $options['language'] : 'html';
          // Decode base64 encoded code field value for display
          $field_value = $this->decode_code_field($field_value);
          echo '<textarea name="yaml_cf[' . esc_attr($field_name) . ']" id="' . esc_attr($field_id) . '" rows="10" class="large-text code" data-language="' . esc_attr($language) . '">' . esc_textarea($field_value) . '</textarea>';
          break;

        case 'section':
          // Sections are normally turned into wrappers by render_schema_fields().
          // One that reaches here (inside a block, say) degrades to a plain break.
          echo '<hr class="yaml-cf-field-group-rule" />';
          if (!empty($field['label'])) {
            echo '<h4 class="yaml-cf-field-group-fallback-title">' . esc_html($field['label']) . '</h4>';
          }
          if (!empty($field['description'])) {
            echo '<p class="yaml-cf-field-group-fallback-description">' . esc_html($field['description']) . '</p>';
          }
          break;

        case 'info':
          // Render read-only info banner with markdown support
          $info_text = isset($field['text']) ? $field['text'] : '';
          if (!empty($info_text)) {
            echo '<div class="yaml-cf-info-box">';
            echo '<span class="dashicons dashicons-info"></span>';
            echo '<div class="yaml-cf-info-content">';
            echo wp_kses_post($this->parse_basic_markdown($info_text));
            echo '</div>';
            echo '</div>';
          }
          break;

        case 'number':
          $options = isset($field['options']) ? $field['options'] : [];
          $attrs = [
            'type' => 'number',
            'name' => 'yaml_cf[' . $field_name . ']',
            'id' => $field_id,
            'value' => $field_value,
            'class' => 'small-text',
          ];
          if (isset($options['min'])) {
            $attrs['min'] = intval($options['min']);
          }
          if (isset($options['max'])) {
            $attrs['max'] = intval($options['max']);
          }
          echo '<input';
          $this->output_html_attrs($attrs);
          echo ' />';
          break;

        case 'date':
          $options = isset($field['options']) ? $field['options'] : [];
          $has_time = isset($options['time']) && $options['time'];
          $attrs = [
            'type' => $has_time ? 'datetime-local' : 'date',
            'name' => 'yaml_cf[' . $field_name . ']',
            'id' => $field_id,
            'value' => $field_value,
          ];
          echo '<input';
          $this->output_html_attrs($attrs);
          echo ' />';
          break;

        case 'select':
          $options = isset($field['options']) ? $field['options'] : [];
          $multiple = isset($field['multiple']) && $field['multiple'];

          // Check for values in options.values first, then fallback to root level values
          $values = [];
          if (isset($options['values']) && is_array($options['values'])) {
            $values = $options['values'];
          } elseif (isset($field['values'])) {
            $values = $field['values'];
          }

          echo '<select name="yaml_cf[' . esc_attr($field_name) . ']' . ($multiple ? '[]' : '') . '" id="' . esc_attr($field_id) . '" ' . ($multiple ? 'multiple' : '') . '>';
          echo '<option value="">' . esc_html__('-- Select --', 'yaml-custom-fields') . '</option>';

          if (is_array($values) && !empty($values)) {
            foreach ($values as $option) {
              // Handle both array format and simple values
              if (is_array($option)) {
                $opt_value = isset($option['value']) ? $option['value'] : '';
                $opt_label = isset($option['label']) ? $option['label'] : $opt_value;
              } else {
                $opt_value = $option;
                $opt_label = $option;
              }

              // Use loose comparison to handle string/int type differences
              $is_selected = false;
              if ($multiple && is_array($field_value)) {
                // For multiple select, check if value is in array
                $is_selected = in_array($opt_value, $field_value, false);
              } else {
                // For single select, use loose comparison
                $is_selected = ($field_value == $opt_value && $field_value !== '');
              }
              $opt_attrs = [
                'value' => $opt_value,
                'selected' => $is_selected ? 'selected' : false,
              ];
              echo '<option';
              $this->output_html_attrs($opt_attrs);
              echo '>' . esc_html($opt_label) . '</option>';
            }
          }

          echo '</select>';
          break;

        case 'taxonomy':
          $options = isset($field['options']) ? $field['options'] : [];
          $taxonomy = isset($options['taxonomy']) ? $options['taxonomy'] : 'category';
          $multiple = isset($field['multiple']) && $field['multiple'];

          // Count first: a big taxonomy gets a search box instead of every term,
          // so the page does not carry thousands of checkboxes or options.
          $term_count = (int) wp_count_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);

          if ($term_count > self::get_picker_threshold('taxonomy', $taxonomy)) {
            $selected_values = is_array($field_value) ? $field_value : ($field_value !== '' ? [$field_value] : []);
            $this->render_picker([
              'field_name' => $field_name,
              'field_id' => $field_id,
              'source' => 'taxonomy',
              'key' => $taxonomy,
              'multiple' => $multiple,
              'selected' => $this->get_selected_term_labels($selected_values),
              'readonly' => $readonly,
              'total' => $term_count,
            ]);
            break;
          }

          // Get terms for the specified taxonomy
          $terms = get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
          ]);

          if ($multiple) {
            // Use checkboxes for better UX with multiple selection
            $field_value = is_array($field_value) ? $field_value : ($field_value ? [$field_value] : []);

            // Hidden field to ensure the field is submitted even when no checkboxes are checked
            // This allows users to clear all selections
            echo '<input type="hidden" name="yaml_cf[' . esc_attr($field_name) . ']" value="" />';

            echo '<div class="yaml-cf-taxonomy-checkboxes">';

            if (!is_wp_error($terms) && !empty($terms)) {
              foreach ($terms as $term) {
                $checked = in_array($term->term_id, $field_value) ? 'checked' : '';
                $checkbox_id = $field_id . '_' . $term->term_id;
                echo '<label>';
                echo '<input type="checkbox" name="yaml_cf[' . esc_attr($field_name) . '][]" id="' . esc_attr($checkbox_id) . '" value="' . esc_attr($term->term_id) . '" ' . esc_attr($checked) . ' />';
                echo ' ' . esc_html($term->name);
                echo '</label>';
              }
            } else {
              echo '<p class="yaml-cf-checkbox-empty">' . esc_html__('There are no categories or tags yet.', 'yaml-custom-fields') . '</p>';
            }

            echo '</div>';
          } else {
            // Single select - use dropdown
            echo '<select name="yaml_cf[' . esc_attr($field_name) . ']" id="' . esc_attr($field_id) . '" class="regular-text">';
            echo '<option value="">' . esc_html(sprintf(
              /* translators: %s: field label */
              __('-- Select %s --', 'yaml-custom-fields'),
              $field['label']
            )) . '</option>';

            if (!is_wp_error($terms) && !empty($terms)) {
              foreach ($terms as $term) {
                $selected = ($field_value == $term->term_id) ? 'selected' : '';
                echo '<option value="' . esc_attr($term->term_id) . '" ' . esc_attr($selected) . '>' . esc_html($term->name) . '</option>';
              }
            }

            echo '</select>';
          }
          break;

        case 'post_type':
          // Get all public post types
          $post_types = get_post_types(['public' => true], 'objects');

          echo '<select name="yaml_cf[' . esc_attr($field_name) . ']" id="' . esc_attr($field_id) . '" class="regular-text">';
          echo '<option value="">' . esc_html(sprintf(
              /* translators: %s: field label */
              __('-- Select %s --', 'yaml-custom-fields'),
              $field['label']
            )) . '</option>';

          foreach ($post_types as $post_type) {
            $selected = ($field_value === $post_type->name) ? 'selected' : '';
            echo '<option value="' . esc_attr($post_type->name) . '" ' . esc_attr($selected) . '>' . esc_html($post_type->label) . '</option>';
          }

          echo '</select>';
          break;

        case 'data_object':
          $options = isset($field['options']) ? $field['options'] : [];
          $object_type = isset($options['object_type']) ? $options['object_type'] : '';
          $multiple = isset($field['multiple']) && $field['multiple'];

          if (empty($object_type)) {
            echo '<p class="yaml-cf-status-bad">' . esc_html__('This field needs a list to choose from. Add object_type to the schema.', 'yaml-custom-fields') . '</p>';
            break;
          }

          // Get entries for this object type
          $data_entries = get_option('yaml_cf_data_object_entries_' . $object_type, []);
          $data_types = get_option('yaml_cf_data_object_types', []);

          if (!isset($data_types[$object_type])) {
            /* translators: %s: data object type name */
            echo '<p class="yaml-cf-status-bad">' . sprintf(esc_html__('The list "%s" does not exist. Check the name in the schema.', 'yaml-custom-fields'), esc_html($object_type)) . '</p>';
            break;
          }

          // A big collection gets a search box instead of every entry
          if (count($data_entries) > self::get_picker_threshold('data_object', $object_type)) {
            $selected_values = is_array($field_value) ? $field_value : ($field_value !== '' ? [$field_value] : []);
            $this->render_picker([
              'field_name' => $field_name,
              'field_id' => $field_id,
              'source' => 'data_object',
              'key' => $object_type,
              'multiple' => $multiple,
              'selected' => $this->get_selected_data_object_labels($selected_values, $object_type),
              'readonly' => $readonly,
              'total' => count($data_entries),
            ]);
            break;
          }

          if ($multiple) {
            // Use checkboxes for better UX with multiple selection
            $field_value = is_array($field_value) ? $field_value : ($field_value ? [$field_value] : []);

            // Hidden field to ensure the field is submitted even when no checkboxes are checked
            echo '<input type="hidden" name="yaml_cf[' . esc_attr($field_name) . ']" value="" />';

            if (!empty($data_entries)) {
              // Get the schema to determine which field to use as label
              $object_schema_yaml = $data_types[$object_type]['schema'];
              $object_schema = $this->parse_yaml_schema($object_schema_yaml);
              $label_field = '';
              if (!empty($object_schema['fields'])) {
                // Use first field as label
                $label_field = $object_schema['fields'][0]['name'];
              }

              echo '<div class="yaml-cf-data-object-checkboxes">';

              foreach ($data_entries as $entry_id => $entry_data) {
                // Use first field value as label
                $entry_label = isset($entry_data[$label_field]) ? $entry_data[$label_field] : $entry_id;
                if (is_array($entry_label)) {
                  $entry_label = $entry_id; // Fallback for complex data
                }

                $checked = in_array($entry_id, $field_value) ? 'checked' : '';
                $checkbox_id = $field_id . '_' . sanitize_html_class($entry_id);
                echo '<label>';
                echo '<input type="checkbox" name="yaml_cf[' . esc_attr($field_name) . '][]" id="' . esc_attr($checkbox_id) . '" value="' . esc_attr($entry_id) . '" ' . esc_attr($checked) . ' />';
                echo ' ' . esc_html($entry_label);
                echo '</label>';
              }

              echo '</div>';
            } else {
              echo '<p class="description">' . sprintf(
                // translators: %1$s is the data object type name, %2$s is the URL to manage entries
                esc_html__('The list %1$s is empty. %2$sAdd items%3$s first.', 'yaml-custom-fields'),
                esc_html($data_types[$object_type]['name']),
                '<a href="' . esc_url(admin_url('admin.php?page=yaml-cf-manage-data-object-entries&type_id=' . rawurlencode($object_type))) . '">',
                '</a>'
              ) . '</p>';
            }
          } else {
            // Single select - use dropdown
            echo '<select name="yaml_cf[' . esc_attr($field_name) . ']" id="' . esc_attr($field_id) . '" class="regular-text">';
            echo '<option value="">' . esc_html(sprintf(
              /* translators: %s: field label */
              __('-- Select %s --', 'yaml-custom-fields'),
              $field['label']
            )) . '</option>';

            if (!empty($data_entries)) {
              // Get the schema to determine which field to use as label
              $object_schema_yaml = $data_types[$object_type]['schema'];
              $object_schema = $this->parse_yaml_schema($object_schema_yaml);
              $label_field = '';
              if (!empty($object_schema['fields'])) {
                // Use first field as label
                $label_field = $object_schema['fields'][0]['name'];
              }

              foreach ($data_entries as $entry_id => $entry_data) {
                // Use first field value as label
                $entry_label = isset($entry_data[$label_field]) ? $entry_data[$label_field] : $entry_id;
                if (is_array($entry_label)) {
                  $entry_label = $entry_id; // Fallback for complex data
                }

                $selected = ($field_value === $entry_id) ? 'selected' : '';
                echo '<option value="' . esc_attr($entry_id) . '" ' . esc_attr($selected) . '>' . esc_html($entry_label) . '</option>';
              }
            } else {
              echo '<option value="" disabled>' . sprintf(
                /* translators: %s: data object type name (e.g., "Universities", "Companies") */
                esc_html__('The list %s is empty', 'yaml-custom-fields'),
                esc_html($data_types[$object_type]['name'])
              ) . '</option>';
            }

            echo '</select>';
          }
          break;

        case 'image':
          $is_image_list = isset($field['list']) && $field['list'];

          if ($is_image_list) {
            // A gallery: many images in one field, picked in one trip to the
            // media library and reordered by dragging.
            $this->render_image_list($field_name, $field_value, $readonly);
            break;
          }

          echo '<input type="hidden" name="yaml_cf[' . esc_attr($field_name) . ']" id="' . esc_attr($field_id) . '" value="' . esc_attr($field_value) . '" />';
          echo '<div class="yaml-cf-media-buttons">';
          echo '<button type="button" class="button yaml-cf-upload-image" data-target="' . esc_attr($field_id) . '">' . esc_html__('Choose image', 'yaml-custom-fields') . '</button>';
          if ($field_value) {
            echo '<button type="button" class="button yaml-cf-clear-media" data-target="' . esc_attr($field_id) . '">' . esc_html__('Remove', 'yaml-custom-fields') . '</button>';
          }
          echo '</div>';
          if ($field_value) {
            // Field value is now attachment ID, get the image URL
            $image_url = wp_get_attachment_image_url($field_value, 'medium');
            if ($image_url) {
              echo '<div class="yaml-cf-image-preview"><img src="' . esc_url($image_url) . '" class="yaml-cf-image-preview-img" alt="" /></div>';
            }
          }
          break;

        case 'file':
          $is_file_list = isset($field['list']) && $field['list'];

          if ($is_file_list) {
            $file_ids = is_array($field_value) ? $field_value : [];
            echo '<input type="hidden" class="yaml-cf-file-list-placeholder" name="yaml_cf[' . esc_attr($field_name) . '][]" value="" />';
            echo '<div class="yaml-cf-file-list" data-field-name="' . esc_attr($field_name) . '">';
            echo '<div class="yaml-cf-file-list-items">';
            foreach ($file_ids as $file_id) {
              $this->render_file_list_item($field_name, $file_id);
            }
            echo '</div>';
            echo '<button type="button" class="button yaml-cf-add-file-list-item" data-target-name="' . esc_attr($field_name) . '">' . esc_html__('Add files', 'yaml-custom-fields') . '</button>';
            echo '</div>';
          } else {
            echo '<input type="hidden" name="yaml_cf[' . esc_attr($field_name) . ']" id="' . esc_attr($field_id) . '" value="' . esc_attr($field_value) . '" />';
            echo '<div class="yaml-cf-media-buttons">';
            echo '<button type="button" class="button yaml-cf-upload-file" data-target="' . esc_attr($field_id) . '">' . esc_html__('Choose file', 'yaml-custom-fields') . '</button>';
            if ($field_value) {
              echo '<button type="button" class="button yaml-cf-clear-media" data-target="' . esc_attr($field_id) . '">' . esc_html__('Remove', 'yaml-custom-fields') . '</button>';
            }
            echo '</div>';
            if ($field_value) {
              // Field value is now attachment ID, get the filename
              $file_path = get_attached_file($field_value);
              if ($file_path) {
                echo '<div class="yaml-cf-file-name">' . esc_html(basename($file_path)) . '</div>';
              }
            }
          }
          break;

        case 'object':
          if (isset($field['fields'])) {
            echo '<div class="yaml-cf-object">';
            $object_data = is_array($field_value) ? $field_value : [];
            $this->render_schema_fields($field['fields'], $object_data, $field_name . '[', $context);
            echo '</div>';
          }
          break;

        case 'block':
          $is_list = isset($field['list']) && $field['list'];
          $blocks = isset($field['blocks']) ? $field['blocks'] : [];
          $block_key = isset($field['blockKey']) ? $field['blockKey'] : 'type';

          // Generate code snippet for block fields
          $block_snippet = '';
          $block_popover_id = '';
          if ($is_list) {
            $block_snippet = "<?php\n// Get all blocks\n\$blocks = ycf_get_field('" . esc_js($field['name']) . "', null);\n\nif (!empty(\$blocks)) {\n  foreach (\$blocks as \$block) {\n    // Access block fields using context parameter:\n    // \$value = ycf_get_field('field_name', null, \$block);\n    // \$image = ycf_get_image('image_field', null, 'thumbnail', \$block);\n    // \$file = ycf_get_file('file_field', null, \$block);\n    // \$term = ycf_get_term('taxonomy_field', null, \$block);\n    // \$post_type = ycf_get_post_type('post_type_field', null, \$block);\n    // \$data_object = ycf_get_data_object('data_object_field', null, \$block);\n  }\n}\n?>";
            $block_popover_id = 'snippet-' . sanitize_html_class($field_id);
            echo '<div class="yaml-cf-block-heading-row">';
            echo '<h4 class="yaml-cf-block-heading">' . esc_html($field_label) . '</h4>';
            // Folding every section at once is the difference between a wall of
            // fields and a list you can scan
            if (!$readonly && is_array($field_value) && count($field_value) > 1) {
              echo '<button type="button" class="button-link yaml-cf-collapse-all" aria-pressed="false">';
              echo esc_html__('Collapse all', 'yaml-custom-fields');
              echo '</button>';
            }
            echo '</div>';
          }

          echo '<div class="yaml-cf-block-container" data-field-name="' . esc_attr($field['name']) . '">';

          if ($is_list) {
            $block_values = is_array($field_value) ? $field_value : [];
            echo '<div class="yaml-cf-block-list">';

            foreach ($block_values as $index => $block_data) {
              $this->render_block_item($field, $blocks, $block_data, $index, $block_key, $context);
            }

            echo '</div>';
            if (!$readonly) {
              echo '<div class="yaml-cf-block-controls">';
              $block_select_id = $field_id . '_block_type_select';
              echo '<label for="' . esc_attr($block_select_id) . '" class="screen-reader-text">' . esc_html__('Choose a section type to add', 'yaml-custom-fields') . '</label>';
              echo '<select class="yaml-cf-block-type-select" id="' . esc_attr($block_select_id) . '" name="' . esc_attr($block_select_id) . '">';
              echo '<option value="">' . esc_html__('Choose a section type', 'yaml-custom-fields') . '</option>';
              foreach ($blocks as $block) {
                $block_name = isset($block['name']) ? $block['name'] : '';
                $block_label = isset($block['label']) ? $block['label'] : $block_name;
                if ($block_name) {
                  echo '<option value="' . esc_attr($block_name) . '">' . esc_html($block_label) . '</option>';
                }
              }
              echo '</select>';
              echo '<button type="button" class="button yaml-cf-add-block">' . esc_html__('Add section', 'yaml-custom-fields') . '</button>';
              echo '</div>';
            }
          }

          echo '</div>';

          // Render snippet below the block container
          if ($is_list && $block_snippet && current_user_can('manage_options')) {
            echo '<span class="yaml-cf-snippet-wrapper">';
            echo '<button type="button" class="yaml-cf-copy-snippet" data-snippet="' . esc_attr($block_snippet) . '" data-popover="' . esc_attr($block_popover_id) . '">';
            echo '<span class="dashicons dashicons-editor-code"></span>';
            echo '<span class="snippet-text">' . esc_html__('Copy code', 'yaml-custom-fields') . '</span>';
            echo '</button>';
            echo '<span class="yaml-cf-snippet-popover" id="' . esc_attr($block_popover_id) . '" role="tooltip">';
            echo '<code class="yaml-cf-snippet-code-block">' . esc_html($block_snippet) . '</code>';
            echo '<span class="snippet-hint">' . esc_html__('Click to copy', 'yaml-custom-fields') . '</span>';
            echo '</span>';
            echo '</span>';
          }
          break;
      }

      // Render snippet below the input field (skip for block fields as they handle their own)
      if ($field['type'] !== 'block' && $code_snippet && current_user_can('manage_options')) {
        echo '<span class="yaml-cf-snippet-wrapper">';
        echo '<button type="button" class="yaml-cf-copy-snippet" data-snippet="' . esc_attr($code_snippet) . '" data-popover="' . esc_attr($popover_id) . '">';
        echo '<span class="dashicons dashicons-editor-code"></span>';
        echo '<span class="snippet-text">' . esc_html__('Copy code', 'yaml-custom-fields') . '</span>';
        echo '</button>';
        echo '<span class="yaml-cf-snippet-popover" id="' . esc_attr($popover_id) . '" role="tooltip">';
        echo '<code>' . esc_html($code_snippet) . '</code>';
        echo '<span class="snippet-hint">' . esc_html__('Click to copy', 'yaml-custom-fields') . '</span>';
        echo '</span>';
        echo '</span>';
      }

      echo '</div>';

      if ($readonly) {
        echo '</fieldset>';
      }
    }
  }

  /**
   * Print a field's help text for writers (the optional "description" in the schema)
   *
   * @param array $field Field definition
   */
  private function render_field_help($field) {
    if (!empty($field['description']) && is_string($field['description'])) {
      echo '<p class="yaml-cf-field-help">' . esc_html($field['description']) . '</p>';
    }
  }

  private function render_file_list_item($field_name, $file_id) {
    echo '<div class="yaml-cf-file-list-item">';
    echo '<input type="hidden" name="yaml_cf[' . esc_attr($field_name) . '][]" value="' . esc_attr($file_id) . '" />';
    $file_path = $file_id ? get_attached_file($file_id) : false;
    echo '<div class="yaml-cf-file-name">' . ($file_path ? esc_html(basename($file_path)) : '') . '</div>';
    echo '<button type="button" class="button button-small yaml-cf-remove-file-list-item">' . esc_html__('Remove', 'yaml-custom-fields') . '</button>';
    echo '</div>';
  }

  /**
   * Print a gallery field: an image field with "list: true"
   *
   * The images are picked in one trip to the media library and reordered by
   * dragging, or with the two buttons on each image for anyone not using a
   * mouse. Every image posts its attachment ID in yaml_cf[<field>][], so the
   * order in the markup is the order that is saved.
   *
   * @param string $field_name Field name, already namespaced for nested fields
   * @param mixed $field_value Stored value: an array of attachment IDs
   * @param bool $readonly Whether the field is display-only
   * @return void
   */
  private function render_image_list($field_name, $field_value, $readonly = false) {
    $image_ids = is_array($field_value) ? $field_value : [];

    echo '<div class="yaml-cf-image-list" data-field-name="' . esc_attr($field_name) . '">';

    // Keeps the field submittable after the last image is removed, so emptying
    // a gallery saves as empty instead of leaving the old images in place.
    if (!$readonly) {
      echo '<input type="hidden" class="yaml-cf-image-list-placeholder" name="yaml_cf[' . esc_attr($field_name) . '][]" value="" />';
    }

    echo '<ul class="yaml-cf-image-list-items' . ($readonly ? '' : ' is-sortable') . '">';
    foreach ($image_ids as $image_id) {
      if ($image_id === '' || $image_id === null) {
        continue;
      }
      $this->render_image_list_item($field_name, $image_id, $readonly);
    }
    echo '</ul>';

    if (!$readonly) {
      echo '<button type="button" class="button yaml-cf-add-image-list-item" data-target-name="' . esc_attr($field_name) . '">';
      echo esc_html__('Add images', 'yaml-custom-fields');
      echo '</button>';
      echo '<p class="yaml-cf-field-help">' . esc_html__('Drag the images to change their order.', 'yaml-custom-fields') . '</p>';
    }

    echo '</div>';
  }

  /**
   * Print one image inside a gallery field
   *
   * @param string $field_name Field name
   * @param int|string $image_id Attachment ID
   * @param bool $readonly Whether the field is display-only
   * @return void
   */
  private function render_image_list_item($field_name, $image_id, $readonly = false) {
    $thumb = wp_get_attachment_image_url($image_id, 'thumbnail');
    $alt = get_post_meta($image_id, '_wp_attachment_image_alt', true);

    echo '<li class="yaml-cf-image-list-item" data-id="' . esc_attr($image_id) . '">';
    echo '<input type="hidden" name="yaml_cf[' . esc_attr($field_name) . '][]" value="' . esc_attr($image_id) . '" />';

    if ($thumb) {
      echo '<img src="' . esc_url($thumb) . '" alt="' . esc_attr($alt) . '" class="yaml-cf-image-list-thumb" />';
    } else {
      // The attachment is gone from the media library, which is what an import
      // from another site looks like. Say so rather than showing a broken image.
      echo '<span class="yaml-cf-image-list-missing">';
      /* translators: %s: attachment ID */
      echo esc_html(sprintf(__('Image %s is missing', 'yaml-custom-fields'), $image_id));
      echo '</span>';
    }

    if (!$readonly) {
      echo '<span class="yaml-cf-image-list-actions">';
      echo '<button type="button" class="button button-small yaml-cf-move-image-earlier" title="' . esc_attr__('Move earlier', 'yaml-custom-fields') . '">';
      echo '<span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>';
      echo '<span class="screen-reader-text">' . esc_html__('Move earlier', 'yaml-custom-fields') . '</span>';
      echo '</button>';
      echo '<button type="button" class="button button-small yaml-cf-move-image-later" title="' . esc_attr__('Move later', 'yaml-custom-fields') . '">';
      echo '<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>';
      echo '<span class="screen-reader-text">' . esc_html__('Move later', 'yaml-custom-fields') . '</span>';
      echo '</button>';
      echo '<button type="button" class="button button-small yaml-cf-remove-image-list-item" title="' . esc_attr__('Remove', 'yaml-custom-fields') . '">';
      echo '<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>';
      echo '<span class="screen-reader-text">' . esc_html__('Remove this image', 'yaml-custom-fields') . '</span>';
      echo '</button>';
      echo '</span>';
    }

    echo '</li>';
  }

  /**
   * How many choices a taxonomy or collection may have before its field turns
   * into a search box instead of listing every option
   *
   * Listing 30 categories as checkboxes is friendlier than making someone type.
   * Listing 3,000 is a megabyte of markup nobody can read, so past this many
   * the field becomes a picker that searches over AJAX.
   *
   * @param string $type Field type: 'taxonomy' or 'data_object'
   * @param string $key Taxonomy name or collection slug
   * @return int Threshold
   */
  public static function get_picker_threshold($type, $key) {
    /**
     * Filter the number of choices at which a field becomes a search box.
     *
     * @param int $threshold Default 50.
     * @param string $type Field type: 'taxonomy' or 'data_object'.
     * @param string $key Taxonomy name or collection slug.
     */
    return (int) apply_filters('yaml_cf_picker_threshold', 50, $type, $key);
  }

  /**
   * Print a search-backed picker for a taxonomy or collection field
   *
   * Only the chosen values are in the markup; everything else is searched over
   * AJAX. That keeps a field over a 5,000-term taxonomy the same size as one
   * over five.
   *
   * @param array $args {
   *   @type string $field_name Field name, already namespaced for nested fields
   *   @type string $field_id Element ID base
   *   @type string $source 'taxonomy' or 'data_object'
   *   @type string $key Taxonomy name or collection slug
   *   @type bool $multiple Whether more than one value can be chosen
   *   @type array $selected Chosen values as [id => label]
   *   @type bool $readonly Whether the field is display-only
   *   @type int $total How many choices exist in total
   * }
   * @return void
   */
  private function render_picker($args) {
    $field_name = $args['field_name'];
    $field_id = $args['field_id'];
    $multiple = !empty($args['multiple']);
    $readonly = !empty($args['readonly']);
    $selected = isset($args['selected']) ? $args['selected'] : [];
    $results_id = $field_id . '_results';

    echo '<div class="yaml-cf-picker"';
    echo ' data-source="' . esc_attr($args['source']) . '"';
    echo ' data-key="' . esc_attr($args['key']) . '"';
    echo ' data-field-name="' . esc_attr($field_name) . '"';
    echo ' data-multiple="' . ($multiple ? '1' : '0') . '"';
    echo '>';

    // Keeps the field submittable once the last value is removed
    if (!$readonly && $multiple) {
      echo '<input type="hidden" class="yaml-cf-picker-placeholder" name="yaml_cf[' . esc_attr($field_name) . '][]" value="" />';
    }

    echo '<ul class="yaml-cf-picker-selected">';
    foreach ($selected as $value => $label) {
      $this->render_picker_chip($field_name, $value, $label, $multiple, $readonly);
    }
    echo '</ul>';

    // A single-value picker still needs an input to post an empty value
    if (!$readonly && !$multiple && empty($selected)) {
      echo '<input type="hidden" class="yaml-cf-picker-value" name="yaml_cf[' . esc_attr($field_name) . ']" value="" />';
    }

    if (!$readonly) {
      echo '<div class="yaml-cf-picker-search-row">';
      echo '<input type="search" class="yaml-cf-picker-search" id="' . esc_attr($field_id) . '"';
      echo ' role="combobox" aria-expanded="false" aria-autocomplete="list"';
      echo ' aria-controls="' . esc_attr($results_id) . '"';
      echo ' autocomplete="off"';
      echo ' placeholder="' . esc_attr__('Type to search…', 'yaml-custom-fields') . '" />';
      echo '</div>';

      echo '<ul class="yaml-cf-picker-results" id="' . esc_attr($results_id) . '" role="listbox" hidden></ul>';

      echo '<p class="yaml-cf-field-help">';
      /* translators: %d: number of available choices */
      echo esc_html(sprintf(_n('%d choice, so search for the one you want.', '%d choices, so search for the one you want.', (int) $args['total'], 'yaml-custom-fields'), (int) $args['total']));
      echo '</p>';
    }

    echo '</div>';
  }

  /**
   * Print one chosen value inside a picker
   *
   * @param string $field_name Field name
   * @param string|int $value Stored value
   * @param string $label Human-readable label
   * @param bool $multiple Whether the field holds more than one value
   * @param bool $readonly Whether the field is display-only
   * @return void
   */
  private function render_picker_chip($field_name, $value, $label, $multiple, $readonly = false) {
    $name = $multiple
      ? 'yaml_cf[' . $field_name . '][]'
      : 'yaml_cf[' . $field_name . ']';

    echo '<li class="yaml-cf-picker-chip" data-id="' . esc_attr($value) . '">';
    if (!$readonly) {
      echo '<input type="hidden" class="yaml-cf-picker-value" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" />';
    }
    echo '<span class="yaml-cf-picker-chip-label">' . esc_html($label) . '</span>';
    if (!$readonly) {
      echo '<button type="button" class="yaml-cf-picker-remove">';
      echo '<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>';
      echo '<span class="screen-reader-text">';
      /* translators: %s: the chosen value's name */
      echo esc_html(sprintf(__('Remove %s', 'yaml-custom-fields'), $label));
      echo '</span>';
      echo '</button>';
    }
    echo '</li>';
  }

  /**
   * Look up the names of the terms already chosen in a taxonomy field
   *
   * @param array $values Term IDs
   * @return array [term_id => name], skipping terms that no longer exist
   */
  private function get_selected_term_labels($values) {
    $labels = [];
    foreach ($values as $value) {
      if ($value === '' || $value === null) {
        continue;
      }
      $term = get_term((int) $value);
      if ($term && !is_wp_error($term)) {
        $labels[$term->term_id] = $term->name;
      }
    }
    return $labels;
  }

  /**
   * Look up the labels of the collection entries already chosen
   *
   * @param array $values Entry IDs
   * @param string $object_type Collection slug
   * @return array [entry_id => label]
   */
  private function get_selected_data_object_labels($values, $object_type) {
    $entries = get_option('yaml_cf_data_object_entries_' . $object_type, []);
    $types = get_option('yaml_cf_data_object_types', []);

    $label_field = '';
    if (isset($types[$object_type]['schema'])) {
      $schema = $this->parse_yaml_schema($types[$object_type]['schema']);
      if (!empty($schema['fields'][0]['name'])) {
        $label_field = $schema['fields'][0]['name'];
      }
    }

    $labels = [];
    foreach ($values as $value) {
      if ($value === '' || $value === null) {
        continue;
      }
      if (!isset($entries[$value])) {
        // An entry that was deleted, or came from another site with the import
        /* translators: %s: entry ID */
        $labels[$value] = sprintf(__('%s (missing)', 'yaml-custom-fields'), $value);
        continue;
      }
      $label = isset($entries[$value][$label_field]) ? $entries[$value][$label_field] : $value;
      $labels[$value] = is_string($label) && $label !== '' ? $label : $value;
    }
    return $labels;
  }

  /**
   * Pick the field whose text stands in for a block in its header
   *
   * The schema can name one with "summaryField"; otherwise the first string or
   * text field is used, which is the title in nearly every block.
   *
   * @param array $block_def Block definition
   * @return string Field name, or '' when the block has no text field
   */
  private function get_block_summary_field($block_def) {
    if (!empty($block_def['summaryField']) && is_string($block_def['summaryField'])) {
      return $block_def['summaryField'];
    }

    if (empty($block_def['fields']) || !is_array($block_def['fields'])) {
      return '';
    }

    foreach ($block_def['fields'] as $block_field) {
      $type = isset($block_field['type']) ? $block_field['type'] : 'string';
      if (($type === 'string' || $type === 'text') && !empty($block_field['name'])) {
        return $block_field['name'];
      }
    }

    return '';
  }

  /**
   * Print one empty block item, for the "Add section" button
   *
   * The admin script used to build this markup itself, in parallel with
   * render_block_item(). The two drifted: taxonomy, post_type and data_object
   * fields rendered differently in a section added on the page than in one that
   * came with it. Rendering both here means there is only one implementation to
   * keep right.
   *
   * @param array|null $schema Parsed schema holding the block field
   * @param string $field_name Name of the block field
   * @param string $block_type Which block type to add
   * @param int $index Position of the new block in the list
   * @param array|null $context Rendering context
   * @return bool Whether anything was printed
   */
  public function render_new_block_item($schema, $field_name, $block_type, $index, $context = null) {
    if (!is_array($schema) || empty($schema['fields']) || !is_array($schema['fields'])) {
      return false;
    }

    $fields = $this->normalize_field_shorthand($schema['fields']);

    $field = null;
    foreach ($fields as $candidate) {
      if (isset($candidate['name']) && $candidate['name'] === $field_name) {
        $field = $candidate;
        break;
      }
    }

    if (!$field || !isset($field['type']) || $field['type'] !== 'block') {
      return false;
    }

    $blocks = isset($field['blocks']) ? $field['blocks'] : [];
    $block_key = isset($field['blockKey']) ? $field['blockKey'] : 'type';

    // render_block_item() returns without printing when the type is unknown
    $this->render_block_item(
      $field,
      $blocks,
      [$block_key => $block_type],
      $index,
      $block_key,
      $context
    );

    return true;
  }

  private function render_block_item($field, $blocks, $block_data, $index, $block_key, $context = null) {
    $block_type = isset($block_data[$block_key]) ? $block_data[$block_key] : '';
    $block_def = null;

    // Check if block should be readonly (for template global display)
    $readonly = ($context && is_array($context) && isset($context['readonly']) && $context['readonly']);

    // Get ID suffix from context to make IDs unique in dual field rendering
    $id_suffix = ($context && is_array($context) && isset($context['id_suffix'])) ? $context['id_suffix'] : '';

    foreach ($blocks as $block) {
      if ($block['name'] === $block_type) {
        $block_def = $block;
        break;
      }
    }

    if (!$block_def) {
      return;
    }

    // Which field's text stands in for the block in its header, so that a page
    // of twenty sections does not read "Feature Block" twenty times
    $summary_field = $this->get_block_summary_field($block_def);
    $summary = '';
    if ($summary_field !== '' && isset($block_data[$summary_field]) && is_string($block_data[$summary_field])) {
      $summary = wp_trim_words($block_data[$summary_field], 12);
    }

    $fields_id = 'ycf_block_' . sanitize_html_class($field['name'] . '_' . $index) . $id_suffix;

    echo '<div class="yaml-cf-block-item" data-block-type="' . esc_attr($block_type) . '"';
    if ($summary_field !== '') {
      echo ' data-summary-field="' . esc_attr($summary_field) . '"';
    }
    echo '>';
    echo '<div class="yaml-cf-block-header">';
    echo '<button type="button" class="yaml-cf-block-toggle" aria-expanded="true" aria-controls="' . esc_attr($fields_id) . '">';
    echo '<span class="yaml-cf-block-caret" aria-hidden="true"></span>';
    echo '<span class="screen-reader-text">';
    /* translators: %s: block type name */
    echo esc_html(sprintf(__('Hide or show %s', 'yaml-custom-fields'), $block_def['label']));
    echo '</span>';
    echo '</button>';
    echo '<strong>' . esc_html($block_def['label']) . '</strong>';
    echo '<span class="yaml-cf-block-summary">' . esc_html($summary) . '</span>';
    if (!$readonly) {
      echo '<span class="yaml-cf-block-actions">';
      echo '<button type="button" class="button button-small yaml-cf-move-up" title="' . esc_attr__('Move up', 'yaml-custom-fields') . '"><span class="dashicons dashicons-arrow-up-alt"></span></button>';
      echo '<button type="button" class="button button-small yaml-cf-move-down" title="' . esc_attr__('Move down', 'yaml-custom-fields') . '"><span class="dashicons dashicons-arrow-down-alt"></span></button>';
      echo '<button type="button" class="button button-small yaml-cf-remove-block">' . esc_html__('Remove', 'yaml-custom-fields') . '</button>';
      echo '</span>';
    }
    echo '</div>';
    $hidden_attrs = [
      'type' => 'hidden',
      'name' => 'yaml_cf[' . $field['name'] . '][' . $index . '][' . $block_key . ']',
      'value' => $block_type,
    ];
    if ($readonly) {
      $hidden_attrs['disabled'] = 'disabled';
    }
    echo '<input';
          $this->output_html_attrs($hidden_attrs);
          echo ' />';

    if (isset($block_def['fields']) && is_array($block_def['fields'])) {
      echo '<div class="yaml-cf-block-fields" id="' . esc_attr($fields_id) . '">';

      foreach ($block_def['fields'] as $block_field) {
        $block_field_id = 'ycf_' . $field['name'] . '_' . $index . '_' . $block_field['name'] . $id_suffix;
        $block_field_value = isset($block_data[$block_field['name']]) ? $block_data[$block_field['name']] : '';
        $block_field_type = isset($block_field['type']) ? $block_field['type'] : 'string';

        // Generate code snippet for block field
        $function_name = 'ycf_get_field';
        $extra_snippet_params = '';
        if ($block_field_type === 'image') {
          $function_name = 'ycf_get_image';
          $extra_snippet_params = "'thumbnail', ";
        } elseif ($block_field_type === 'file') {
          $function_name = 'ycf_get_file';
        } elseif ($block_field_type === 'taxonomy') {
          $function_name = 'ycf_get_term';
        } elseif ($block_field_type === 'post_type') {
          $function_name = 'ycf_get_post_type';
        } elseif ($block_field_type === 'data_object') {
          $function_name = 'ycf_get_data_object';
        }
        $is_block_file_list_field = ($block_field_type === 'file') && isset($block_field['list']) && $block_field['list'];

        if ($is_block_file_list_field) {
          $block_snippet = "\$files = ycf_get_file('" . esc_js($block_field['name']) . "', null, \$block);\nif (!empty(\$files)) {\n  foreach (\$files as \$file) {\n    echo '<a href=\"' . esc_url(\$file['url']) . '\">' . esc_html(\$file['filename']) . '</a>';\n  }\n}";
        } else {
          $block_snippet = $function_name . "('" . esc_js($block_field['name']) . "', null, " . $extra_snippet_params . "\$block)";
        }
        $block_popover_id = 'snippet-' . sanitize_html_class($block_field_id);

        echo '<div class="yaml-cf-field" data-type="' . esc_attr($block_field_type) . '">';
        // For image/file/object fields with hidden inputs or no direct input, don't use 'for' attribute
        if ($block_field_type === 'image' || $block_field_type === 'file') {
          echo '<p class="yaml-cf-field-label">' . esc_html($block_field['label']) . '</p>';
        } elseif ($block_field_type === 'object') {
          // Use heading for object fields (container fields with nested fields)
          echo '<h4 class="yaml-cf-field-heading">' . esc_html($block_field['label']) . '</h4>';
        } else {
          echo '<label for="' . esc_attr($block_field_id) . '">' . esc_html($block_field['label']) . '</label>';
        }
        $this->render_field_help($block_field);

        if ($block_field_type === 'boolean') {
          echo '<input type="checkbox" name="yaml_cf[' . esc_attr($field['name']) . '][' . esc_attr($index) . '][' . esc_attr($block_field['name']) . ']" id="' . esc_attr($block_field_id) . '" value="1" ' . checked($block_field_value, 1, false) . disabled($readonly, true, false) . ' />';
        } elseif ($block_field_type === 'rich-text') {
          $editor_settings = [
            'textarea_name' => 'yaml_cf[' . $field['name'] . '][' . $index . '][' . $block_field['name'] . ']',
            'textarea_rows' => 10,
            'media_buttons' => !$readonly,
            'wpautop' => true,
            'tinymce' => [
              'toolbar1' => 'formatselect,bold,italic,bullist,numlist,link,unlink',
              'wp_autoresize_on' => true,
            ],
            'quicktags' => true,
          ];
          if ($readonly) {
            $editor_settings['tinymce']['readonly'] = 1;
            $editor_settings['quicktags'] = false;
          }
          wp_editor($block_field_value, $block_field_id, $editor_settings);
        } elseif ($block_field_type === 'text') {
          $textarea_attrs = [
            'name' => 'yaml_cf[' . $field['name'] . '][' . $index . '][' . $block_field['name'] . ']',
            'id' => $block_field_id,
            'rows' => 5,
            'class' => 'large-text',
          ];
          if ($readonly) {
            $textarea_attrs['disabled'] = 'disabled';
          }
          echo '<textarea';
          $this->output_html_attrs($textarea_attrs);
          echo '>' . esc_textarea($block_field_value) . '</textarea>';
        } elseif ($block_field_type === 'code') {
          $block_field_options = isset($block_field['options']) ? $block_field['options'] : [];
          $language = isset($block_field_options['language']) ? $block_field_options['language'] : 'html';
          echo '<textarea name="yaml_cf[' . esc_attr($field['name']) . '][' . esc_attr($index) . '][' . esc_attr($block_field['name']) . ']" id="' . esc_attr($block_field_id) . '" rows="10" class="large-text code" data-language="' . esc_attr($language) . '"' . disabled($readonly, true, false) . '>' . esc_textarea($block_field_value) . '</textarea>';
        } elseif ($block_field_type === 'number') {
          $block_field_options = isset($block_field['options']) ? $block_field['options'] : [];
          $number_attrs = [
            'type' => 'number',
            'name' => 'yaml_cf[' . $field['name'] . '][' . $index . '][' . $block_field['name'] . ']',
            'id' => $block_field_id,
            'value' => $block_field_value,
            'class' => 'small-text',
          ];
          if (isset($block_field_options['min'])) {
            $number_attrs['min'] = intval($block_field_options['min']);
          }
          if (isset($block_field_options['max'])) {
            $number_attrs['max'] = intval($block_field_options['max']);
          }
          if ($readonly) {
            $number_attrs['disabled'] = 'disabled';
          }
          echo '<input';
          $this->output_html_attrs($number_attrs);
          echo ' />';
        } elseif ($block_field_type === 'date') {
          $block_field_options = isset($block_field['options']) ? $block_field['options'] : [];
          $has_time = isset($block_field_options['time']) && $block_field_options['time'];
          $date_attrs = [
            'type' => $has_time ? 'datetime-local' : 'date',
            'name' => 'yaml_cf[' . $field['name'] . '][' . $index . '][' . $block_field['name'] . ']',
            'id' => $block_field_id,
            'value' => $block_field_value,
          ];
          if ($readonly) {
            $date_attrs['disabled'] = 'disabled';
          }
          echo '<input';
          $this->output_html_attrs($date_attrs);
          echo ' />';
        } elseif ($block_field_type === 'select') {
          $block_field_options = isset($block_field['options']) ? $block_field['options'] : [];
          $multiple = isset($block_field['multiple']) && $block_field['multiple'];

          // Check for values in options.values first, then fallback to root level values
          $values = [];
          if (isset($block_field_options['values']) && is_array($block_field_options['values'])) {
            $values = $block_field_options['values'];
          } elseif (isset($block_field['values'])) {
            $values = $block_field['values'];
          }

          echo '<select name="yaml_cf[' . esc_attr($field['name']) . '][' . esc_attr($index) . '][' . esc_attr($block_field['name']) . ']' . ($multiple ? '[]' : '') . '" id="' . esc_attr($block_field_id) . '" ' . ($multiple ? 'multiple' : '') . disabled($readonly, true, false) . '>';
          echo '<option value="">' . esc_html__('-- Select --', 'yaml-custom-fields') . '</option>';

          if (is_array($values) && !empty($values)) {
            foreach ($values as $option) {
              if (is_array($option)) {
                $opt_value = isset($option['value']) ? $option['value'] : '';
                $opt_label = isset($option['label']) ? $option['label'] : $opt_value;
              } else {
                $opt_value = $option;
                $opt_label = $option;
              }

              // Use loose comparison to handle string/int type differences
              $is_selected = false;
              if ($multiple && is_array($block_field_value)) {
                // For multiple select, check if value is in array
                $is_selected = in_array($opt_value, $block_field_value, false);
              } else {
                // For single select, use loose comparison
                $is_selected = ($block_field_value == $opt_value && $block_field_value !== '');
              }
              $block_opt_attrs = [
                'value' => $opt_value,
                'selected' => $is_selected ? 'selected' : false,
              ];
              echo '<option';
              $this->output_html_attrs($block_opt_attrs);
              echo '>' . esc_html($opt_label) . '</option>';
            }
          }

          echo '</select>';
        } elseif ($block_field_type === 'taxonomy') {
          $block_field_options = isset($block_field['options']) ? $block_field['options'] : [];
          $taxonomy = isset($block_field_options['taxonomy']) ? $block_field_options['taxonomy'] : 'category';
          $multiple = isset($block_field['multiple']) && $block_field['multiple'];

          // A big taxonomy gets a search box. It matters more inside a block:
          // the options would otherwise be repeated for every block on the page.
          $term_count = (int) wp_count_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);

          if ($term_count > self::get_picker_threshold('taxonomy', $taxonomy)) {
            $selected_values = is_array($block_field_value) ? $block_field_value : ($block_field_value !== '' ? [$block_field_value] : []);
            $this->render_picker([
              'field_name' => $field['name'] . '][' . $index . '][' . $block_field['name'],
              'field_id' => $block_field_id,
              'source' => 'taxonomy',
              'key' => $taxonomy,
              'multiple' => $multiple,
              'selected' => $this->get_selected_term_labels($selected_values),
              'readonly' => $readonly,
              'total' => $term_count,
            ]);
          } else {
            // Get terms for the specified taxonomy
            $terms = get_terms([
              'taxonomy' => $taxonomy,
              'hide_empty' => false,
            ]);

            if ($multiple) {
              $block_field_value = is_array($block_field_value) ? $block_field_value : ($block_field_value ? [$block_field_value] : []);
              echo '<select name="yaml_cf[' . esc_attr($field['name']) . '][' . esc_attr($index) . '][' . esc_attr($block_field['name']) . '][]" id="' . esc_attr($block_field_id) . '" multiple class="regular-text yaml-cf-multi-select"' . disabled($readonly, true, false) . '>';
            } else {
              echo '<select name="yaml_cf[' . esc_attr($field['name']) . '][' . esc_attr($index) . '][' . esc_attr($block_field['name']) . ']" id="' . esc_attr($block_field_id) . '" class="regular-text"' . disabled($readonly, true, false) . '>';
              echo '<option value="">' . esc_html(sprintf(
                /* translators: %s: field label */
                __('-- Select %s --', 'yaml-custom-fields'),
                $block_field['label']
              )) . '</option>';
            }

            if (!is_wp_error($terms) && !empty($terms)) {
              foreach ($terms as $term) {
                if ($multiple) {
                  $selected = in_array($term->term_id, $block_field_value) ? 'selected' : '';
                } else {
                  $selected = ($block_field_value == $term->term_id) ? 'selected' : '';
                }
                echo '<option value="' . esc_attr($term->term_id) . '" ' . esc_attr($selected) . '>' . esc_html($term->name) . '</option>';
              }
            }

            echo '</select>';
          }
        } elseif ($block_field_type === 'post_type') {
          // Get all public post types
          $post_types = get_post_types(['public' => true], 'objects');

          echo '<select name="yaml_cf[' . esc_attr($field['name']) . '][' . esc_attr($index) . '][' . esc_attr($block_field['name']) . ']" id="' . esc_attr($block_field_id) . '" class="regular-text"' . disabled($readonly, true, false) . '>';
          echo '<option value="">' . esc_html(sprintf(
              /* translators: %s: field label */
              __('-- Select %s --', 'yaml-custom-fields'),
              $block_field['label']
            )) . '</option>';

          foreach ($post_types as $post_type) {
            $selected = ($block_field_value === $post_type->name) ? 'selected' : '';
            echo '<option value="' . esc_attr($post_type->name) . '" ' . esc_attr($selected) . '>' . esc_html($post_type->label) . '</option>';
          }

          echo '</select>';
        } elseif ($block_field_type === 'data_object') {
          $block_field_options = isset($block_field['options']) ? $block_field['options'] : [];
          $object_type = isset($block_field_options['object_type']) ? $block_field_options['object_type'] : '';

          if (!empty($object_type)) {
            // Get data object entries
            $data_object_entries = get_option('yaml_cf_data_object_entries_' . $object_type, []);
            $data_object_types = get_option('yaml_cf_data_object_types', []);
            $type_name = isset($data_object_types[$object_type]) ? $data_object_types[$object_type]['name'] : ucfirst($object_type);
            $type_schema_yaml = isset($data_object_types[$object_type]) ? $data_object_types[$object_type]['schema'] : '';

            // Parse schema to get label field
            $parsed_schema = $this->parse_yaml_schema($type_schema_yaml);
            $label_field = null;
            if (!empty($parsed_schema['fields']) && is_array($parsed_schema['fields'])) {
              $label_field = $parsed_schema['fields'][0]['name'];
            }

            // A big collection gets a search box rather than an option per entry
            // repeated in every block on the page.
            if (count($data_object_entries) > self::get_picker_threshold('data_object', $object_type)) {
              $selected_values = is_array($block_field_value) ? $block_field_value : ($block_field_value !== '' ? [$block_field_value] : []);
              $this->render_picker([
                'field_name' => $field['name'] . '][' . $index . '][' . $block_field['name'],
                'field_id' => $block_field_id,
                'source' => 'data_object',
                'key' => $object_type,
                'multiple' => !empty($block_field['multiple']),
                'selected' => $this->get_selected_data_object_labels($selected_values, $object_type),
                'readonly' => $readonly,
                'total' => count($data_object_entries),
              ]);
            } else {
              echo '<select name="yaml_cf[' . esc_attr($field['name']) . '][' . esc_attr($index) . '][' . esc_attr($block_field['name']) . ']" id="' . esc_attr($block_field_id) . '" class="regular-text"' . disabled($readonly, true, false) . '>';
              echo '<option value="">' . esc_html(sprintf(
                /* translators: %s: field label */
                __('-- Select %s --', 'yaml-custom-fields'),
                $type_name
              )) . '</option>';

              if (!empty($data_object_entries) && is_array($data_object_entries)) {
                foreach ($data_object_entries as $entry_id => $entry_data) {
                  $selected = ($block_field_value === $entry_id) ? 'selected' : '';
                  $label = $entry_id;
                  if ($label_field && isset($entry_data[$label_field])) {
                    $label = $entry_data[$label_field];
                  }
                  echo '<option value="' . esc_attr($entry_id) . '" ' . esc_attr($selected) . '>' . esc_html($label) . '</option>';
                }
              }

              echo '</select>';
            }
          } else {
            echo '<p class="description yaml-cf-status-bad">' . esc_html__('This field needs a list to choose from. Add object_type to the schema.', 'yaml-custom-fields') . '</p>';
          }
        } elseif ($block_field_type === 'image' && !empty($block_field['list'])) {
          // A gallery inside a block
          $this->render_image_list(
            $field['name'] . '][' . $index . '][' . $block_field['name'],
            $block_field_value,
            $readonly
          );
        } elseif ($block_field_type === 'image') {
          echo '<input type="hidden" name="yaml_cf[' . esc_attr($field['name']) . '][' . esc_attr($index) . '][' . esc_attr($block_field['name']) . ']" id="' . esc_attr($block_field_id) . '" value="' . esc_attr($block_field_value) . '"' . disabled($readonly, true, false) . ' />';
          if (!$readonly) {
            echo '<div class="yaml-cf-media-buttons">';
            echo '<button type="button" class="button yaml-cf-upload-image" data-target="' . esc_attr($block_field_id) . '">' . esc_html__('Choose image', 'yaml-custom-fields') . '</button>';
            if ($block_field_value) {
              echo '<button type="button" class="button yaml-cf-clear-media" data-target="' . esc_attr($block_field_id) . '">' . esc_html__('Remove', 'yaml-custom-fields') . '</button>';
            }
            echo '</div>';
          }
          if ($block_field_value) {
            $image_url = wp_get_attachment_image_url($block_field_value, 'medium');
            if ($image_url) {
              echo '<div class="yaml-cf-image-preview"><img src="' . esc_url($image_url) . '" class="yaml-cf-image-preview-img" alt="" /></div>';
            }
          }
        } elseif ($block_field_type === 'file') {
          $block_field_name_path = $field['name'] . '][' . $index . '][' . $block_field['name'];
          $is_block_file_list = isset($block_field['list']) && $block_field['list'];

          if ($is_block_file_list) {
            $block_file_ids = is_array($block_field_value) ? $block_field_value : [];
            echo '<input type="hidden" class="yaml-cf-file-list-placeholder" name="yaml_cf[' . esc_attr($block_field_name_path) . '][]" value=""' . disabled($readonly, true, false) . ' />';
            echo '<div class="yaml-cf-file-list" data-field-name="' . esc_attr($block_field_name_path) . '">';
            echo '<div class="yaml-cf-file-list-items">';
            foreach ($block_file_ids as $block_file_id) {
              $this->render_file_list_item($block_field_name_path, $block_file_id);
            }
            echo '</div>';
            if (!$readonly) {
              echo '<button type="button" class="button yaml-cf-add-file-list-item" data-target-name="' . esc_attr($block_field_name_path) . '">' . esc_html__('Add files', 'yaml-custom-fields') . '</button>';
            }
            echo '</div>';
          } else {
            echo '<input type="hidden" name="yaml_cf[' . esc_attr($field['name']) . '][' . esc_attr($index) . '][' . esc_attr($block_field['name']) . ']" id="' . esc_attr($block_field_id) . '" value="' . esc_attr($block_field_value) . '"' . disabled($readonly, true, false) . ' />';
            if (!$readonly) {
              echo '<div class="yaml-cf-media-buttons">';
              echo '<button type="button" class="button yaml-cf-upload-file" data-target="' . esc_attr($block_field_id) . '">' . esc_html__('Choose file', 'yaml-custom-fields') . '</button>';
              if ($block_field_value) {
                echo '<button type="button" class="button yaml-cf-clear-media" data-target="' . esc_attr($block_field_id) . '">' . esc_html__('Remove', 'yaml-custom-fields') . '</button>';
              }
              echo '</div>';
            }
            if ($block_field_value) {
              $file_path = get_attached_file($block_field_value);
              if ($file_path) {
                echo '<div class="yaml-cf-file-name">' . esc_html(basename($file_path)) . '</div>';
              }
            }
          }
        } elseif ($block_field_type === 'string') {
          $block_field_options = isset($block_field['options']) ? $block_field['options'] : [];
          $string_attrs = [
            'type' => 'text',
            'name' => 'yaml_cf[' . $field['name'] . '][' . $index . '][' . $block_field['name'] . ']',
            'id' => $block_field_id,
            'value' => $block_field_value,
            'class' => 'regular-text',
          ];
          if (isset($block_field_options['minlength'])) {
            $string_attrs['minlength'] = intval($block_field_options['minlength']);
          }
          if (isset($block_field_options['maxlength'])) {
            $string_attrs['maxlength'] = intval($block_field_options['maxlength']);
          }
          if ($readonly) {
            $string_attrs['disabled'] = 'disabled';
          }
          echo '<input';
          $this->output_html_attrs($string_attrs);
          echo ' />';
        } else {
          // Default to text input for unknown types
          $default_attrs = [
            'type' => 'text',
            'name' => 'yaml_cf[' . $field['name'] . '][' . $index . '][' . $block_field['name'] . ']',
            'id' => $block_field_id,
            'value' => $block_field_value,
            'class' => 'regular-text',
          ];
          if ($readonly) {
            $default_attrs['disabled'] = 'disabled';
          }
          echo '<input';
          $this->output_html_attrs($default_attrs);
          echo ' />';
        }

        // Render snippet below the input field (administrators only; writers do not edit theme files)
        if (current_user_can('manage_options')) {
          echo '<span class="yaml-cf-snippet-wrapper">';
          echo '<button type="button" class="yaml-cf-copy-snippet" data-snippet="' . esc_attr($block_snippet) . '" data-popover="' . esc_attr($block_popover_id) . '">';
          echo '<span class="dashicons dashicons-editor-code"></span>';
          echo '<span class="snippet-text">' . esc_html__('Copy code', 'yaml-custom-fields') . '</span>';
          echo '</button>';
          echo '<span class="yaml-cf-snippet-popover" id="' . esc_attr($block_popover_id) . '" role="tooltip">';
          echo '<code>' . esc_html($block_snippet) . '</code>';
          echo '<span class="snippet-hint">' . esc_html__('Click to copy', 'yaml-custom-fields') . '</span>';
          echo '</span>';
          echo '</span>';
        }

        echo '</div>';
      }
      echo '</div>';
    }

    echo '</div>';
  }

  public function save_schema_data($post_id) {
    if (!isset($_POST['yaml_cf_meta_box_nonce'])) {
      return;
    }

    if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['yaml_cf_meta_box_nonce'])), 'yaml_cf_meta_box')) {
      return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
      return;
    }

    if (!current_user_can('edit_post', $post_id)) {
      return;
    }

    // Use post_raw() to get unslashed data safely without PHPCS warnings
    // Data will be sanitized by schema-aware sanitize_field_data()
    $posted_data = self::post_raw('yaml_cf', []);

    if (!empty($posted_data) && is_array($posted_data)) {
      // Get the template for this post
      $post = get_post($post_id);
      $template = $this->get_template_for_post($post);

      // Get schema for validation
      $schemas = get_option('yaml_cf_schemas', []);
      $schema = null;
      if (isset($schemas[$template])) {
        $schema = $this->parse_yaml_schema($schemas[$template]);
      }

      $sanitized_data = $this->sanitize_field_data($posted_data, $schema);
      $sanitized_data = $this->remove_template_global_only_fields($sanitized_data, $schema, $template);

      // Update post meta using WordPress API (handles serialization and caching)
      // update_post_meta() unslashes its value; slash first so backslashes in field values survive
      update_post_meta($post_id, '_yaml_cf_data', wp_slash($sanitized_data));

      // Store schema for validation purposes
      if ($schema) {
        update_post_meta($post_id, '_yaml_cf_schema', wp_slash($schema));
      }

      // Track this post for efficient cache clearing (avoids slow meta_query)
      $this->track_post_with_yaml_data($post_id);

      // Clear caches
      $this->clear_data_caches($post_id);
    }

    // Save per-field template global preferences
    $use_template_global_fields = isset($_POST['yaml_cf_use_template_global_fields']) && is_array($_POST['yaml_cf_use_template_global_fields'])
      ? array_map('sanitize_text_field', wp_unslash($_POST['yaml_cf_use_template_global_fields']))
      : [];
    update_post_meta($post_id, '_yaml_cf_use_template_global_fields', wp_slash($use_template_global_fields));
  }

  /**
   * Remove values for fields that exist only in the template global schema
   * Older versions submitted read-only copies of these fields with the post,
   * which froze a stale snapshot of the template global value in post meta.
   *
   * @param array $data Sanitized post data
   * @param array|null $schema Parsed post (template) schema
   * @param string $template Template file name
   * @return array Data without template-global-only fields
   */
  private function remove_template_global_only_fields($data, $schema, $template) {
    if (!is_array($data) || !is_array($schema)) {
      return $data;
    }

    $template_global_schema = $this->parse_yaml_schema(yaml_cf_get_template_global_schema($template));
    if (!is_array($template_global_schema) || empty($template_global_schema['fields']) || !is_array($template_global_schema['fields'])) {
      return $data;
    }

    $sanitizer = \YamlCF\Core\Plugin::getInstance()->get('data_sanitizer');
    foreach ($template_global_schema['fields'] as $field) {
      if (is_array($field) && isset($field['name']) && !$sanitizer->getFieldDefinition($schema, $field['name'])) {
        unset($data[$field['name']]);
      }
    }

    return $data;
  }

  /**
   * Clear plugin data caches (delegates to YamlCF\Cache\CacheManager)
   *
   * @param int|null $post_id Optional. Specific post ID to clear cache for. If null, clears all.
   */
  private function clear_data_caches($post_id = null) {
    \YamlCF\Core\Plugin::getInstance()->get('cache_manager')->clearDataCaches($post_id);
  }

  /**
   * Export all data object types and their entries as a download
   * Delegates to YamlCF\ImportExport\DataObjectExporter
   */
  public function export_data_objects() {
    $exporter = \YamlCF\Core\Plugin::getInstance()->get('data_object_exporter');
    $exporter->download($exporter->export());
  }
}

function yaml_cf_init() {
  return YAML_Custom_Fields::get_instance();
}

add_action('plugins_loaded', 'yaml_cf_init');

// Initialize new Plugin architecture (Phase 1: running in parallel with old code)
// Note: PSR-4 autoloading for YamlCF namespace is now included in build/vendor/scoper-autoload.php
// Old code continues to handle everything for now
function yaml_cf_init_new_architecture() {
  \YamlCF\Core\Plugin::getInstance();
}
add_action('plugins_loaded', 'yaml_cf_init_new_architecture', 11);

/**
 * Get a specific field value from YAML Custom Fields data
 *
 * @param string $field_name The name of the field to retrieve
 * @param int|string|null $post_id Optional. Post ID or 'partial:filename' for partials. Defaults to current post.
 * @param array|null $context_data Optional. Array data to search in (useful for nested blocks). Defaults to null.
 * @return mixed The field value, or null if not found
 *
 * Usage Examples:
 *
 * Basic Usage (Current Page):
 *   $title = yaml_cf_get_field('hero_title', null);
 *
 * Specific Post:
 *   $title = yaml_cf_get_field('hero_title', 123);
 *
 * Partial Template:
 *   $logo = yaml_cf_get_field('logo', 'partial:header.php');
 *
 * Block Fields (with context):
 *   $blocks = yaml_cf_get_field('features', null);
 *   foreach ($blocks as $block) {
 *     $title = yaml_cf_get_field('title', null, $block);
 *     $description = yaml_cf_get_field('description', null, $block);
 *   }
 */
function yaml_cf_get_field($field_name, $post_id = null, $context_data = null) {
  $value = yaml_cf_get_field_unfiltered($field_name, $post_id, $context_data);

  /**
   * Filter a field's value on the way out to the theme.
   *
   * Runs for every way a value can be found: post data, template global data,
   * site details and block context alike.
   *
   * @param mixed $value The value, or null when the field has none.
   * @param string $field_name The field's name.
   * @param int|string|null $post_id Post ID, 'partial:<file>', or null for the current post.
   * @param array|null $context_data Block data the value was read from, if any.
   */
  return apply_filters('yaml_cf_field_value', $value, $field_name, $post_id, $context_data);
}

/**
 * Find a field's value, before the yaml_cf_field_value filter runs
 *
 * Kept separate so that every route to a value passes through one filter, and
 * so that internal callers can opt out of filtering.
 *
 * @param string $field_name The name of the field
 * @param int|string|null $post_id Post ID, 'partial:<file>', or null for the current post
 * @param array|null $context_data Block data to read from instead of the post
 * @return mixed The value, or null
 */
function yaml_cf_get_field_unfiltered($field_name, $post_id = null, $context_data = null) {
  $value = null;
  $schema = null;
  $plugin = null;
  $template = null;
  $post = null;

  // If context data is provided, search within that array
  if (is_array($context_data)) {
    $value = isset($context_data[$field_name]) ? $context_data[$field_name] : null;
    // For context data, we can't easily get the schema, so use helper function for decoding
    return yaml_cf_maybe_decode_code_field($value);
  }

  // Handle partials
  if (is_string($post_id) && strpos($post_id, 'partial:') === 0) {
    $partial_file = str_replace('partial:', '', $post_id);
    $partial_data = get_option('yaml_cf_partial_data', []);

    if (isset($partial_data[$partial_file][$field_name])) {
      $value = $partial_data[$partial_file][$field_name];

      // Partial schemas are stored alongside template schemas
      $partial_schemas = get_option('yaml_cf_schemas', []);
      if (isset($partial_schemas[$partial_file])) {
        $schema = $partial_schemas[$partial_file];
      }

      return yaml_cf_auto_decode_if_code_field($value, $schema, $field_name);
    }

    return null;
  }

  // Handle post/page data
  if ($post_id === null) {
    $post_id = get_the_ID();
  }

  if (!$post_id) {
    return null;
  }

  $data = get_post_meta($post_id, '_yaml_cf_data', true);
  if (!is_array($data)) {
    $data = [];
  }

  // Get post, plugin, and template for schema lookup
  $post = get_post($post_id);
  if ($post) {
    $plugin = YAML_Custom_Fields::get_instance();
    $template = $plugin->get_template_for_post($post);

    // Get schema for this template
    $schemas = get_option('yaml_cf_schemas', []);
    if (isset($schemas[$template])) {
      $schema = $schemas[$template];
    }
  }

  // Check if template global is enabled for this specific field
  $use_template_global_fields = get_post_meta($post_id, '_yaml_cf_use_template_global_fields', true);
  if (is_array($use_template_global_fields) && isset($use_template_global_fields[$field_name]) && $use_template_global_fields[$field_name] === '1') {
    if ($post && $template) {
      // Get template global data
      $template_global_data_array = get_option('yaml_cf_template_global_data', []);
      if (isset($template_global_data_array[$template][$field_name])) {
        $value = $template_global_data_array[$template][$field_name];
        return yaml_cf_auto_decode_if_code_field($value, yaml_cf_get_template_global_schema($template), $field_name);
      }
    }
  }

  // Check for site details (global data)
  // (Only if the template has site details enabled)
  if ($post && $template) {
    $template_settings = get_option('yaml_cf_template_settings', []);
    $use_global = isset($template_settings[$template . '_use_global']) && $template_settings[$template . '_use_global'];

    if ($use_global) {
      $global_data = get_option('yaml_cf_global_data', []);
      if (is_array($global_data) && isset($global_data[$field_name])) {
        $value = $global_data[$field_name];
        return yaml_cf_auto_decode_if_code_field($value, get_option('yaml_cf_global_schema', ''), $field_name);
      }
    }
  }

  // Fields defined only in the template global schema always come from template global data
  // (older versions stored stale copies of them in post data)
  if ($post && $template && $schema) {
    $template_global_schema = yaml_cf_get_template_global_schema($template);
    if ($template_global_schema !== '') {
      $sanitizer = \YamlCF\Core\Plugin::getInstance()->get('data_sanitizer');
      $in_post_schema = $sanitizer->getFieldDefinition(yaml_cf_parse_schema_cached($schema), $field_name);
      $in_template_global_schema = $sanitizer->getFieldDefinition(yaml_cf_parse_schema_cached($template_global_schema), $field_name);
      if (!$in_post_schema && $in_template_global_schema) {
        $template_global_data_array = get_option('yaml_cf_template_global_data', []);
        if (!isset($template_global_data_array[$template][$field_name])) {
          return null;
        }
        return yaml_cf_auto_decode_if_code_field($template_global_data_array[$template][$field_name], $template_global_schema, $field_name);
      }
    }
  }

  // Finally, check post-specific data
  if (isset($data[$field_name])) {
    $value = $data[$field_name];
    return yaml_cf_auto_decode_if_code_field($value, $schema, $field_name);
  }

  // Fallback: check template global data for fields defined only in template global schema
  if ($post && $template) {
    $template_global_data_array = get_option('yaml_cf_template_global_data', []);
    if (isset($template_global_data_array[$template][$field_name])) {
      $value = $template_global_data_array[$template][$field_name];
      return yaml_cf_auto_decode_if_code_field($value, yaml_cf_get_template_global_schema($template), $field_name);
    }
  }

  return null;
}

/**
 * Helper function to decode a value if it's a code field.
 * When the schema defines the field with a type other than 'code', the value is
 * returned as stored, so a marker-prefixed value in a non-code field is never decoded.
 *
 * @param mixed $value The field value.
 * @param array|string|null $schema Parsed schema or YAML schema string for the data source.
 * @param string $field_name The top-level field name.
 * @return mixed The decoded value if it is an encoded code field, otherwise the original value.
 */
function yaml_cf_auto_decode_if_code_field($value, $schema, $field_name) {
  if (is_string($value) && $schema && $field_name !== '') {
    $parsed = is_array($schema) ? $schema : yaml_cf_parse_schema_cached($schema);
    $field_def = \YamlCF\Core\Plugin::getInstance()->get('data_sanitizer')->getFieldDefinition($parsed, $field_name);
    if ($field_def && (!isset($field_def['type']) || $field_def['type'] !== 'code')) {
      return $value;
    }
  }

  return yaml_cf_maybe_decode_code_field($value);
}

/**
 * Parse a YAML schema string once per request.
 *
 * @param string $yaml YAML schema.
 * @return array|null Parsed schema or null if invalid.
 */
function yaml_cf_parse_schema_cached($yaml) {
  static $cache = [];

  if (!is_string($yaml) || $yaml === '') {
    return null;
  }

  $key = md5($yaml);
  if (!array_key_exists($key, $cache)) {
    $parsed = \YamlCF\Core\Plugin::getInstance()->get('schema_parser')->parse($yaml);
    $cache[$key] = is_array($parsed) ? $parsed : null;
  }

  return $cache[$key];
}

/**
 * Get the YAML template global schema for a template.
 *
 * @param string $template Template file name.
 * @return string YAML schema or '' if none.
 */
function yaml_cf_get_template_global_schema($template) {
  $template_global_schemas = get_option('yaml_cf_template_global_schemas', []);
  return isset($template_global_schemas[$template]) && is_string($template_global_schemas[$template]) ? $template_global_schemas[$template] : '';
}

/**
 * Helper function to decode a potentially base64-encoded code field value.
 * Only decodes values with the YAMLCF marker prefix.
 * Safe to call on any value - returns the original if not a marked code field.
 *
 * @param mixed $value The value to decode.
 * @return mixed The decoded value if it's a marked code field, otherwise the original value.
 */
function yaml_cf_maybe_decode_code_field($value) {
  if (empty($value) || !is_string($value)) {
    return $value;
  }

  // Only decode values with the marker prefix
  $marker = YAML_Custom_Fields::CODE_FIELD_MARKER;
  if (strpos($value, $marker) === 0) {
    $encoded = substr($value, strlen($marker));
    $decoded = base64_decode($encoded, true);
    if ($decoded !== false) {
      return $decoded;
    }
  }

  return $value;
}

/**
 * Get all YAML Custom Fields fields for the current post or partial
 *
 * @param int|string $post_id Optional. Post ID or 'partial:filename' for partials. Defaults to current post.
 * @return array Array of all field values
 *
 * Usage in templates:
 * - For page/post: $fields = yaml_cf_get_fields();
 * - For specific post: $fields = yaml_cf_get_fields(123);
 * - For partial: $fields = yaml_cf_get_fields('partial:header.php');
 */
function yaml_cf_get_fields($post_id = null) {
  // Handle partials
  if (is_string($post_id) && strpos($post_id, 'partial:') === 0) {
    $partial_file = str_replace('partial:', '', $post_id);
    $partial_data = get_option('yaml_cf_partial_data', []);

    $data = isset($partial_data[$partial_file]) ? $partial_data[$partial_file] : [];
    $partial_schemas = get_option('yaml_cf_schemas', []);
    $partial_schema = isset($partial_schemas[$partial_file]) ? $partial_schemas[$partial_file] : null;
    return yaml_cf_decode_all_code_fields($data, $partial_schema);
  }

  // Handle post/page data
  if ($post_id === null) {
    $post_id = get_the_ID();
  }

  if (!$post_id) {
    return [];
  }

  $merged_data = [];

  // Start with post data
  $post_data = get_post_meta($post_id, '_yaml_cf_data', true);
  if (is_array($post_data)) {
    $merged_data = $post_data;
  }

  // Get post and template info
  $post = get_post($post_id);
  if (!$post) {
    return yaml_cf_decode_all_code_fields($merged_data);
  }

  $plugin = YAML_Custom_Fields::get_instance();
  $template = $plugin->get_template_for_post($post);
  $template_settings = get_option('yaml_cf_template_settings', []);

  // Decode each data source against its own schema before merging
  $schemas = get_option('yaml_cf_schemas', []);
  $post_schema = isset($schemas[$template]) ? $schemas[$template] : null;
  $merged_data = yaml_cf_decode_all_code_fields($merged_data, $post_schema);

  // Fields defined only in the template global schema always come from template global data
  $template_global_schema = yaml_cf_get_template_global_schema($template);
  $parsed_template_global_schema = yaml_cf_parse_schema_cached($template_global_schema);
  if ($post_schema && $parsed_template_global_schema && !empty($parsed_template_global_schema['fields']) && is_array($parsed_template_global_schema['fields'])) {
    $sanitizer = \YamlCF\Core\Plugin::getInstance()->get('data_sanitizer');
    $parsed_post_schema = yaml_cf_parse_schema_cached($post_schema);
    $template_global_data_array = get_option('yaml_cf_template_global_data', []);
    $template_global_values = isset($template_global_data_array[$template]) && is_array($template_global_data_array[$template]) ? $template_global_data_array[$template] : [];
    foreach ($parsed_template_global_schema['fields'] as $field) {
      if (!is_array($field) || !isset($field['name']) || $sanitizer->getFieldDefinition($parsed_post_schema, $field['name'])) {
        continue;
      }
      $name = $field['name'];
      if (isset($template_global_values[$name])) {
        $merged_data[$name] = yaml_cf_decode_all_code_fields([$name => $template_global_values[$name]], $parsed_template_global_schema)[$name];
      } else {
        unset($merged_data[$name]);
      }
    }
  }

  // Merge site details (global data), if enabled for the template
  $use_global = isset($template_settings[$template . '_use_global']) && $template_settings[$template . '_use_global'];
  if ($use_global) {
    $global_data = get_option('yaml_cf_global_data', []);
    if (is_array($global_data)) {
      $global_data = yaml_cf_decode_all_code_fields($global_data, get_option('yaml_cf_global_schema', ''));
      $merged_data = array_merge($global_data, $merged_data);
    }
  }

  // Merge template global data (if enabled for post)
  $use_template_global = get_post_meta($post_id, '_yaml_cf_use_template_global', true);
  if ($use_template_global) {
    $template_global_data_array = get_option('yaml_cf_template_global_data', []);
    if (isset($template_global_data_array[$template]) && is_array($template_global_data_array[$template])) {
      $template_global_data = yaml_cf_decode_all_code_fields($template_global_data_array[$template], yaml_cf_get_template_global_schema($template));
      $merged_data = array_merge($merged_data, $template_global_data);
    }
  }

  // Apply overrides (highest priority)
  if (isset($merged_data['_template_global_override']) && is_array($merged_data['_template_global_override'])) {
    $merged_data = array_merge($merged_data, $merged_data['_template_global_override']);
  }

  // Remove internal keys
  unset($merged_data['_template_global_override']);

  return $merged_data;
}

/**
 * Recursively decode all base64-encoded code field values in an array.
 *
 * @param mixed $data The data to process.
 * @param array|string|null $schema Optional schema for the top level; top-level fields it defines
 *                                  with a type other than 'code' are not decoded.
 * @return mixed The data with code fields decoded.
 */
function yaml_cf_decode_all_code_fields($data, $schema = null) {
  if (!is_array($data)) {
    return yaml_cf_maybe_decode_code_field($data);
  }

  foreach ($data as $key => $value) {
    if (is_array($value)) {
      $data[$key] = yaml_cf_decode_all_code_fields($value);
    } elseif (is_string($value)) {
      $data[$key] = $schema ? yaml_cf_auto_decode_if_code_field($value, $schema, (string) $key) : yaml_cf_maybe_decode_code_field($value);
    }
  }

  return $data;
}

/**
 * Check if a field exists
 *
 * @param string $field_name The name of the field to check
 * @param int|string $post_id Optional. Post ID or 'partial:filename' for partials. Defaults to current post.
 * @return bool True if field exists, false otherwise
 */
function yaml_cf_has_field($field_name, $post_id = null) {
  $value = yaml_cf_get_field($field_name, $post_id);
  return $value !== null;
}

/**
 * Get a global field value
 *
 * @param string $field_name The name of the field to retrieve
 * @return mixed The field value or null if not found
 *
 * Usage in templates:
 * - $background = yaml_cf_get_global_field('site_background');
 */
function yaml_cf_get_global_field($field_name) {
  $global_data = get_option('yaml_cf_global_data', []);

  if (is_array($global_data) && isset($global_data[$field_name])) {
    return yaml_cf_auto_decode_if_code_field($global_data[$field_name], get_option('yaml_cf_global_schema', ''), $field_name);
  }

  return null;
}

/**
 * Get all global fields
 *
 * @return array Array of all global field values
 *
 * Usage in templates:
 * - $global_fields = yaml_cf_get_global_fields();
 */
function yaml_cf_get_global_fields() {
  $global_data = get_option('yaml_cf_global_data', []);
  $data = is_array($global_data) ? $global_data : [];
  return yaml_cf_decode_all_code_fields($data, get_option('yaml_cf_global_schema', ''));
}

/**
 * Check if a global field exists
 *
 * @param string $field_name The name of the field to check
 * @return bool True if field exists, false otherwise
 *
 * Usage in templates:
 * - if (yaml_cf_has_global_field('site_background')) { ... }
 */
function yaml_cf_has_global_field($field_name) {
  $value = yaml_cf_get_global_field($field_name);
  return $value !== null;
}

// Shorter aliases for convenience
if (!function_exists('ycf_get_field')) {
  /**
   * Alias for yaml_cf_get_field()
   *
   * @deprecated Use yaml_cf_get_field() instead
   */
  function ycf_get_field($field_name, $post_id = null, $context_data = null) {
    return yaml_cf_get_field($field_name, $post_id, $context_data);
  }
}

if (!function_exists('ycf_get_fields')) {
  /**
   * Alias for yaml_cf_get_fields()
   *
   * @deprecated Use yaml_cf_get_fields() instead
   */
  function ycf_get_fields($post_id = null) {
    return yaml_cf_get_fields($post_id);
  }
}

if (!function_exists('ycf_has_field')) {
  /**
   * Alias for yaml_cf_has_field()
   *
   * @deprecated Use yaml_cf_has_field() instead
   */
  function ycf_has_field($field_name, $post_id = null) {
    return yaml_cf_has_field($field_name, $post_id);
  }
}

/**
 * Get image data for an image field
 * Returns an array with image information
 *
 * @param string $field_name The name of the image field
 * @param int|string|null $post_id Optional. Post ID or 'partial:filename' for partials. Defaults to current post.
 * @param string $size Optional. Image size (thumbnail, medium, large, full). Defaults to 'full'.
 * @param array|null $context_data Optional. Array data to search in (useful for nested blocks). Defaults to null.
 * @return array|null Array with 'id', 'url', 'alt', 'title', 'caption', 'description', 'width', 'height' keys, an array of such arrays if the field has list: true, or null if not found
 *
 * Usage Examples:
 *
 * Basic Usage (Current Page):
 *   $hero = yaml_cf_get_image('hero_image', null, 'large');
 *   if ($hero) {
 *     echo '<img src="' . esc_url($hero['url']) . '" alt="' . esc_attr($hero['alt']) . '" />';
 *   }
 *
 * Gallery (image field with list: true):
 *   $gallery = yaml_cf_get_image('gallery', null, 'medium');
 *   if ($gallery) {
 *     foreach ($gallery as $image) {
 *       echo '<img src="' . esc_url($image['url']) . '" alt="' . esc_attr($image['alt']) . '" />';
 *     }
 *   }
 *
 * With Different Sizes:
 *   $thumb = yaml_cf_get_image('featured_image', null, 'thumbnail');
 *   $medium = yaml_cf_get_image('featured_image', null, 'medium');
 *   $large = yaml_cf_get_image('featured_image', null, 'large');
 *   $full = yaml_cf_get_image('featured_image', null, 'full');
 *
 * Block Fields (with context):
 *   $blocks = yaml_cf_get_field('team_members', null);
 *   foreach ($blocks as $block) {
 *     $photo = yaml_cf_get_image('photo', null, 'thumbnail', $block);
 *     if ($photo) {
 *       echo '<img src="' . esc_url($photo['url']) . '" alt="' . esc_attr($photo['alt']) . '" />';
 *     }
 *   }
 *
 * Partial Template:
 *   $logo = yaml_cf_get_image('site_logo', 'partial:header.php', 'medium');
 */
function yaml_cf_get_image($field_name, $post_id = null, $size = 'full', $context_data = null) {
  $attachment_ids = yaml_cf_get_field($field_name, $post_id, $context_data);

  if (!$attachment_ids) {
    return null;
  }

  // Handle a gallery (image field with list: true)
  if (is_array($attachment_ids)) {
    $images = [];
    foreach ($attachment_ids as $attachment_id) {
      $image_data = yaml_cf_resolve_image_data($attachment_id, $size);
      if ($image_data) {
        $images[] = $image_data;
      }
    }
    return !empty($images) ? $images : null;
  }

  return yaml_cf_resolve_image_data($attachment_ids, $size);
}

if (!function_exists('yaml_cf_resolve_image_data')) {
  /**
   * Build the image-info array for a single attachment ID
   *
   * @param int|string $attachment_id Attachment ID
   * @param string $size Image size
   * @return array|null Image data, or null when the ID is not an attachment
   */
  function yaml_cf_resolve_image_data($attachment_id, $size = 'full') {
    if (!$attachment_id || !is_numeric($attachment_id)) {
      return null;
    }

    $image_data = [
      'id' => $attachment_id,
      'url' => wp_get_attachment_image_url($attachment_id, $size),
      'alt' => get_post_meta($attachment_id, '_wp_attachment_image_alt', true),
      'title' => get_the_title($attachment_id),
      'caption' => wp_get_attachment_caption($attachment_id),
      'description' => get_post_field('post_content', $attachment_id),
    ];

    $metadata = wp_get_attachment_metadata($attachment_id);
    if ($metadata && isset($metadata['width']) && isset($metadata['height'])) {
      $image_data['width'] = $metadata['width'];
      $image_data['height'] = $metadata['height'];
    }

    // Get specific size dimensions if not full
    if ($size !== 'full' && isset($metadata['sizes'][$size])) {
      $image_data['width'] = $metadata['sizes'][$size]['width'];
      $image_data['height'] = $metadata['sizes'][$size]['height'];
    }

    return $image_data;
  }
}

if (!function_exists('ycf_get_image')) {
  /**
   * Alias for yaml_cf_get_image()
   *
   * @deprecated Use yaml_cf_get_image() instead
   */
  function ycf_get_image($field_name, $post_id = null, $size = 'full', $context_data = null) {
    return yaml_cf_get_image($field_name, $post_id, $size, $context_data);
  }
}

/**
 * Get file data for a file field
 * Returns an array with file information
 *
 * @param string $field_name The name of the file field
 * @param int|string|null $post_id Optional. Post ID or 'partial:filename' for partials. Defaults to current post.
 * @param array|null $context_data Optional. Array data to search in (useful for nested blocks). Defaults to null.
 * @return array|null Array with 'id', 'url', 'path', 'filename', 'filesize', 'mime_type', 'title' keys, an array of such arrays if the field has list: true, or null if not found
 *
 * Usage Examples:
 *
 * Basic Usage (Current Page):
 *   $pdf = yaml_cf_get_file('brochure', null);
 *   if ($pdf) {
 *     echo '<a href="' . esc_url($pdf['url']) . '" download>' . esc_html($pdf['filename']) . '</a>';
 *     echo '<span>(' . size_format($pdf['filesize']) . ')</span>';
 *   }
 *
 * Block Fields (with context):
 *   $blocks = yaml_cf_get_field('downloads', null);
 *   foreach ($blocks as $block) {
 *     $file = yaml_cf_get_file('document', null, $block);
 *     $title = yaml_cf_get_field('title', null, $block);
 *     if ($file) {
 *       echo '<div class="download">';
 *       echo '<h3>' . esc_html($title) . '</h3>';
 *       echo '<a href="' . esc_url($file['url']) . '">' . esc_html($file['filename']) . '</a>';
 *       echo '</div>';
 *     }
 *   }
 *
 * Partial Template:
 *   $terms = yaml_cf_get_file('terms_pdf', 'partial:footer.php');
 *
 * Multiple Files (file field with list: true):
 *   $audio_files = yaml_cf_get_file('audio', null, $block);
 *   if ($audio_files && is_array($audio_files)) {
 *     foreach ($audio_files as $audio) {
 *       echo '<audio controls src="' . esc_url($audio['url']) . '"></audio>';
 *     }
 *   }
 */
function yaml_cf_get_file($field_name, $post_id = null, $context_data = null) {
  $attachment_ids = yaml_cf_get_field($field_name, $post_id, $context_data);

  if (!$attachment_ids) {
    return null;
  }

  // Handle a list of file IDs (file field with list: true)
  if (is_array($attachment_ids)) {
    $files = [];
    foreach ($attachment_ids as $attachment_id) {
      $file_data = yaml_cf_resolve_file_data($attachment_id);
      if ($file_data) {
        $files[] = $file_data;
      }
    }
    return !empty($files) ? $files : null;
  }

  return yaml_cf_resolve_file_data($attachment_ids);
}

if (!function_exists('yaml_cf_resolve_file_data')) {
  /**
   * Build the file-info array for a single attachment ID
   *
   * @param mixed $attachment_id Attachment ID
   * @return array|null Array with 'id', 'url', 'path', 'filename', 'filesize', 'mime_type', 'title' keys or null if not found
   */
  function yaml_cf_resolve_file_data($attachment_id) {
    if (!$attachment_id || !is_numeric($attachment_id)) {
      return null;
    }

    $file_path = get_attached_file($attachment_id);
    $file_url = wp_get_attachment_url($attachment_id);

    if (!$file_path || !$file_url) {
      return null;
    }

    return [
      'id' => $attachment_id,
      'url' => $file_url,
      'path' => $file_path,
      'filename' => basename($file_path),
      'filesize' => filesize($file_path),
      'mime_type' => get_post_mime_type($attachment_id),
      'title' => get_the_title($attachment_id),
    ];
  }
}

if (!function_exists('ycf_get_file')) {
  /**
   * Alias for yaml_cf_get_file()
   *
   * @deprecated Use yaml_cf_get_file() instead
   */
  function ycf_get_file($field_name, $post_id = null, $context_data = null) {
    return yaml_cf_get_file($field_name, $post_id, $context_data);
  }
}

/**
 * Get term/taxonomy data for a taxonomy field
 * Returns term object(s) or an array of term objects
 *
 * @param string $field_name The name of the taxonomy field
 * @param int|string|null $post_id Optional. Post ID or 'partial:filename' for partials. Defaults to current post.
 * @param array|null $context_data Optional. Array data to search in (useful for nested blocks). Defaults to null.
 * @return WP_Term|WP_Term[]|null Single term object, array of term objects, or null if not found
 *
 * Usage Examples:
 *
 * Basic Usage (Single Category):
 *   $category = yaml_cf_get_term('post_category', null);
 *   if ($category) {
 *     echo '<a href="' . get_term_link($category) . '">' . esc_html($category->name) . '</a>';
 *   }
 *
 * Multiple Categories:
 *   $categories = yaml_cf_get_term('post_categories', null);
 *   if ($categories && is_array($categories)) {
 *     foreach ($categories as $category) {
 *       echo '<span>' . esc_html($category->name) . '</span>';
 *     }
 *   }
 *
 * Block Fields (with context):
 *   $blocks = yaml_cf_get_field('articles', null);
 *   foreach ($blocks as $block) {
 *     $category = yaml_cf_get_term('category', null, $block);
 *     if ($category) {
 *       echo '<span class="cat">' . esc_html($category->name) . '</span>';
 *     }
 *   }
 */
function yaml_cf_get_term($field_name, $post_id = null, $context_data = null) {
  $term_ids = yaml_cf_get_field($field_name, $post_id, $context_data);

  if (!$term_ids) {
    return null;
  }

  // Handle single term ID
  if (is_numeric($term_ids)) {
    $term = get_term($term_ids);
    return (!is_wp_error($term) && $term) ? $term : null;
  }

  // Handle multiple term IDs
  if (is_array($term_ids)) {
    $terms = [];
    foreach ($term_ids as $term_id) {
      if (is_numeric($term_id)) {
        $term = get_term($term_id);
        if (!is_wp_error($term) && $term) {
          $terms[] = $term;
        }
      }
    }
    return !empty($terms) ? $terms : null;
  }

  return null;
}

if (!function_exists('ycf_get_term')) {
  /**
   * Alias for yaml_cf_get_term()
   *
   * @deprecated Use yaml_cf_get_term() instead
   */
  function ycf_get_term($field_name, $post_id = null, $context_data = null) {
    return yaml_cf_get_term($field_name, $post_id, $context_data);
  }
}

/**
 * Get post type object for a post_type field
 * Returns WP_Post_Type object
 *
 * @param string $field_name The name of the post_type field
 * @param int|string|null $post_id Optional. Post ID or 'partial:filename' for partials. Defaults to current post.
 * @param array|null $context_data Optional. Array data to search in (useful for nested blocks). Defaults to null.
 * @return WP_Post_Type|null Post type object or null if not found
 *
 * Usage Examples:
 *
 * Basic Usage (Current Page):
 *   $post_type = yaml_cf_get_post_type('content_type', null);
 *   if ($post_type) {
 *     echo '<h2>' . esc_html($post_type->label) . '</h2>';
 *     echo '<p>Slug: ' . esc_html($post_type->name) . '</p>';
 *   }
 *
 * Block Fields (with context):
 *   $blocks = yaml_cf_get_field('content_blocks', null);
 *   foreach ($blocks as $block) {
 *     $post_type = yaml_cf_get_post_type('type', null, $block);
 *     if ($post_type) {
 *       echo '<span class="type">' . esc_html($post_type->label) . '</span>';
 *     }
 *   }
 *
 * Partial Template:
 *   $post_type = yaml_cf_get_post_type('archive_type', 'partial:header.php');
 */
function yaml_cf_get_post_type($field_name, $post_id = null, $context_data = null) {
  $post_type_slug = yaml_cf_get_field($field_name, $post_id, $context_data);

  if (!$post_type_slug || !is_string($post_type_slug)) {
    return null;
  }

  $post_type_object = get_post_type_object($post_type_slug);
  return $post_type_object ? $post_type_object : null;
}

if (!function_exists('ycf_get_post_type')) {
  /**
   * Alias for yaml_cf_get_post_type()
   *
   * @deprecated Use yaml_cf_get_post_type() instead
   */
  function ycf_get_post_type($field_name, $post_id = null, $context_data = null) {
    return yaml_cf_get_post_type($field_name, $post_id, $context_data);
  }
}

/**
 * Get data object entry for a data_object field
 * Returns array with entry data
 *
 * @param string $field_name The name of the data_object field
 * @param int|string|null $post_id Optional. Post ID or 'partial:filename' for partials. Defaults to current post.
 * @param array|null $context_data Optional. Array data to search in (useful for nested blocks). Defaults to null.
 * @return array|null Array with entry data or null if not found
 *
 * Usage Examples:
 *
 * Basic Usage (Current Page):
 *   $university = yaml_cf_get_data_object('university', null);
 *   if ($university) {
 *     echo '<h2>' . esc_html($university['name']) . '</h2>';
 *     echo '<p>' . esc_html($university['description']) . '</p>';
 *     $logo = $university['logo']; // Image ID
 *   }
 *
 * Block Fields (with context):
 *   $blocks = yaml_cf_get_field('event_blocks', null);
 *   foreach ($blocks as $block) {
 *     $university = yaml_cf_get_data_object('university', null, $block);
 *     if ($university) {
 *       echo '<p>Host: ' . esc_html($university['name']) . '</p>';
 *     }
 *   }
 *
 * Partial Template:
 *   $featured_company = yaml_cf_get_data_object('company', 'partial:header.php');
 */
function yaml_cf_get_data_object($field_name, $post_id = null, $context_data = null) {
  $entry_id = yaml_cf_get_field($field_name, $post_id, $context_data);

  if (!$entry_id || !is_string($entry_id)) {
    return null;
  }

  // We need to know which object type this entry belongs to
  // The field should have stored just the entry_id, but we need to search all types
  // This is not ideal - better to store object_type:entry_id or have a way to know the type

  // For now, search all object types for this entry_id
  $data_object_types = get_option('yaml_cf_data_object_types', []);

  foreach ($data_object_types as $type_slug => $type_data) {
    $entries = get_option('yaml_cf_data_object_entries_' . $type_slug, []);
    if (isset($entries[$entry_id])) {
      return $entries[$entry_id];
    }
  }

  return null;
}

if (!function_exists('ycf_get_data_object')) {
  /**
   * Alias for yaml_cf_get_data_object()
   *
   * @deprecated Use yaml_cf_get_data_object() instead
   */
  function ycf_get_data_object($field_name, $post_id = null, $context_data = null) {
    return yaml_cf_get_data_object($field_name, $post_id, $context_data);
  }
}

/**
 * Get all data object entries of a specific type
 *
 * @param string $object_type The slug of the data object type (e.g., 'universities')
 * @return array Array of entries with entry_id as keys and entry data as values
 *
 * Usage Examples:
 *
 * Get all universities:
 *   $universities = yaml_cf_get_data_objects('universities');
 *   foreach ($universities as $entry_id => $university) {
 *     echo '<h3>' . esc_html($university['name']) . '</h3>';
 *   }
 *
 * Filter and display:
 *   $companies = yaml_cf_get_data_objects('companies');
 *   if (!empty($companies)) {
 *     foreach ($companies as $id => $company) {
 *       echo '<div class="company">';
 *       echo '<h4>' . esc_html($company['name']) . '</h4>';
 *       echo '</div>';
 *     }
 *   }
 */
function yaml_cf_get_data_objects($object_type) {
  if (empty($object_type) || !is_string($object_type)) {
    return [];
  }

  $entries = get_option('yaml_cf_data_object_entries_' . $object_type, []);
  return is_array($entries) ? $entries : [];
}

if (!function_exists('ycf_get_data_objects')) {
  /**
   * Alias for yaml_cf_get_data_objects()
   *
   * @deprecated Use yaml_cf_get_data_objects() instead
   */
  function ycf_get_data_objects($object_type) {
    return yaml_cf_get_data_objects($object_type);
  }
}

// Uninstall cleanup lives in uninstall.php (WordPress runs it when the plugin is deleted)
