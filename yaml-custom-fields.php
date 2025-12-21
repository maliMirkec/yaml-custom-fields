<?php
/**
 * Plugin Name: YAML Custom Fields
 * Plugin URI: https://github.com/maliMirkec/yaml-custom-fields
 * Description: A WordPress plugin for managing YAML frontmatter schemas in theme templates
 * Version: 1.2.1
 * Author: Silvestar Bistrović
 * Author URI: https://www.silvestar.codes
 * Author Email: me@silvestar.codes
 * License: GPL v2 or later
 * Text Domain: yaml-custom-fields
 */

if (!defined('ABSPATH')) {
  exit;
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

  private function init_hooks() {
    add_action('admin_init', [$this, 'handle_form_submissions']);
    add_action('admin_init', [$this, 'handle_single_post_export']);
    add_action('admin_init', [$this, 'handle_settings_export']);
    add_action('admin_init', [$this, 'handle_page_data_export']);
    add_action('admin_init', [$this, 'handle_data_objects_export']);
    add_action('admin_init', [$this, 'handle_data_object_type_submissions']);

    // NOTE: Admin menu, assets, and menu customization now handled by new architecture (HookManager)
    // add_action('admin_menu', [$this, 'add_admin_menu']);
    // add_action('admin_head', [$this, 'hide_submenu_items']);
    // add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    // add_filter('admin_title', [$this, 'customize_admin_title'], 10, 2);
    // add_filter('parent_file', [$this, 'set_parent_file']);
    // add_filter('submenu_file', [$this, 'set_submenu_file']);
    // add_action('switch_theme', [$this, 'clear_template_cache']);

    // Only use edit_form_after_title to avoid duplicate rendering
    add_action('edit_form_after_title', [$this, 'render_schema_meta_box_after_title']);
    add_action('save_post', [$this, 'save_schema_data']);
    add_action('delete_post', [$this, 'handle_post_deletion']);
    add_action('wp_ajax_yaml_cf_save_template_settings', [$this, 'ajax_save_template_settings']);
    add_action('wp_ajax_yaml_cf_toggle_use_global', [$this, 'ajax_toggle_use_global']);
    add_action('wp_ajax_yaml_cf_save_schema', [$this, 'ajax_save_schema']);
    add_action('wp_ajax_yaml_cf_get_schema', [$this, 'ajax_get_schema']);
    add_action('wp_ajax_yaml_cf_get_partial_data', [$this, 'ajax_get_partial_data']);
    add_action('wp_ajax_yaml_cf_save_partial_data', [$this, 'ajax_save_partial_data']);
    add_action('wp_ajax_yaml_cf_refresh_templates', [$this, 'ajax_refresh_templates']);
    add_action('wp_ajax_yaml_cf_export_settings', [$this, 'ajax_export_settings']);
    add_action('wp_ajax_yaml_cf_import_settings', [$this, 'ajax_import_settings']);
    add_action('wp_ajax_yaml_cf_export_page_data', [$this, 'ajax_export_page_data']);
    add_action('wp_ajax_yaml_cf_import_page_data', [$this, 'ajax_import_page_data']);
    add_action('wp_ajax_yaml_cf_get_posts_with_data', [$this, 'ajax_get_posts_with_data']);
    add_action('wp_ajax_yaml_cf_import_data_objects', [$this, 'ajax_import_data_objects']);
  }



  public function handle_single_post_export() {
    $post_id = $this->get_param_int('yaml_cf_export_post', 0);
    $nonce = $this->get_param('_wpnonce');

    if (!$post_id || !$nonce) {
      return;
    }

    if (!wp_verify_nonce($nonce, 'yaml_cf_export_post_' . $post_id)) {
      wp_die(esc_html__('Security check failed', 'yaml-custom-fields'));
    }

    if (!current_user_can('edit_post', $post_id)) {
      wp_die(esc_html__('Permission denied', 'yaml-custom-fields'));
    }

    $post = get_post($post_id);
    if (!$post) {
      wp_die(esc_html__('Post not found', 'yaml-custom-fields'));
    }

    $template = $this->get_template_for_post($post);

    $data = get_post_meta($post_id, '_yaml_cf_data', true);
    if (empty($data)) {
      wp_die(esc_html__('No custom field data found for this post', 'yaml-custom-fields'));
    }

    // Get schema if available
    $schema = get_post_meta($post_id, '_yaml_cf_schema', true);

    $post_data = [
      'id' => $post->ID,
      'title' => $post->post_title,
      'slug' => $post->post_name,
      'type' => $post->post_type,
      'template' => $template,
      'data' => $data
    ];

    // Include schema if available
    if (!empty($schema)) {
      $post_data['schema'] = $schema;
    }

    $export_data = [
      'plugin' => 'yaml-custom-fields',
      'version' => YAML_CF_VERSION,
      'exported_at' => current_time('mysql'),
      'site_url' => get_site_url(),
      'type' => 'single-post',
      'post' => $post_data
    ];

    // Set headers for file download
    $filename = 'yaml-cs-content-' . sanitize_file_name($post->post_name) . '-' . gmdate('Y-m-d-H-i-s') . '.json';
    nocache_headers();
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Type: application/json; charset=utf-8');

    // Use WordPress JSON output function
    echo wp_json_encode($export_data, JSON_PRETTY_PRINT);
    exit;
  }

  public function handle_settings_export() {
    $export_settings = $this->get_param('yaml_cf_export_settings');
    $nonce = $this->get_param('_wpnonce');

    if (!$export_settings || !$nonce) {
      return;
    }

    if (!wp_verify_nonce($nonce, 'yaml_cf_export_settings')) {
      wp_die(esc_html__('Security check failed', 'yaml-custom-fields'));
    }

    if (!current_user_can('manage_options')) {
      wp_die(esc_html__('Permission denied', 'yaml-custom-fields'));
    }

    // Gather all settings
    $export_data = [
      'plugin' => 'yaml-custom-fields',
      'version' => YAML_CF_VERSION,
      'exported_at' => current_time('mysql'),
      'site_url' => get_site_url(),
      'settings' => [
        'template_settings' => get_option('yaml_cf_template_settings', []),
        'schemas' => get_option('yaml_cf_schemas', []),
        'partial_data' => get_option('yaml_cf_partial_data', []),
        'template_global_schemas' => get_option('yaml_cf_template_global_schemas', []),
        'template_global_data' => get_option('yaml_cf_template_global_data', []),
        'global_schema' => get_option('yaml_cf_global_schema', ''),
        'global_data' => get_option('yaml_cf_global_data', [])
      ]
    ];

    // Set headers for file download
    $filename = 'yaml-cs-schema-' . gmdate('Y-m-d-H-i-s') . '.json';
    nocache_headers();
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Type: application/json; charset=utf-8');

    // Use WordPress JSON output function
    echo wp_json_encode($export_data, JSON_PRETTY_PRINT);
    exit;
  }

  public function handle_page_data_export() {
    if (!isset($_POST['yaml_cf_export_page_data_nonce'])) {
      return;
    }

    if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['yaml_cf_export_page_data_nonce'])), 'yaml_cf_export_page_data')) {
      wp_die(esc_html__('Security check failed', 'yaml-custom-fields'));
    }

    if (!current_user_can('manage_options')) {
      wp_die(esc_html__('Permission denied', 'yaml-custom-fields'));
    }

    $post_ids = isset($_POST['post_ids']) && is_array($_POST['post_ids']) ? array_map('intval', wp_unslash($_POST['post_ids'])) : [];
    $match_by = isset($_POST['match_by']) ? sanitize_text_field(wp_unslash($_POST['match_by'])) : 'slug';

    if (empty($post_ids)) {
      wp_die(esc_html__('No posts selected', 'yaml-custom-fields'));
    }

    $export_data = [
      'plugin' => 'yaml-custom-fields',
      'version' => YAML_CF_VERSION,
      'exported_at' => current_time('mysql'),
      'site_url' => get_site_url(),
      'match_by' => $match_by,
      'posts' => []
    ];

    foreach ($post_ids as $post_id) {
      $post = get_post($post_id);
      if (!$post) {
        continue;
      }

      $template = $this->get_template_for_post($post);

      $data = get_post_meta($post_id, '_yaml_cf_data', true);
      if (empty($data)) {
        continue;
      }

      // Get schema if available
      $schema = get_post_meta($post_id, '_yaml_cf_schema', true);

      $post_data = [
        'id' => $post->ID,
        'title' => $post->post_title,
        'slug' => $post->post_name,
        'type' => $post->post_type,
        'template' => $template,
        'data' => $data
      ];

      // Include schema if available
      if (!empty($schema)) {
        $post_data['schema'] = $schema;
      }

      $export_data['posts'][] = $post_data;
    }

    // Set headers for file download
    $filename = 'yaml-cs-partials-' . gmdate('Y-m-d-H-i-s') . '.json';
    nocache_headers();
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Type: application/json; charset=utf-8');

    // Use WordPress JSON output function
    echo wp_json_encode($export_data, JSON_PRETTY_PRINT);
    exit;
  }

  public function handle_data_objects_export() {
    if (!isset($_POST['yaml_cf_export_data_objects_nonce'])) {
      return;
    }

    if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['yaml_cf_export_data_objects_nonce'])), 'yaml_cf_export_data_objects')) {
      wp_die(esc_html__('Security check failed', 'yaml-custom-fields'));
    }

    if (!current_user_can('manage_options')) {
      wp_die(esc_html__('Permission denied', 'yaml-custom-fields'));
    }

    $this->export_data_objects();
    exit;
  }

  public function handle_data_object_type_submissions() {
    $plugin = \YamlCF\Core\Plugin::getInstance();
    $controller = $plugin->get('data_object_controller');
    $controller->handleFormSubmissions();
  }

  public function handle_form_submissions() {
    // Handle single post import
    if (isset($_POST['yaml_cf_import_post_nonce']) &&
        wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['yaml_cf_import_post_nonce'])), 'yaml_cf_import_post')) {

      $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

      if (!current_user_can('edit_post', $post_id)) {
        wp_die(esc_html__('Permission denied', 'yaml-custom-fields'));
      }

      // Load WordPress file handling functions
      if (!function_exists('wp_handle_upload')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
      }

      // Validate that file was uploaded
      if (!isset($_FILES['yaml_cf_import_file']) || empty($_FILES['yaml_cf_import_file']['name'])) {
        set_transient('yaml_cf_import_error_' . get_current_user_id() . '_' . $post_id, 'no_file', 60);
        wp_safe_redirect(add_query_arg([
          'post' => $post_id,
          'action' => 'edit'
        ], admin_url('post.php')));
        exit;
      }

      // Configure upload handling for JSON files
      $upload_overrides = [
        'test_form' => false,
        'mimes' => ['json' => 'application/json'],
      ];

      // Add filter to allow JSON mime type
      add_filter('upload_mimes', function($mimes) {
        $mimes['json'] = 'application/json';
        return $mimes;
      });

      // Also bypass the file type check entirely for this specific upload
      add_filter('wp_check_filetype_and_ext', function($data, $file, $filename, $mimes) {
        if (substr($filename, -5) === '.json') {
          $data['ext'] = 'json';
          $data['type'] = 'application/json';
        }
        return $data;
      }, 10, 4);

      // Use WordPress native upload handler - validates and sanitizes automatically
      $uploaded_file = wp_handle_upload($_FILES['yaml_cf_import_file'], $upload_overrides);

      // Check for upload errors (wp_handle_upload validates all upload conditions)
      if (!$uploaded_file || isset($uploaded_file['error'])) {
        set_transient('yaml_cf_import_error_' . get_current_user_id() . '_' . $post_id, 'upload_failed', 60);
        wp_safe_redirect(add_query_arg([
          'post' => $post_id,
          'action' => 'edit'
        ], admin_url('post.php')));
        exit;
      }

      // Validate file extension
      $file_type = wp_check_filetype($uploaded_file['file']);
      if ($file_type['ext'] !== 'json') {
        wp_delete_file($uploaded_file['file']);
        set_transient('yaml_cf_import_error_' . get_current_user_id() . '_' . $post_id, 'invalid_file', 60);
        wp_safe_redirect(add_query_arg([
          'post' => $post_id,
          'action' => 'edit'
        ], admin_url('post.php')));
        exit;
      }

      // Read uploaded file content using WordPress filesystem API
      $json_data = file_get_contents($uploaded_file['file']);

      // Clean up - delete the uploaded file after reading
      wp_delete_file($uploaded_file['file']);

      $import_data = json_decode($json_data, true);

      if (!$import_data || !isset($import_data['plugin']) || $import_data['plugin'] !== 'yaml-custom-fields') {
        set_transient('yaml_cf_import_error_' . get_current_user_id() . '_' . $post_id, 'invalid_format', 60);
        wp_safe_redirect(add_query_arg([
          'post' => $post_id,
          'action' => 'edit'
        ], admin_url('post.php')));
        exit;
      }

      // Handle both single-post and multi-post export formats
      $post_data = null;

      if (isset($import_data['type']) && $import_data['type'] === 'single-post' && isset($import_data['post'])) {
        // Single-post export format
        $post_data = $import_data['post'];
      } elseif (isset($import_data['posts']) && is_array($import_data['posts']) && count($import_data['posts']) > 0) {
        // Multi-post export format - find matching post by ID or slug
        $current_post = get_post($post_id);

        foreach ($import_data['posts'] as $candidate) {
          if (isset($candidate['id']) && $candidate['id'] == $post_id) {
            $post_data = $candidate;
            break;
          } elseif (isset($candidate['slug']) && $candidate['slug'] === $current_post->post_name) {
            $post_data = $candidate;
            break;
          }
        }

        // If no match found, use the first post
        if (!$post_data) {
          $post_data = $import_data['posts'][0];
        }
      }

      if (!$post_data || !isset($post_data['data'])) {
        set_transient('yaml_cf_import_error_' . get_current_user_id() . '_' . $post_id, 'no_data', 60);
        wp_safe_redirect(add_query_arg([
          'post' => $post_id,
          'action' => 'edit'
        ], admin_url('post.php')));
        exit;
      }

      // Validate and clean attachment data
      $schema = isset($post_data['schema']) ? $post_data['schema'] : null;
      $cleaned_data = $this->validate_and_clean_attachment_data($post_data['data'], $schema);

      // Update post meta using WordPress API (handles serialization and caching)
      update_post_meta($post_id, '_yaml_cf_data', $cleaned_data);

      // Mark as imported and store schema if available
      update_post_meta($post_id, '_yaml_cf_imported', true);
      if (isset($post_data['schema'])) {
        update_post_meta($post_id, '_yaml_cf_schema', $post_data['schema']);
      }

      // Clear caches
      $this->clear_data_caches($post_id);

      // Set transient for success message (shows only once)
      set_transient('yaml_cf_import_success_' . get_current_user_id() . '_' . $post_id, true, 60);

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
        wp_die(esc_html__('Permission denied', 'yaml-custom-fields'));
      }

      $template = isset($_POST['template']) ? sanitize_text_field(wp_unslash($_POST['template'])) : '';
      $schema = isset($_POST['schema']) ? sanitize_textarea_field(wp_unslash($_POST['schema'])) : '';

      // Validate YAML syntax before saving
      if (!empty($schema)) {
        $validation_result = $this->validate_yaml_schema($schema, $template);
        if (!$validation_result['valid']) {
          // Store the invalid schema in a transient so we can display it back
          set_transient('yaml_cf_invalid_schema_' . get_current_user_id(), $schema, 60);

          // Redirect back with error message
          wp_safe_redirect(add_query_arg([
            'page' => 'yaml-cf-edit-schema',
            'template' => urlencode($template),
            'error' => '1',
            'error_msg' => urlencode($validation_result['message'])
          ], admin_url('admin.php')));
          exit;
        }
      }

      $schemas = get_option('yaml_cf_schemas', []);
      $schemas[$template] = $schema;
      update_option('yaml_cf_schemas', $schemas);

      // Clear any stored invalid schema
      delete_transient('yaml_cf_invalid_schema_' . get_current_user_id());

      // Redirect with success message
      wp_safe_redirect(add_query_arg([
        'page' => 'yaml-cf-edit-schema',
        'template' => urlencode($template),
        'saved' => '1'
      ], admin_url('admin.php')));
      exit;
    }

    // Handle partial data save
    if (isset($_POST['yaml_cf_partial_nonce']) &&
        wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['yaml_cf_partial_nonce'])), 'yaml_cf_save_partial')) {

      if (!current_user_can('manage_options')) {
        wp_die(esc_html__('Permission denied', 'yaml-custom-fields'));
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

      // Redirect with success message
      wp_safe_redirect(add_query_arg([
        'page' => 'yaml-cf-edit-partial',
        'template' => urlencode($template),
        'saved' => '1'
      ], admin_url('admin.php')));
      exit;
    }

    // Handle global schema save
    if (isset($_POST['yaml_cf_save_global_schema_nonce']) &&
        wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['yaml_cf_save_global_schema_nonce'])), 'yaml_cf_save_global_schema')) {

      if (!current_user_can('manage_options')) {
        wp_die(esc_html__('Permission denied', 'yaml-custom-fields'));
      }

      $global_schema = isset($_POST['global_schema']) ? sanitize_textarea_field(wp_unslash($_POST['global_schema'])) : '';

      // Validate YAML syntax before saving
      if (!empty($global_schema)) {
        $validation_result = $this->validate_yaml_schema($global_schema);
        if (!$validation_result['valid']) {
          // Store the invalid schema in a transient so we can display it back
          set_transient('yaml_cf_invalid_global_schema_' . get_current_user_id(), $global_schema, 60);

          // Redirect back with error message
          wp_safe_redirect(add_query_arg([
            'page' => 'yaml-cf-edit-global-schema',
            'error' => '1',
            'error_msg' => urlencode($validation_result['message'])
          ], admin_url('admin.php')));
          exit;
        }
      }

      update_option('yaml_cf_global_schema', $global_schema);

      // Clear any stored invalid schema
      delete_transient('yaml_cf_invalid_global_schema_' . get_current_user_id());

      // Redirect with success message
      wp_safe_redirect(add_query_arg([
        'page' => 'yaml-cf-edit-global-schema',
        'saved' => '1'
      ], admin_url('admin.php')));
      exit;
    }

    // Handle global data save
    if (isset($_POST['yaml_cf_global_data_nonce']) &&
        wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['yaml_cf_global_data_nonce'])), 'yaml_cf_save_global_data')) {

      if (!current_user_can('manage_options')) {
        wp_die(esc_html__('Permission denied', 'yaml-custom-fields'));
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

      // Redirect with success message
      wp_safe_redirect(add_query_arg([
        'page' => 'yaml-cf-manage-global-data',
        'saved' => '1'
      ], admin_url('admin.php')));
      exit;
    }

    // Handle template global schema save
    if (isset($_POST['yaml_cf_save_template_global_schema_nonce']) &&
        wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['yaml_cf_save_template_global_schema_nonce'])), 'yaml_cf_save_template_global_schema')) {

      if (!current_user_can('manage_options')) {
        wp_die(esc_html__('Permission denied', 'yaml-custom-fields'));
      }

      $template = isset($_POST['template']) ? sanitize_text_field(wp_unslash($_POST['template'])) : '';
      $template_global_schema = isset($_POST['template_global_schema']) ? sanitize_textarea_field(wp_unslash($_POST['template_global_schema'])) : '';

      // Validate YAML syntax before saving
      if (!empty($template_global_schema)) {
        $validation_result = $this->validate_yaml_schema($template_global_schema, $template);
        if (!$validation_result['valid']) {
          // Store the invalid schema in a transient so we can display it back
          set_transient('yaml_cf_invalid_template_global_schema_' . get_current_user_id(), $template_global_schema, 60);

          // Redirect back with error message
          wp_safe_redirect(add_query_arg([
            'page' => 'yaml-cf-edit-template-global',
            'template' => urlencode($template),
            'error' => '1',
            'error_msg' => urlencode($validation_result['message'])
          ], admin_url('admin.php')));
          exit;
        }
      }

      $template_global_schemas = get_option('yaml_cf_template_global_schemas', []);
      $template_global_schemas[$template] = $template_global_schema;
      update_option('yaml_cf_template_global_schemas', $template_global_schemas);

      // Clear any stored invalid schema
      delete_transient('yaml_cf_invalid_template_global_schema_' . get_current_user_id());

      // Redirect with success message
      wp_safe_redirect(add_query_arg([
        'page' => 'yaml-cf-edit-template-global',
        'template' => urlencode($template),
        'saved' => '1'
      ], admin_url('admin.php')));
      exit;
    }

    // Handle template global data save
    if (isset($_POST['yaml_cf_save_template_global_data_nonce']) &&
        wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['yaml_cf_save_template_global_data_nonce'])), 'yaml_cf_save_template_global_data')) {

      if (!current_user_can('manage_options')) {
        wp_die(esc_html__('Permission denied', 'yaml-custom-fields'));
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

      // Redirect with success message
      wp_safe_redirect(add_query_arg([
        'page' => 'yaml-cf-manage-template-global',
        'template' => urlencode($template),
        'saved' => '1'
      ], admin_url('admin.php')));
      exit;
    }
  }

  public function sanitize_field_data($data, $schema = null, $field_name = '') {
    if (is_array($data)) {
      $sanitized = [];

      // Check if this field is a block or object type in the schema
      $field_def = $this->get_field_definition($schema, $field_name);
      $is_block_list = $field_def && isset($field_def['type']) && $field_def['type'] === 'block';
      $is_object = $field_def && isset($field_def['type']) && $field_def['type'] === 'object';
      $is_taxonomy = $field_def && isset($field_def['type']) && $field_def['type'] === 'taxonomy';
      $is_multiple = $field_def && isset($field_def['multiple']) && $field_def['multiple'];

      foreach ($data as $key => $value) {
        $child_schema = $schema;

        // If this is an object field, use its nested fields as the schema
        if ($is_object && isset($field_def['fields']) && is_array($field_def['fields'])) {
          $child_schema = ['fields' => $field_def['fields']];
        }
        // If this is a block list, find the appropriate block schema
        elseif ($is_block_list && is_array($value)) {
          $block_key = isset($field_def['blockKey']) ? $field_def['blockKey'] : 'type';
          $block_type = isset($value[$block_key]) ? $value[$block_key] : '';

          if ($block_type && isset($field_def['blocks']) && is_array($field_def['blocks'])) {
            foreach ($field_def['blocks'] as $block) {
              if (isset($block['name']) && $block['name'] === $block_type) {
                // Use the block's field definitions as the schema for child elements
                $child_schema = ['fields' => isset($block['fields']) ? $block['fields'] : []];
                break;
              }
            }
          }
        }

        $sanitized[sanitize_text_field($key)] = $this->sanitize_field_data($value, $child_schema, $key);
      }

      // For taxonomy and data_object fields with multiple=true, filter out empty strings
      // (These come from the hidden field used to ensure the field is always submitted)
      $is_data_object = $field_def && isset($field_def['type']) && $field_def['type'] === 'data_object';
      if (($is_taxonomy || $is_data_object) && $is_multiple) {
        $sanitized = array_filter($sanitized, function($value) {
          return $value !== '';
        });
        // Re-index array to avoid gaps in numeric keys
        $sanitized = array_values($sanitized);
      }

      return $sanitized;
    } elseif (is_string($data)) {
      // Check if this is a code field
      if ($schema && $field_name && $this->is_code_field($schema, $field_name)) {
        return $this->sanitize_code_field($data, $schema, $field_name);
      }
      // Check if this is a rich-text field - preserve safe HTML
      if ($schema && $field_name && $this->is_rich_text_field($schema, $field_name)) {
        return wp_kses_post($data);
      }
      // Use sanitize_textarea_field to preserve newlines and structure
      return sanitize_textarea_field($data);
    }
    return $data;
  }

  private function get_field_definition($schema, $field_name) {
    if (!$schema || !$field_name || !isset($schema['fields']) || !is_array($schema['fields'])) {
      return null;
    }

    foreach ($schema['fields'] as $field) {
      if (isset($field['name']) && $field['name'] === $field_name) {
        return $field;
      }
    }

    return null;
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

  private function is_rich_text_field($schema, $field_name) {
    if (!isset($schema['fields']) || !is_array($schema['fields'])) {
      return false;
    }

    foreach ($schema['fields'] as $field) {
      if (isset($field['name']) && $field['name'] === $field_name && isset($field['type']) && $field['type'] === 'rich-text') {
        return true;
      }
      // Check nested fields in objects
      if (isset($field['fields']) && is_array($field['fields'])) {
        if ($this->is_rich_text_field(['fields' => $field['fields']], $field_name)) {
          return true;
        }
      }
      // Check blocks
      if (isset($field['blocks']) && is_array($field['blocks'])) {
        foreach ($field['blocks'] as $block) {
          if (isset($block['fields']) && is_array($block['fields'])) {
            if ($this->is_rich_text_field(['fields' => $block['fields']], $field_name)) {
              return true;
            }
          }
        }
      }
    }

    return false;
  }

  private function get_code_field_language($schema, $field_name) {
    if (!isset($schema['fields']) || !is_array($schema['fields'])) {
      return 'html';
    }

    foreach ($schema['fields'] as $field) {
      if (isset($field['name']) && $field['name'] === $field_name && isset($field['type']) && $field['type'] === 'code') {
        return isset($field['options']['language']) ? $field['options']['language'] : 'html';
      }
      // Check nested fields in objects
      if (isset($field['fields']) && is_array($field['fields'])) {
        $lang = $this->get_code_field_language(['fields' => $field['fields']], $field_name);
        if ($lang !== 'html' || $this->is_code_field(['fields' => $field['fields']], $field_name)) {
          return $lang;
        }
      }
      // Check blocks
      if (isset($field['blocks']) && is_array($field['blocks'])) {
        foreach ($field['blocks'] as $block) {
          if (isset($block['fields']) && is_array($block['fields'])) {
            $lang = $this->get_code_field_language(['fields' => $block['fields']], $field_name);
            if ($lang !== 'html' || $this->is_code_field(['fields' => $block['fields']], $field_name)) {
              return $lang;
            }
          }
        }
      }
    }

    return 'html';
  }

  private function sanitize_code_field($code, $schema, $field_name) {
    $language = $this->get_code_field_language($schema, $field_name);

    // For users with unfiltered_html capability (administrators), allow raw code
    if (current_user_can('unfiltered_html')) {
      switch (strtolower($language)) {
        case 'css':
          // Still sanitize CSS to remove dangerous patterns even for admins
          return $this->sanitize_css_code($code);

        case 'javascript':
        case 'js':
        case 'html':
        default:
          // For administrators, preserve code exactly as entered
          return $code;
      }
    }

    // For non-administrators, be more restrictive
    switch (strtolower($language)) {
      case 'css':
        return $this->sanitize_css_code($code);

      case 'javascript':
      case 'js':
        // Strip all tags for non-admins
        return wp_strip_all_tags($code);

      case 'html':
      default:
        // For HTML, use wp_kses_post which allows safe HTML tags
        return wp_kses_post($code);
    }
  }

  private function sanitize_css_code($css) {
    // Remove potentially dangerous CSS
    $dangerous_patterns = [
      '/expression\s*\(/i',           // IE CSS expressions
      '/javascript\s*:/i',            // JavaScript protocol
      '/vbscript\s*:/i',              // VBScript protocol
      '/@import\s+/i',                // Prevent external CSS imports
      '/behavior\s*:/i',              // IE behaviors
      '/\-moz\-binding\s*:/i',        // Firefox XBL bindings
      '/data\s*:\s*text\/html/i',    // Data URI with HTML
    ];

    $cleaned_css = $css;
    foreach ($dangerous_patterns as $pattern) {
      $cleaned_css = preg_replace($pattern, '', $cleaned_css);
    }

    // Basic sanitization while preserving newlines
    return sanitize_textarea_field($cleaned_css);
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
      $cacheManager = $plugin->get('cache_manager');
      $cacheManager->clearTemplateCache();
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

  public function ajax_save_template_settings() {
    check_ajax_referer('yaml_cf_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
      wp_send_json_error('Permission denied');
    }

    $template = isset($_POST['template']) ? sanitize_text_field(wp_unslash($_POST['template'])) : '';
    $enabled = isset($_POST['enabled']) && sanitize_text_field(wp_unslash($_POST['enabled'])) === 'true';

    $settings = get_option('yaml_cf_template_settings', []);
    $settings[$template] = $enabled;

    update_option('yaml_cf_template_settings', $settings);

    // Check if schema exists for this template
    $schemas = get_option('yaml_cf_schemas', []);
    $has_schema = isset($schemas[$template]) && !empty($schemas[$template]);

    // Check if template global schema exists for this template
    $template_global_schemas = get_option('yaml_cf_template_global_schemas', []);
    $has_template_global_schema = isset($template_global_schemas[$template]) && !empty($template_global_schemas[$template]);

    wp_send_json_success([
      'has_schema' => $has_schema,
      'has_template_global_schema' => $has_template_global_schema
    ]);
  }

  public function ajax_refresh_templates() {
    try {
      check_ajax_referer('yaml_cf_nonce', 'nonce');

      if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
        return;
      }

      // Get services from plugin instance
      $plugin = \YamlCF\Core\Plugin::getInstance();
      $templateCache = $plugin->get('template_cache');
      $notificationManager = $plugin->get('notification_manager');

      // Clear the template cache
      $templateCache->clear();

      // Set success notification
      $notificationManager->success(esc_html__('Template list refreshed successfully!', 'yaml-custom-fields'));

      wp_send_json_success();
    } catch (\Exception $e) {
      wp_send_json_error($e->getMessage());
    }
  }

  public function ajax_toggle_use_global() {
    check_ajax_referer('yaml_cf_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
      wp_send_json_error('Permission denied');
    }

    $template = isset($_POST['template']) ? sanitize_text_field(wp_unslash($_POST['template'])) : '';
    $use_global = isset($_POST['use_global']) && sanitize_text_field(wp_unslash($_POST['use_global'])) === 'true';

    $settings = get_option('yaml_cf_template_settings', []);
    $settings[$template . '_use_global'] = $use_global;

    update_option('yaml_cf_template_settings', $settings);

    wp_send_json_success([
      'use_global' => $use_global
    ]);
  }

  public function ajax_save_schema() {
    check_ajax_referer('yaml_cf_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
      wp_send_json_error('Permission denied');
    }

    $template = isset($_POST['template']) ? sanitize_text_field(wp_unslash($_POST['template'])) : '';
    $schema = isset($_POST['schema']) ? sanitize_textarea_field(wp_unslash($_POST['schema'])) : '';

    $schemas = get_option('yaml_cf_schemas', []);
    $schemas[$template] = $schema;

    update_option('yaml_cf_schemas', $schemas);

    wp_send_json_success();
  }

  public function ajax_get_schema() {
    check_ajax_referer('yaml_cf_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
      wp_send_json_error('Permission denied');
    }

    $template = isset($_POST['template']) ? sanitize_text_field(wp_unslash($_POST['template'])) : '';
    $schemas = get_option('yaml_cf_schemas', []);

    wp_send_json_success([
      'schema' => isset($schemas[$template]) ? $schemas[$template] : ''
    ]);
  }

  /**
   * Get the template file for a post based on its type and settings
   *
   * @param WP_Post $post The post object
   * @return string The template filename
   */
  public function get_template_for_post($post) {
    $post_type = $post->post_type;

    // For pages, check if a custom page template is assigned
    if ($post_type === 'page') {
      $template = get_post_meta($post->ID, '_wp_page_template', true);
      if (!empty($template) && $template !== 'default') {
        return $template;
      }
      return 'page.php';
    }

    // For posts and custom post types, use the single template
    // Check for specific template: single-{post_type}.php
    $specific_template = "single-{$post_type}.php";
    $theme_dir = get_template_directory();

    if (file_exists($theme_dir . '/' . $specific_template)) {
      return $specific_template;
    }

    // Fall back to generic single.php for regular posts
    if ($post_type === 'post') {
      return 'single.php';
    }

    // For custom post types without a specific template, return the expected name
    // This allows the user to create the template and schema
    return $specific_template;
  }

  public function render_schema_meta_box_after_title($post) {
    // Only render for public post types
    $post_type_object = get_post_type_object($post->post_type);
    if (!$post_type_object || !$post_type_object->public) {
      return;
    }

    wp_nonce_field('yaml_cf_meta_box', 'yaml_cf_meta_box_nonce');

    // Determine the template based on post type
    $template = $this->get_template_for_post($post);

    $template_settings = get_option('yaml_cf_template_settings', []);

    if (!isset($template_settings[$template]) || !$template_settings[$template]) {
      return;
    }

    $schemas = get_option('yaml_cf_schemas', []);

    if (!isset($schemas[$template]) || empty($schemas[$template])) {
      return;
    }

    $schema_yaml = $schemas[$template];
    $schema = $this->parse_yaml_schema($schema_yaml);

    if (!$schema || !isset($schema['fields'])) {
      return;
    }

    // Normalize shorthand info field syntax
    $schema['fields'] = $this->normalize_info_field_shorthand($schema['fields']);

    // Add link to edit schema
    $edit_schema_url = admin_url('admin.php?page=yaml-cf-edit-schema&template=' . urlencode($template));

    echo '<div id="yaml-cf-meta-box" class="postbox" style="margin-top: 20px; margin-bottom: 20px;">';
    echo '<div class="postbox-header"><h2 class="hndle">' . esc_html__('YAML Custom Fields Schema', 'yaml-custom-fields') . '</h2></div>';
    echo '<div class="inside">';

    // Display import/export messages (using transients - shown only once)
    $success_key = 'yaml_cf_import_success_' . get_current_user_id() . '_' . $post->ID;
    if (get_transient($success_key)) {
      echo '<div class="notice notice-success inline" style="margin: 10px 0;"><p>' . esc_html__('Data imported successfully!', 'yaml-custom-fields') . '</p></div>';
      delete_transient($success_key);
    }

    $error_key = 'yaml_cf_import_error_' . get_current_user_id() . '_' . $post->ID;
    $error_msg = get_transient($error_key);
    if ($error_msg) {
      $error_messages = [
        'no_file' => __('No file selected. Please choose a file to import.', 'yaml-custom-fields'),
        'upload_failed' => __('File upload failed. Please try again.', 'yaml-custom-fields'),
        'invalid_file' => __('Invalid file type. Please upload a JSON file.', 'yaml-custom-fields'),
        'invalid_format' => __('Invalid file format. Please upload a valid YAML CF export file.', 'yaml-custom-fields'),
        'no_data' => __('No data found in the import file.', 'yaml-custom-fields')
      ];
      $message = isset($error_messages[$error_msg]) ? $error_messages[$error_msg] : __('Import failed.', 'yaml-custom-fields');
      echo '<div class="notice notice-error inline" style="margin: 10px 0;"><p>' . esc_html($message) . '</p></div>';
      delete_transient($error_key);
    }

    $export_url = wp_nonce_url(
      add_query_arg('yaml_cf_export_post', $post->ID, admin_url('post.php')),
      'yaml_cf_export_post_' . $post->ID
    );

    echo '<div class="yaml-cf-meta-box-header" style="margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #ddd;">';
    echo '<p style="margin: 0;">';
    echo '<strong>' . esc_html__('Template:', 'yaml-custom-fields') . '</strong> ' . esc_html($template);
    echo ' | ';
    echo '<a href="' . esc_url($edit_schema_url) . '" target="_blank">' . esc_html__('Edit Schema', 'yaml-custom-fields') . '</a>';
    echo ' | ';

    // Export link (simple text link)
    echo '<a href="' . esc_url($export_url) . '">' . esc_html__('Export', 'yaml-custom-fields') . '</a>';
    echo ' | ';

    // Import link (triggers hidden file input, handled via separate form submission)
    echo '<span style="display: inline-block;">';
    echo '<input type="file" name="yaml_cf_import_file_hidden" accept=".json" id="yaml-cf-import-file-' . esc_attr($post->ID) . '" style="display: none;" data-post-id="' . esc_attr($post->ID) . '" data-nonce="' . esc_attr(wp_create_nonce('yaml_cf_import_post')) . '">';
    echo '<label for="yaml-cf-import-file-' . esc_attr($post->ID) . '" style="cursor: pointer; color: #2271b1; text-decoration: underline;">';
    echo esc_html__('Import', 'yaml-custom-fields');
    echo '</label>';
    echo '</span>';
    echo ' | ';

    // Reset All Data (simple text link)
    echo '<a href="#" class="yaml-cf-reset-data" data-post-id="' . esc_attr($post->ID) . '" style="color: #d63638;">';
    echo esc_html__('Reset All Data', 'yaml-custom-fields');
    echo '</a>';

    echo '</p>';
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

    // Render each field with dual display if it exists in template global
    foreach ($schema['fields'] as $field) {
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

    echo '</div>';

    // Render template-global-only fields as readonly
    if ($has_template_global && $template_global_schema && isset($template_global_schema['fields']) && isset($schema['fields'])) {
      $manage_template_global_url = admin_url('admin.php?page=yaml-cf-manage-template-global&template=' . urlencode($template));

      $has_global_only_fields = false;

      foreach ($template_global_schema['fields'] as $global_field) {
        $global_field_name = $global_field['name'];

        // Check if this field exists ONLY in template global (NOT in local schema)
        if (!$this->field_exists_in_schema($global_field_name, $schema['fields'])) {
          // First global-only field: render section header
          if (!$has_global_only_fields) {
            $has_global_only_fields = true;
            echo '<div class="yaml-cf-template-global-only-fields" style="margin-top: 20px; padding-top: 20px; border-top: 2px solid #2271b1;">';
            echo '<div style="margin-bottom: 15px; padding: 10px; background: #f0f6fc; border-left: 4px solid #2271b1;">';
            echo '<h3 style="margin: 0 0 5px 0; font-size: 14px;">' . esc_html__('Template Global Fields', 'yaml-custom-fields') . '</h3>';
            echo '<p style="margin: 0; font-size: 13px; color: #646970;">';
            echo esc_html__('These fields are defined only in the template global schema and are shared across all posts/pages using this template.', 'yaml-custom-fields');
            echo ' <a href="' . esc_url($manage_template_global_url) . '" target="_blank">' . esc_html__('Edit Template Global Data', 'yaml-custom-fields') . '</a>';
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

          echo '<div class="yaml-cf-global-fields" style="margin-top: 20px; padding-top: 20px; border-top: 2px solid #2271b1;">';
          echo '<div style="margin-bottom: 15px; padding: 10px; background: #f0f6fc; border-left: 4px solid #2271b1;">';
          echo '<h3 style="margin: 0 0 5px 0; font-size: 14px;">' . esc_html__('Global Fields', 'yaml-custom-fields') . '</h3>';
          echo '<p style="margin: 0; font-size: 13px; color: #646970;">';
          echo esc_html__('These fields are shared across all posts/pages. Changes affect all content using global schema.', 'yaml-custom-fields');
          echo ' <a href="' . esc_url($manage_global_url) . '" target="_blank">' . esc_html__('Edit Global Data', 'yaml-custom-fields') . '</a>';
          echo '</p>';
          echo '</div>';

          $global_context = ['type' => 'global', 'readonly' => true];
          $this->render_schema_fields($global_schema['fields'], $global_data, '', $global_context);
          echo '</div>';
        }
      }
    }

    echo '</div>';
    echo '</div>';
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

    $manage_template_global_url = admin_url('admin.php?page=yaml-cf-manage-template-global&template=' . urlencode($template));

    echo '<div class="yaml-cf-dual-field" data-field-name="' . esc_attr($field_name) . '" style="margin-bottom: 25px; padding: 15px; background: #f9f9f9; border-radius: 4px;">';

    // Field label
    echo '<h4 style="margin: 0 0 15px 0; font-size: 14px;">' . esc_html($field['label'] ?? $field_name) . '</h4>';

    // Template global field (readonly, with edit link)
    echo '<div class="yaml-cf-template-global-part" style="margin-bottom: 15px; padding: 12px; background: #fff; border: 1px solid #ddd; border-radius: 3px;">';
    echo '<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">';
    echo '<strong style="font-size: 13px; color: #2271b1;">' . esc_html__('Template Global (All Pages)', 'yaml-custom-fields') . '</strong>';
    echo '<a href="' . esc_url($manage_template_global_url) . '" target="_blank" class="button button-small">' . esc_html__('Edit', 'yaml-custom-fields') . '</a>';
    echo '</div>';
    $readonly_context = ['type' => 'template_global', 'readonly' => true, 'id_suffix' => '_global'];
    $this->render_schema_fields([$field], [$field_name => $template_global_value], '', $readonly_context);
    echo '</div>';

    // Local field (always editable in HTML, visually blocked by JavaScript/CSS when checkbox is checked)
    echo '<div class="yaml-cf-local-part" style="margin-bottom: 15px; padding: 12px; background: #fff; border: 1px solid #ddd; border-radius: 3px;">';
    echo '<div style="margin-bottom: 8px;">';
    echo '<strong style="font-size: 13px; color: #046b99;">' . esc_html__('Page-Specific Value', 'yaml-custom-fields') . '</strong>';
    echo '</div>';
    $local_context = ['type' => 'page'];
    $this->render_schema_fields([$field], [$field_name => $local_value], '', $local_context);
    echo '</div>';

    // Checkbox to toggle between global and local
    echo '<div class="yaml-cf-field-toggle" style="padding: 10px; background: #fff; border: 1px solid #46b450; border-radius: 3px;">';
    echo '<label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">';
    echo '<input type="checkbox" name="yaml_cf_use_template_global_fields[' . esc_attr($field_name) . ']" value="1" class="yaml-cf-use-global-checkbox" ' . checked($use_global, true, false) . ' />';
    echo '<span style="font-weight: 500;">' . esc_html__('Use template global for this field', 'yaml-custom-fields') . '</span>';
    echo '</label>';
    echo '</div>';

    echo '</div>'; // yaml-cf-dual-field
  }

  /**
   * Normalize shorthand info field syntax to standard field structure
   * Transforms: - info: "text" → - type: info, name: info_0, text: "text"
   *
   * @param array $fields The fields array from parsed YAML
   * @return array Normalized fields array
   */
  private function normalize_info_field_shorthand($fields) {
    $normalized = [];
    $info_counter = 0;

    foreach ($fields as $field) {
      // Check if this is shorthand info syntax (single key 'info')
      if (isset($field['info']) && !isset($field['type']) && !isset($field['name'])) {
        // This is shorthand: - info: "text"
        $normalized[] = [
          'type' => 'info',
          'name' => 'info_' . $info_counter++,
          'text' => $field['info']
        ];
      } else {
        // Standard field structure, keep as-is
        $normalized[] = $field;
      }
    }

    return $normalized;
  }

  private function parse_yaml_schema($yaml) {
    try {
      return Yaml::parse($yaml);
    } catch (ParseException $e) {
      // Log error for debugging but fail gracefully
      self::log_debug('YAML parsing error - ' . $e->getMessage());
      return null;
    }
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

  private function validate_yaml_schema($yaml, $template = null) {
    try {
      $parsed = Yaml::parse($yaml);

      // Check if parsed successfully
      if ($parsed === null) {
        return [
          'valid' => false,
          'message' => 'Empty or invalid YAML content'
        ];
      }

      // Check for required 'fields' key
      if (!isset($parsed['fields']) || !is_array($parsed['fields'])) {
        return [
          'valid' => false,
          'message' => 'Schema must contain a "fields" array'
        ];
      }

      // Normalize info field shorthand for validation
      $fields = $this->normalize_info_field_shorthand($parsed['fields']);

      // Basic validation of field structure
      foreach ($fields as $index => $field) {
        if (!is_array($field)) {
          return [
            'valid' => false,
            'message' => 'Field at index ' . $index . ' is not a valid array'
          ];
        }

        if (!isset($field['name'])) {
          return [
            'valid' => false,
            'message' => 'Field at index ' . $index . ' is missing required "name" property'
          ];
        }

        if (!isset($field['type'])) {
          return [
            'valid' => false,
            'message' => 'Field "' . $field['name'] . '" is missing required "type" property'
          ];
        }

        // Validate info field template restrictions
        if ($field['type'] === 'info' && $template !== null) {
          if (!$this->is_template_allowed_for_info_field($template)) {
            return [
              'valid' => false,
              'message' => 'Info fields are not allowed for template partials and archives. Current template: ' . $template
            ];
          }
        }
      }

      return [
        'valid' => true,
        'message' => 'Schema is valid'
      ];
    } catch (ParseException $e) {
      return [
        'valid' => false,
        'message' => 'YAML syntax error: ' . $e->getMessage()
      ];
    }
  }

  /**
   * Check if a template is allowed to use info fields
   * Info fields are NOT allowed for partials and archives (global data templates)
   *
   * @param string $template The template name
   * @return bool True if allowed, false otherwise
   */
  private function is_template_allowed_for_info_field($template) {
    // Get just the basename if path is provided
    $basename = basename($template);

    // Partial/Archive patterns that should NOT have info fields
    // These match the partial_patterns from get_theme_templates()
    $disallowed_patterns = [
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

    // Check if template matches any disallowed pattern
    foreach ($disallowed_patterns as $pattern) {
      if ($pattern === $basename || fnmatch($pattern, $basename)) {
        return false; // This is a partial/archive, not allowed
      }
    }

    // All other templates are allowed (page.php, single.php, custom page templates, etc.)
    return true;
  }

  public function render_schema_fields($fields, $saved_data, $prefix = '', $context = null) {
    // Check if fields should be readonly
    $readonly = ($context && is_array($context) && isset($context['readonly']) && $context['readonly']);
    $disabled_attr = $readonly ? ' disabled="disabled"' : '';
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

      echo '<div class="yaml-cf-field' . esc_attr($readonly_class) . '" data-type="' . esc_attr($field['type']) . '">';

      // Generate code snippet (skip for block type fields as they have their own snippet)
      // Also skip for data_object context (manage entries page)
      $code_snippet = '';
      $popover_id = '';
      if ($context && is_array($context) && isset($context['type']) && $context['type'] !== 'data_object' && $field['type'] !== 'block') {
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

        $code_snippet = $function_name . "('" . esc_js($field['name']) . "'" . $post_id_param . $extra_params . ")";
        $popover_id = 'snippet-' . sanitize_html_class($field_id);
      }

      // Skip label rendering for block and info fields as they handle their own display
      if ($field['type'] !== 'block' && $field['type'] !== 'info') {
        if($field['type'] === 'image' || $field['type'] === 'file') {
          echo '<p>' . esc_html($field_label) . '</p>';
        } else {
          echo '<label for="' . esc_attr($field_id) . '">' . esc_html($field_label) . '</label>';
        }
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
          echo '<textarea name="yaml_cf[' . esc_attr($field_name) . ']" id="' . esc_attr($field_id) . '" rows="10" class="large-text code" data-language="' . esc_attr($language) . '">' . esc_textarea($field_value) . '</textarea>';
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
          echo '<option value="">-- Select --</option>';

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

            echo '<div class="yaml-cf-taxonomy-checkboxes" style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #fff;">';

            if (!is_wp_error($terms) && !empty($terms)) {
              foreach ($terms as $term) {
                $checked = in_array($term->term_id, $field_value) ? 'checked' : '';
                $checkbox_id = $field_id . '_' . $term->term_id;
                echo '<label style="display: block; margin-bottom: 5px;">';
                echo '<input type="checkbox" name="yaml_cf[' . esc_attr($field_name) . '][]" id="' . esc_attr($checkbox_id) . '" value="' . esc_attr($term->term_id) . '" ' . esc_attr($checked) . ' />';
                echo ' ' . esc_html($term->name);
                echo '</label>';
              }
            } else {
              echo '<p style="margin: 0; color: #666;">' . esc_html__('No terms found.', 'yaml-custom-fields') . '</p>';
            }

            echo '</div>';
          } else {
            // Single select - use dropdown
            echo '<select name="yaml_cf[' . esc_attr($field_name) . ']" id="' . esc_attr($field_id) . '" class="regular-text">';
            echo '<option value="">-- Select ' . esc_html($field['label']) . ' --</option>';

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
          echo '<option value="">-- Select ' . esc_html($field['label']) . ' --</option>';

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
            echo '<p style="color: #d63638;">' . esc_html__('Error: object_type not specified in field options.', 'yaml-custom-fields') . '</p>';
            break;
          }

          // Get entries for this object type
          $data_entries = get_option('yaml_cf_data_object_entries_' . $object_type, []);
          $data_types = get_option('yaml_cf_data_object_types', []);

          if (!isset($data_types[$object_type])) {
            /* translators: %s: data object type name */
            echo '<p style="color: #d63638;">' . sprintf(esc_html__('Error: Data object type "%s" not found.', 'yaml-custom-fields'), esc_html($object_type)) . '</p>';
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

              echo '<div class="yaml-cf-data-object-checkboxes" style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #fff;">';

              foreach ($data_entries as $entry_id => $entry_data) {
                // Use first field value as label
                $entry_label = isset($entry_data[$label_field]) ? $entry_data[$label_field] : $entry_id;
                if (is_array($entry_label)) {
                  $entry_label = $entry_id; // Fallback for complex data
                }

                $checked = in_array($entry_id, $field_value) ? 'checked' : '';
                $checkbox_id = $field_id . '_' . sanitize_html_class($entry_id);
                echo '<label style="display: block; margin-bottom: 5px;">';
                echo '<input type="checkbox" name="yaml_cf[' . esc_attr($field_name) . '][]" id="' . esc_attr($checkbox_id) . '" value="' . esc_attr($entry_id) . '" ' . esc_attr($checked) . ' />';
                echo ' ' . esc_html($entry_label);
                echo '</label>';
              }

              echo '</div>';
            } else {
              echo '<p class="description">' . sprintf(
                // translators: %1$s is the data object type name, %2$s is the URL to manage entries
                esc_html__('No %1$s entries found. %2$sAdd entries%3$s first.', 'yaml-custom-fields'),
                esc_html($data_types[$object_type]['name']),
                '<a href="' . esc_url(admin_url('admin.php?page=yaml-cf-manage-data-object-entries&type_id=' . urlencode($object_type))) . '">',
                '</a>'
              ) . '</p>';
            }
          } else {
            // Single select - use dropdown
            echo '<select name="yaml_cf[' . esc_attr($field_name) . ']" id="' . esc_attr($field_id) . '" class="regular-text">';
            echo '<option value="">-- Select ' . esc_html($field['label']) . ' --</option>';

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
                esc_html__('No %s entries found', 'yaml-custom-fields'),
                esc_html($data_types[$object_type]['name'])
              ) . '</option>';
            }

            echo '</select>';
          }
          break;

        case 'image':
          echo '<input type="hidden" name="yaml_cf[' . esc_attr($field_name) . ']" id="' . esc_attr($field_id) . '" value="' . esc_attr($field_value) . '" />';
          echo '<div class="yaml-cf-media-buttons">';
          echo '<button type="button" class="button yaml-cf-upload-image" data-target="' . esc_attr($field_id) . '">Upload Image</button>';
          if ($field_value) {
            echo '<button type="button" class="button yaml-cf-clear-media" data-target="' . esc_attr($field_id) . '">Clear</button>';
          }
          echo '</div>';
          if ($field_value) {
            // Field value is now attachment ID, get the image URL
            $image_url = wp_get_attachment_image_url($field_value, 'medium');
            if ($image_url) {
              echo '<div class="yaml-cf-image-preview"><img src="' . esc_url($image_url) . '" style="max-width: 200px; display: block; margin-top: 10px;" /></div>';
            }
          }
          break;

        case 'file':
          echo '<input type="hidden" name="yaml_cf[' . esc_attr($field_name) . ']" id="' . esc_attr($field_id) . '" value="' . esc_attr($field_value) . '" />';
          echo '<div class="yaml-cf-media-buttons">';
          echo '<button type="button" class="button yaml-cf-upload-file" data-target="' . esc_attr($field_id) . '">Upload File</button>';
          if ($field_value) {
            echo '<button type="button" class="button yaml-cf-clear-media" data-target="' . esc_attr($field_id) . '">Clear</button>';
          }
          echo '</div>';
          if ($field_value) {
            // Field value is now attachment ID, get the filename
            $file_path = get_attached_file($field_value);
            if ($file_path) {
              echo '<div class="yaml-cf-file-name">' . esc_html(basename($file_path)) . '</div>';
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
            echo '<label style="display: block; margin-bottom: 5px;">' . esc_html($field_label) . '</label>';
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
              echo '<select class="yaml-cf-block-type-select">';
              echo '<option value="">-- Add Block --</option>';
              foreach ($blocks as $block) {
                echo '<option value="' . esc_attr($block['name']) . '">' . esc_html($block['label']) . '</option>';
              }
              echo '</select>';
              echo '<button type="button" class="button yaml-cf-add-block">Add Block</button>';
              echo '</div>';
            }
          }

          echo '</div>';

          // Render snippet below the block container
          if ($is_list && $block_snippet) {
            echo '<span class="yaml-cf-snippet-wrapper">';
            echo '<button type="button" class="yaml-cf-copy-snippet" data-snippet="' . esc_attr($block_snippet) . '" data-popover="' . esc_attr($block_popover_id) . '">';
            echo '<span class="dashicons dashicons-editor-code"></span>';
            echo '<span class="snippet-text">' . esc_html__('Copy snippet', 'yaml-custom-fields') . '</span>';
            echo '</button>';
            echo '<span class="yaml-cf-snippet-popover" id="' . esc_attr($block_popover_id) . '" role="tooltip">';
            echo '<code style="white-space: pre-wrap;">' . esc_html($block_snippet) . '</code>';
            echo '<span class="snippet-hint">' . esc_html__('Click button to copy', 'yaml-custom-fields') . '</span>';
            echo '</span>';
            echo '</span>';
          }
          break;
      }

      // Render snippet below the input field (skip for block fields as they handle their own)
      if ($field['type'] !== 'block' && $code_snippet) {
        echo '<span class="yaml-cf-snippet-wrapper">';
        echo '<button type="button" class="yaml-cf-copy-snippet" data-snippet="' . esc_attr($code_snippet) . '" data-popover="' . esc_attr($popover_id) . '">';
        echo '<span class="dashicons dashicons-editor-code"></span>';
        echo '<span class="snippet-text">' . esc_html__('Copy snippet', 'yaml-custom-fields') . '</span>';
        echo '</button>';
        echo '<span class="yaml-cf-snippet-popover" id="' . esc_attr($popover_id) . '" role="tooltip">';
        echo '<code>' . esc_html($code_snippet) . '</code>';
        echo '<span class="snippet-hint">' . esc_html__('Click button to copy', 'yaml-custom-fields') . '</span>';
        echo '</span>';
        echo '</span>';
      }

      echo '</div>';
    }
  }

  private function render_block_item($field, $blocks, $block_data, $index, $block_key, $context = null) {
    $block_type = isset($block_data[$block_key]) ? $block_data[$block_key] : '';
    $block_def = null;

    // Check if block should be readonly (for template global display)
    $readonly = ($context && is_array($context) && isset($context['readonly']) && $context['readonly']);
    $disabled_attr = $readonly ? ' disabled="disabled"' : '';

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

    echo '<div class="yaml-cf-block-item" data-block-type="' . esc_attr($block_type) . '">';
    echo '<div class="yaml-cf-block-header">';
    echo '<strong>' . esc_html($block_def['label']) . '</strong>';
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
      echo '<div class="yaml-cf-block-fields">';

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
        $block_snippet = $function_name . "('" . esc_js($block_field['name']) . "', null, " . $extra_snippet_params . "\$block)";
        $block_popover_id = 'snippet-' . sanitize_html_class($block_field_id);

        echo '<div class="yaml-cf-field">';
        // For image/file fields with hidden inputs, don't use 'for' attribute as it's invalid
        if ($block_field_type === 'image' || $block_field_type === 'file') {
          echo '<p>' . esc_html($block_field['label']) . '</p>';
        } else {
          echo '<label for="' . esc_attr($block_field_id) . '">' . esc_html($block_field['label']) . '</label>';
        }

        if ($block_field_type === 'boolean') {
          echo '<input type="checkbox" name="yaml_cf[' . esc_attr($field['name']) . '][' . esc_attr($index) . '][' . esc_attr($block_field['name']) . ']" id="' . esc_attr($block_field_id) . '" value="1" ' . checked($block_field_value, 1, false) . esc_attr($disabled_attr) . ' />';
        } elseif ($block_field_type === 'rich-text') {
          $editor_settings = [
            'textarea_name' => 'yaml_cf[' . $field['name'] . '][' . $index . '][' . $block_field['name'] . ']',
            'textarea_rows' => 5,
            'media_buttons' => !$readonly,
            'tinymce' => [
              'toolbar1' => 'formatselect,bold,italic,bullist,numlist,link,unlink',
              'readonly' => $readonly ? 1 : 0,
            ],
            '_content_editor_dfw' => false
          ];
          if ($readonly) {
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
          echo '<textarea name="yaml_cf[' . esc_attr($field['name']) . '][' . esc_attr($index) . '][' . esc_attr($block_field['name']) . ']" id="' . esc_attr($block_field_id) . '" rows="10" class="large-text code" data-language="' . esc_attr($language) . '"' . esc_attr($disabled_attr) . '>' . esc_textarea($block_field_value) . '</textarea>';
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

          echo '<select name="yaml_cf[' . esc_attr($field['name']) . '][' . esc_attr($index) . '][' . esc_attr($block_field['name']) . ']' . ($multiple ? '[]' : '') . '" id="' . esc_attr($block_field_id) . '" ' . ($multiple ? 'multiple' : '') . esc_attr($disabled_attr) . '>';
          echo '<option value="">-- Select --</option>';

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

          // Get terms for the specified taxonomy
          $terms = get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
          ]);

          if ($multiple) {
            $block_field_value = is_array($block_field_value) ? $block_field_value : ($block_field_value ? [$block_field_value] : []);
            echo '<select name="yaml_cf[' . esc_attr($field['name']) . '][' . esc_attr($index) . '][' . esc_attr($block_field['name']) . '][]" id="' . esc_attr($block_field_id) . '" multiple style="height: 150px;" class="regular-text"' . esc_attr($disabled_attr) . '>';
          } else {
            echo '<select name="yaml_cf[' . esc_attr($field['name']) . '][' . esc_attr($index) . '][' . esc_attr($block_field['name']) . ']" id="' . esc_attr($block_field_id) . '" class="regular-text"' . esc_attr($disabled_attr) . '>';
            echo '<option value="">-- Select ' . esc_html($block_field['label']) . ' --</option>';
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
        } elseif ($block_field_type === 'post_type') {
          // Get all public post types
          $post_types = get_post_types(['public' => true], 'objects');

          echo '<select name="yaml_cf[' . esc_attr($field['name']) . '][' . esc_attr($index) . '][' . esc_attr($block_field['name']) . ']" id="' . esc_attr($block_field_id) . '" class="regular-text"' . esc_attr($disabled_attr) . '>';
          echo '<option value="">-- Select ' . esc_html($block_field['label']) . ' --</option>';

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

            echo '<select name="yaml_cf[' . esc_attr($field['name']) . '][' . esc_attr($index) . '][' . esc_attr($block_field['name']) . ']" id="' . esc_attr($block_field_id) . '" class="regular-text"' . esc_attr($disabled_attr) . '>';
            echo '<option value="">-- Select ' . esc_html($type_name) . ' --</option>';

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
          } else {
            echo '<p class="description" style="color: #d63638;">' . esc_html__('Error: object_type not specified in schema', 'yaml-custom-fields') . '</p>';
          }
        } elseif ($block_field_type === 'image') {
          echo '<input type="hidden" name="yaml_cf[' . esc_attr($field['name']) . '][' . esc_attr($index) . '][' . esc_attr($block_field['name']) . ']" id="' . esc_attr($block_field_id) . '" value="' . esc_attr($block_field_value) . '"' . esc_attr($disabled_attr) . ' />';
          if (!$readonly) {
            echo '<div class="yaml-cf-media-buttons">';
            echo '<button type="button" class="button yaml-cf-upload-image" data-target="' . esc_attr($block_field_id) . '">Upload Image</button>';
            if ($block_field_value) {
              echo '<button type="button" class="button yaml-cf-clear-media" data-target="' . esc_attr($block_field_id) . '">Clear</button>';
            }
            echo '</div>';
          }
          if ($block_field_value) {
            $image_url = wp_get_attachment_image_url($block_field_value, 'medium');
            if ($image_url) {
              echo '<div class="yaml-cf-image-preview"><img src="' . esc_url($image_url) . '" style="max-width: 200px; display: block; margin-top: 10px;" /></div>';
            }
          }
        } elseif ($block_field_type === 'file') {
          echo '<input type="hidden" name="yaml_cf[' . esc_attr($field['name']) . '][' . esc_attr($index) . '][' . esc_attr($block_field['name']) . ']" id="' . esc_attr($block_field_id) . '" value="' . esc_attr($block_field_value) . '"' . esc_attr($disabled_attr) . ' />';
          if (!$readonly) {
            echo '<div class="yaml-cf-media-buttons">';
            echo '<button type="button" class="button yaml-cf-upload-file" data-target="' . esc_attr($block_field_id) . '">Upload File</button>';
            if ($block_field_value) {
              echo '<button type="button" class="button yaml-cf-clear-media" data-target="' . esc_attr($block_field_id) . '">Clear</button>';
            }
            echo '</div>';
          }
          if ($block_field_value) {
            $file_path = get_attached_file($block_field_value);
            if ($file_path) {
              echo '<div class="yaml-cf-file-name">' . esc_html(basename($file_path)) . '</div>';
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

        // Render snippet below the input field
        echo '<span class="yaml-cf-snippet-wrapper">';
        echo '<button type="button" class="yaml-cf-copy-snippet" data-snippet="' . esc_attr($block_snippet) . '" data-popover="' . esc_attr($block_popover_id) . '">';
        echo '<span class="dashicons dashicons-editor-code"></span>';
        echo '<span class="snippet-text">' . esc_html__('Copy snippet', 'yaml-custom-fields') . '</span>';
        echo '</button>';
        echo '<span class="yaml-cf-snippet-popover" id="' . esc_attr($block_popover_id) . '" role="tooltip">';
        echo '<code>' . esc_html($block_snippet) . '</code>';
        echo '<span class="snippet-hint">' . esc_html__('Click button to copy', 'yaml-custom-fields') . '</span>';
        echo '</span>';
        echo '</span>';

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

      // Update post meta using WordPress API (handles serialization and caching)
      update_post_meta($post_id, '_yaml_cf_data', $sanitized_data);

      // Store schema for validation purposes
      if ($schema) {
        update_post_meta($post_id, '_yaml_cf_schema', $schema);
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
    update_post_meta($post_id, '_yaml_cf_use_template_global_fields', $use_template_global_fields);
  }

  public function ajax_get_partial_data() {
    check_ajax_referer('yaml_cf_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
      wp_send_json_error('Permission denied');
    }

    $template = isset($_POST['template']) ? sanitize_text_field(wp_unslash($_POST['template'])) : '';

    // Get schema
    $schemas = get_option('yaml_cf_schemas', []);
    $schema_yaml = isset($schemas[$template]) ? $schemas[$template] : '';

    if (empty($schema_yaml)) {
      wp_send_json_error('No schema found');
      return;
    }

    $schema = $this->parse_yaml_schema($schema_yaml);

    // Get existing data
    $partial_data = get_option('yaml_cf_partial_data', []);
    $data = isset($partial_data[$template]) ? $partial_data[$template] : [];

    wp_send_json_success([
      'schema' => $schema,
      'data' => $data
    ]);
  }

  public function ajax_save_partial_data() {
    check_ajax_referer('yaml_cf_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
      wp_send_json_error('Permission denied');
    }

    $template = isset($_POST['template']) ? sanitize_text_field(wp_unslash($_POST['template'])) : '';
    $json_data = isset($_POST['data']) ? sanitize_textarea_field(wp_unslash($_POST['data'])) : '{}';
    $data = json_decode($json_data, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
      $data = [];
    }

    // Get existing partial data
    $partial_data = get_option('yaml_cf_partial_data', []);

    // Update data for this partial
    $partial_data[$template] = $data;

    // Save back to options
    update_option('yaml_cf_partial_data', $partial_data);

    // Clear caches
    $this->clear_data_caches();

    wp_send_json_success();
  }

  public function ajax_export_settings() {
    check_ajax_referer('yaml_cf_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
      wp_send_json_error('Permission denied');
    }

    // Gather all settings
    $export_data = [
      'plugin' => 'yaml-custom-fields',
      'version' => YAML_CF_VERSION,
      'exported_at' => current_time('mysql'),
      'site_url' => get_site_url(),
      'settings' => [
        'template_settings' => get_option('yaml_cf_template_settings', []),
        'schemas' => get_option('yaml_cf_schemas', []),
        'partial_data' => get_option('yaml_cf_partial_data', []),
        'template_global_schemas' => get_option('yaml_cf_template_global_schemas', []),
        'template_global_data' => get_option('yaml_cf_template_global_data', []),
        'global_schema' => get_option('yaml_cf_global_schema', ''),
        'global_data' => get_option('yaml_cf_global_data', [])
      ]
    ];

    wp_send_json_success($export_data);
  }

  public function ajax_import_settings() {
    check_ajax_referer('yaml_cf_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
      wp_send_json_error('Permission denied');
    }

    if (!isset($_POST['data']) || !is_string($_POST['data'])) {
      wp_send_json_error('No data provided');
    }

    // Sanitize and decode JSON data
    $json_data = sanitize_textarea_field(wp_unslash($_POST['data']));
    $import_data = json_decode($json_data, true);

    // Check for JSON decode errors
    if (json_last_error() !== JSON_ERROR_NONE) {
      wp_send_json_error('Invalid JSON: ' . json_last_error_msg());
    }

    // Validate import data
    if (!$import_data || !isset($import_data['plugin']) || $import_data['plugin'] !== 'yaml-custom-fields') {
      wp_send_json_error('Invalid import file format');
    }

    if (!isset($import_data['settings'])) {
      wp_send_json_error('No settings found in import file');
    }

    $settings = $import_data['settings'];
    $merge = isset($_POST['merge']) && $_POST['merge'] === 'true';

    // Import template settings
    if (isset($settings['template_settings'])) {
      if ($merge) {
        $existing = get_option('yaml_cf_template_settings', []);
        $settings['template_settings'] = array_merge($existing, $settings['template_settings']);
      }
      update_option('yaml_cf_template_settings', $settings['template_settings']);
    }

    // Import schemas
    if (isset($settings['schemas'])) {
      if ($merge) {
        $existing = get_option('yaml_cf_schemas', []);
        $settings['schemas'] = array_merge($existing, $settings['schemas']);
      }
      update_option('yaml_cf_schemas', $settings['schemas']);
    }

    // Import partial data
    if (isset($settings['partial_data'])) {
      if ($merge) {
        $existing = get_option('yaml_cf_partial_data', []);
        $settings['partial_data'] = array_merge($existing, $settings['partial_data']);
      }
      update_option('yaml_cf_partial_data', $settings['partial_data']);
    }

    // Import template global schemas
    if (isset($settings['template_global_schemas'])) {
      if ($merge) {
        $existing = get_option('yaml_cf_template_global_schemas', []);
        $settings['template_global_schemas'] = array_merge($existing, $settings['template_global_schemas']);
      }
      update_option('yaml_cf_template_global_schemas', $settings['template_global_schemas']);
    }

    // Import template global data
    if (isset($settings['template_global_data'])) {
      if ($merge) {
        $existing = get_option('yaml_cf_template_global_data', []);
        $settings['template_global_data'] = array_merge($existing, $settings['template_global_data']);
      }
      update_option('yaml_cf_template_global_data', $settings['template_global_data']);
    }

    // Import global schema
    if (isset($settings['global_schema'])) {
      update_option('yaml_cf_global_schema', $settings['global_schema']);
    }

    // Import global data
    if (isset($settings['global_data'])) {
      if ($merge) {
        $existing = get_option('yaml_cf_global_data', []);
        $settings['global_data'] = array_merge($existing, $settings['global_data']);
      }
      update_option('yaml_cf_global_data', $settings['global_data']);
    }

    // Clear template cache
    $this->clear_template_cache();

    // Clear data caches
    $this->clear_data_caches();

    wp_send_json_success([
      'message' => 'Settings imported successfully',
      'imported_from' => isset($import_data['site_url']) ? $import_data['site_url'] : 'unknown',
      'exported_at' => isset($import_data['exported_at']) ? $import_data['exported_at'] : 'unknown'
    ]);
  }

  public function ajax_get_posts_with_data() {
    check_ajax_referer('yaml_cf_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
      wp_send_json_error('Permission denied');
    }

    // Get all posts and pages that have custom field data
    // Try to get from cache first
    $cache_key = 'yaml_cf_posts_with_data';
    $posts = wp_cache_get($cache_key, 'yaml-custom-fields');

    if (false === $posts) {
      // Query all posts without meta_query to avoid slow query warnings
      // Filter in PHP using metadata_exists() which uses WordPress's cached post meta
      $all_posts = get_posts([
        'post_type' => ['page', 'post'],
        'post_status' => ['publish', 'draft', 'pending', 'private'],
        'posts_per_page' => -1,
        'orderby' => ['post_type' => 'ASC', 'title' => 'ASC'],
        'no_found_rows' => true,
        'update_post_term_cache' => false,
      ]);

      $posts = [];
      foreach ($all_posts as $post) {
        // Filter: only include posts with custom field data
        // metadata_exists() uses cached post meta, so this is fast
        if (!metadata_exists('post', $post->ID, '_yaml_cf_data')) {
          continue;
        }

        $template = $this->get_template_for_post($post);

        $posts[] = [
          'id' => $post->ID,
          'title' => $post->post_title,
          'slug' => $post->post_name,
          'type' => $post->post_type,
          'status' => $post->post_status,
          'template' => $template,
          'edit_url' => get_edit_post_link($post->ID)
        ];
      }

      // Cache for 1 hour
      wp_cache_set($cache_key, $posts, 'yaml-custom-fields', 3600);
    }

    wp_send_json_success(['posts' => $posts]);
  }

  public function ajax_export_page_data() {
    check_ajax_referer('yaml_cf_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
      wp_send_json_error('Permission denied');
    }

    $post_ids = isset($_POST['post_ids']) ? array_map('intval', wp_unslash($_POST['post_ids'])) : [];
    $match_by = isset($_POST['match_by']) ? sanitize_text_field(wp_unslash($_POST['match_by'])) : 'slug';

    if (empty($post_ids)) {
      wp_send_json_error('No posts selected');
    }

    $export_data = [
      'plugin' => 'yaml-custom-fields',
      'version' => YAML_CF_VERSION,
      'exported_at' => current_time('mysql'),
      'site_url' => get_site_url(),
      'match_by' => $match_by,
      'posts' => []
    ];

    foreach ($post_ids as $post_id) {
      $post = get_post($post_id);
      if (!$post) {
        continue;
      }

      $template = $this->get_template_for_post($post);

      $data = get_post_meta($post_id, '_yaml_cf_data', true);
      if (empty($data)) {
        continue;
      }

      // Get schema if available
      $schema = get_post_meta($post_id, '_yaml_cf_schema', true);

      $post_data = [
        'id' => $post->ID,
        'title' => $post->post_title,
        'slug' => $post->post_name,
        'type' => $post->post_type,
        'template' => $template,
        'data' => $data
      ];

      // Include schema if available
      if (!empty($schema)) {
        $post_data['schema'] = $schema;
      }

      $export_data['posts'][] = $post_data;
    }

    wp_send_json_success($export_data);
  }

  public function ajax_import_page_data() {
    check_ajax_referer('yaml_cf_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
      wp_send_json_error('Permission denied');
    }

    if (!isset($_POST['data']) || !is_string($_POST['data'])) {
      wp_send_json_error('No data provided');
    }

    // Sanitize and decode JSON data
    $json_data = sanitize_textarea_field(wp_unslash($_POST['data']));
    $import_data = json_decode($json_data, true);

    // Check for JSON decode errors
    if (json_last_error() !== JSON_ERROR_NONE) {
      wp_send_json_error('Invalid JSON: ' . json_last_error_msg());
    }

    // Validate import data
    if (!$import_data || !isset($import_data['plugin']) || $import_data['plugin'] !== 'yaml-custom-fields') {
      wp_send_json_error('Invalid import file format');
    }

    // Handle both single-post and multi-post formats
    $posts_to_import = [];
    if (isset($import_data['type']) && $import_data['type'] === 'single-post' && isset($import_data['post'])) {
      // Single post export format
      $posts_to_import = [$import_data['post']];
      $match_by = 'slug'; // Default for single post
    } elseif (isset($import_data['posts']) && is_array($import_data['posts'])) {
      // Multi-post export format
      $posts_to_import = $import_data['posts'];
      $match_by = isset($import_data['match_by']) ? $import_data['match_by'] : 'slug';
    } else {
      wp_send_json_error('No posts found in import file');
    }

    $imported = 0;
    $skipped = 0;
    $errors = [];
    $debug_info = [];

    foreach ($posts_to_import as $post_data) {
      $target_post = null;

      // Find the target post based on match_by preference
      if ($match_by === 'id') {
        $target_post = get_post($post_data['id']);
      } else {
        // Match by slug
        $args = [
          'name' => $post_data['slug'],
          'post_type' => $post_data['type'],
          'post_status' => 'any',
          'posts_per_page' => 1
        ];
        $posts = get_posts($args);
        if (!empty($posts)) {
          $target_post = $posts[0];
        }
      }

      if (!$target_post) {
        $skipped++;
        $errors[] = sprintf('Post not found: %s (slug: %s, id: %d)', $post_data['title'], $post_data['slug'], $post_data['id']);
        $debug_info[] = [
          'action' => 'skipped',
          'reason' => 'post_not_found',
          'search_by' => $match_by,
          'search_value' => $match_by === 'id' ? $post_data['id'] : $post_data['slug']
        ];
        continue;
      }

      // Validate attachments (images/files) and clean up missing ones
      $original_data_count = is_array($post_data['data']) ? count($post_data['data']) : 0;

      // Debug: Log original data sample
      // Pass schema to validation so we know which fields are actually attachments
      $schema = isset($post_data['schema']) ? $post_data['schema'] : null;
      $cleaned_data = $this->validate_and_clean_attachment_data($post_data['data'], $schema);

      // Check if data is empty
      $data_field_count = is_array($cleaned_data) ? count($cleaned_data) : 0;
      $has_schema = isset($post_data['schema']);

      // Update post meta using WordPress API (handles serialization and caching)
      $data_updated = update_post_meta($target_post->ID, '_yaml_cf_data', $cleaned_data);

      // Mark as imported and store schema if available
      update_post_meta($target_post->ID, '_yaml_cf_imported', true);
      $schema_updated = false;
      if (isset($post_data['schema'])) {
        $schema_updated = update_post_meta($target_post->ID, '_yaml_cf_schema', $post_data['schema']);
      }

      $debug_info[] = [
        'action' => 'imported',
        'post_id' => $target_post->ID,
        'post_title' => $target_post->post_title,
        'original_data_fields' => $original_data_count,
        'cleaned_data_fields' => $data_field_count,
        'data_updated' => $data_updated,
        'schema_included' => $has_schema,
        'schema_updated' => $schema_updated,
        'sample_data' => is_array($cleaned_data) && !empty($cleaned_data) ? array_slice($cleaned_data, 0, 3, true) : 'empty'
      ];

      $imported++;
    }

    // Clear caches after import
    $this->clear_data_caches();

    wp_send_json_success([
      'message' => sprintf('Import complete. %d imported, %d skipped.', $imported, $skipped),
      'imported' => $imported,
      'skipped' => $skipped,
      'errors' => $errors,
      'debug' => $debug_info,
      'imported_from' => isset($import_data['site_url']) ? $import_data['site_url'] : 'unknown',
      'exported_at' => isset($import_data['exported_at']) ? $import_data['exported_at'] : 'unknown'
    ]);
  }

  private function validate_and_clean_attachment_data($data, $schema = null, $parent_key = '') {
    if (!is_array($data)) {
      return $data;
    }

    // Build a map of attachment field names from schema
    $attachment_fields = [];
    if ($schema && isset($schema['fields']) && is_array($schema['fields'])) {
      $this->build_attachment_field_map($schema['fields'], $attachment_fields);
    }

    foreach ($data as $key => $value) {
      // Build full field path for nested fields
      $field_path = $parent_key ? $parent_key . '.' . $key : $key;

      if (is_array($value)) {
        // Recursively clean nested arrays
        $data[$key] = $this->validate_and_clean_attachment_data($value, $schema, $field_path);
      } elseif (is_numeric($value) && intval($value) > 0) {
        // Only validate if this field is an image/file field according to schema
        $is_attachment_field = empty($attachment_fields) || in_array($key, $attachment_fields) || in_array($field_path, $attachment_fields);

        if ($is_attachment_field) {
          // Check if this might be an attachment ID
          $attachment = get_post(intval($value));
          if ($attachment && $attachment->post_type === 'attachment') {
            // Valid attachment, keep it
            continue;
          } elseif ($attachment) {
            // It's a valid post ID but not an attachment, keep it
            continue;
          } else {
            // Attachment doesn't exist, set to empty
            $data[$key] = '';
          }
        }
      }
    }

    return $data;
  }

  /**
   * Build a map of field names that are image or file type fields
   */
  private function build_attachment_field_map($fields, &$attachment_fields, $prefix = '') {
    foreach ($fields as $field) {
      if (!isset($field['name'])) continue;

      $field_path = $prefix ? $prefix . '.' . $field['name'] : $field['name'];

      // Check if this is an image or file field
      if (isset($field['type']) && in_array($field['type'], ['image', 'file'])) {
        $attachment_fields[] = $field['name'];
        $attachment_fields[] = $field_path;
      }

      // Check nested fields in blocks
      if (isset($field['blocks']) && is_array($field['blocks'])) {
        foreach ($field['blocks'] as $block) {
          if (isset($block['fields']) && is_array($block['fields'])) {
            $this->build_attachment_field_map($block['fields'], $attachment_fields, $field_path);
          }
        }
      }

      // Check nested fields in field groups
      if (isset($field['fields']) && is_array($field['fields'])) {
        $this->build_attachment_field_map($field['fields'], $attachment_fields, $field_path);
      }
    }
  }

  /**
   * Clear all plugin caches
   * Call this whenever custom field data changes
   *
   * @param int|null $post_id Optional. Specific post ID to clear cache for. If null, clears all.
   */
  private function clear_data_caches($post_id = null) {
    // Clear validation page cache
    wp_cache_delete('yaml_cf_validation_posts', 'yaml-custom-fields');

    // Clear export page cache
    wp_cache_delete('yaml_cf_posts_with_data', 'yaml-custom-fields');

    // Clear WordPress post meta cache
    // This ensures that get_post_meta() calls return fresh data
    if ($post_id) {
      // Clear cache for specific post
      wp_cache_delete($post_id, 'post_meta');
      clean_post_cache($post_id);
    } else {
      // Clear cache for all posts with custom field data
      // Use a tracking option to avoid slow meta_query
      $tracked_post_ids = get_option('yaml_cf_tracked_posts', []);

      // If we have tracked posts, use those
      if (!empty($tracked_post_ids)) {
        foreach ($tracked_post_ids as $pid) {
          wp_cache_delete($pid, 'post_meta');
          clean_post_cache($pid);
        }
      } else {
        // Fallback: Query all posts and filter in PHP (no meta_query)
        // This only happens if tracking option is empty
        $all_posts = get_posts([
          'post_type' => 'any',
          'post_status' => 'any',
          'posts_per_page' => -1,
          'fields' => 'ids',
          'no_found_rows' => true,
          'update_post_meta_cache' => false,
          'update_post_term_cache' => false,
        ]);

        foreach ($all_posts as $pid) {
          // Only clear cache if post has custom field data
          if (metadata_exists('post', $pid, '_yaml_cf_data')) {
            wp_cache_delete($pid, 'post_meta');
            clean_post_cache($pid);
          }
        }
      }
    }
  }

  /**
   * Export all data object types and their entries
   */
  public function export_data_objects() {
    $data_object_types = get_option('yaml_cf_data_object_types', []);

    $export_data = [
      'plugin' => 'yaml-custom-fields',
      'type' => 'data_objects',
      'version' => YAML_CF_VERSION,
      'site_url' => get_site_url(),
      'exported_at' => current_time('mysql'),
      'types' => []
    ];

    foreach ($data_object_types as $type_slug => $type_data) {
      $entries = get_option('yaml_cf_data_object_entries_' . $type_slug, []);

      // Clean attachment data in entries
      $cleaned_entries = [];
      $schema = isset($type_data['schema']) ? $type_data['schema'] : null;
      foreach ($entries as $entry_id => $entry_data) {
        $cleaned_entries[$entry_id] = $this->validate_and_clean_attachment_data($entry_data, $schema);
      }

      $export_data['types'][$type_slug] = [
        'name' => $type_data['name'],
        'schema' => $type_data['schema'],
        'entries' => $cleaned_entries
      ];
    }

    // Set headers for download
    $filename = 'yaml-cf-data-objects-' . sanitize_file_name(wp_parse_url(get_site_url(), PHP_URL_HOST)) . '-' . gmdate('Y-m-d-His') . '.json';
    nocache_headers();
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Type: application/json; charset=utf-8');

    echo wp_json_encode($export_data, JSON_PRETTY_PRINT);
  }

  /**
   * Import data object types and entries via AJAX
   */
  public function ajax_import_data_objects() {
    check_ajax_referer('yaml_cf_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
      wp_send_json_error('Permission denied');
    }

    if (!isset($_POST['data']) || !is_string($_POST['data'])) {
      wp_send_json_error('No data provided');
    }

    // Sanitize and decode JSON data
    $json_data = sanitize_textarea_field(wp_unslash($_POST['data']));
    $import_data = json_decode($json_data, true);

    // Check for JSON decode errors
    if (json_last_error() !== JSON_ERROR_NONE) {
      wp_send_json_error('Invalid JSON: ' . json_last_error_msg());
    }

    // Validate import data
    if (!$import_data || !isset($import_data['plugin']) || $import_data['plugin'] !== 'yaml-custom-fields') {
      wp_send_json_error('Invalid import file format');
    }

    if (!isset($import_data['type']) || $import_data['type'] !== 'data_objects') {
      wp_send_json_error('Invalid import file type. Expected data_objects export file.');
    }

    if (!isset($import_data['types']) || !is_array($import_data['types'])) {
      wp_send_json_error('No data object types found in import file');
    }

    $types_imported = 0;
    $entries_imported = 0;
    $errors = [];

    // Get existing types
    $existing_types = get_option('yaml_cf_data_object_types', []);

    foreach ($import_data['types'] as $type_slug => $type_data) {
      // Validate required fields
      if (!isset($type_data['name']) || !isset($type_data['schema'])) {
        $errors[] = sprintf('Invalid type data for: %s', $type_slug);
        continue;
      }

      // Import/update the type
      $existing_types[$type_slug] = [
        'name' => $type_data['name'],
        'schema' => $type_data['schema']
      ];

      // Import entries
      if (isset($type_data['entries']) && is_array($type_data['entries'])) {
        $cleaned_entries = [];
        foreach ($type_data['entries'] as $entry_id => $entry_data) {
          // Clean attachment data
          $cleaned_entries[$entry_id] = $this->validate_and_clean_attachment_data($entry_data);
          $entries_imported++;
        }

        update_option('yaml_cf_data_object_entries_' . $type_slug, $cleaned_entries);
      }

      $types_imported++;
    }

    // Save updated types
    update_option('yaml_cf_data_object_types', $existing_types);

    wp_send_json_success([
      'message' => sprintf('Import complete. %d types imported with %d total entries.', $types_imported, $entries_imported),
      'types_imported' => $types_imported,
      'entries_imported' => $entries_imported,
      'errors' => $errors,
      'exported_at' => isset($import_data['exported_at']) ? $import_data['exported_at'] : 'unknown'
    ]);
  }

}

function yaml_cf_init() {
  return YAML_Custom_Fields::get_instance();
}

add_action('plugins_loaded', 'yaml_cf_init');

// Load Composer autoload for src/ directory (new architecture)
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
  require_once __DIR__ . '/vendor/autoload.php';
}

// Initialize new Plugin architecture (Phase 1: running in parallel with old code)
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
  // If context data is provided, search within that array
  if (is_array($context_data)) {
    return isset($context_data[$field_name]) ? $context_data[$field_name] : null;
  }

  // Handle partials
  if (is_string($post_id) && strpos($post_id, 'partial:') === 0) {
    $partial_file = str_replace('partial:', '', $post_id);
    $partial_data = get_option('yaml_cf_partial_data', []);

    if (isset($partial_data[$partial_file][$field_name])) {
      return $partial_data[$partial_file][$field_name];
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

  // Check if template global is enabled for this specific field
  $use_template_global_fields = get_post_meta($post_id, '_yaml_cf_use_template_global_fields', true);
  if (is_array($use_template_global_fields) && isset($use_template_global_fields[$field_name]) && $use_template_global_fields[$field_name] === '1') {
    // Get the template for this post
    $post = get_post($post_id);
    if ($post) {
      $plugin = YAML_Custom_Fields::get_instance();
      $template = $plugin->get_template_for_post($post);

      // Get template global data
      $template_global_data_array = get_option('yaml_cf_template_global_data', []);
      if (isset($template_global_data_array[$template][$field_name])) {
        return $template_global_data_array[$template][$field_name];
      }
    }
  }

  // Check for site-wide global data
  // (Only if the template has site-wide global enabled)
  $post = $post ?? get_post($post_id);
  if ($post) {
    $plugin = $plugin ?? YAML_Custom_Fields::get_instance();
    $template = $template ?? $plugin->get_template_for_post($post);

    $template_settings = get_option('yaml_cf_template_settings', []);
    $use_global = isset($template_settings[$template . '_use_global']) && $template_settings[$template . '_use_global'];

    if ($use_global) {
      $global_data = get_option('yaml_cf_global_data', []);
      if (is_array($global_data) && isset($global_data[$field_name])) {
        return $global_data[$field_name];
      }
    }
  }

  // Finally, check post-specific data
  if (isset($data[$field_name])) {
    return $data[$field_name];
  }

  // Fallback: check template global data for fields defined only in template global schema
  if ($post) {
    $plugin = $plugin ?? YAML_Custom_Fields::get_instance();
    $template = $template ?? $plugin->get_template_for_post($post);

    $template_global_data_array = get_option('yaml_cf_template_global_data', []);
    if (isset($template_global_data_array[$template][$field_name])) {
      return $template_global_data_array[$template][$field_name];
    }
  }

  return null;
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

    return isset($partial_data[$partial_file]) ? $partial_data[$partial_file] : [];
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
    return $merged_data;
  }

  $plugin = YAML_Custom_Fields::get_instance();
  $template = $plugin->get_template_for_post($post);
  $template_settings = get_option('yaml_cf_template_settings', []);

  // Merge site-wide global data (if enabled for template)
  $use_global = isset($template_settings[$template . '_use_global']) && $template_settings[$template . '_use_global'];
  if ($use_global) {
    $global_data = get_option('yaml_cf_global_data', []);
    if (is_array($global_data)) {
      $merged_data = array_merge($global_data, $merged_data);
    }
  }

  // Merge template global data (if enabled for post)
  $use_template_global = get_post_meta($post_id, '_yaml_cf_use_template_global', true);
  if ($use_template_global) {
    $template_global_data_array = get_option('yaml_cf_template_global_data', []);
    if (isset($template_global_data_array[$template]) && is_array($template_global_data_array[$template])) {
      $merged_data = array_merge($merged_data, $template_global_data_array[$template]);
    }
  }

  // Apply overrides (highest priority)
  if (isset($post_data['_template_global_override']) && is_array($post_data['_template_global_override'])) {
    $merged_data = array_merge($merged_data, $post_data['_template_global_override']);
  }

  // Remove internal keys
  unset($merged_data['_template_global_override']);

  return $merged_data;
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
    return $global_data[$field_name];
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
  return is_array($global_data) ? $global_data : [];
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
 * @return array|null Array with 'id', 'url', 'alt', 'title', 'caption', 'description', 'width', 'height' keys or null if not found
 *
 * Usage Examples:
 *
 * Basic Usage (Current Page):
 *   $hero = yaml_cf_get_image('hero_image', null, 'large');
 *   if ($hero) {
 *     echo '<img src="' . esc_url($hero['url']) . '" alt="' . esc_attr($hero['alt']) . '" />';
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
  $attachment_id = yaml_cf_get_field($field_name, $post_id, $context_data);

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
 * @return array|null Array with 'id', 'url', 'path', 'filename', 'filesize', 'mime_type', 'title' keys or null if not found
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
 */
function yaml_cf_get_file($field_name, $post_id = null, $context_data = null) {
  $attachment_id = yaml_cf_get_field($field_name, $post_id, $context_data);

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

register_uninstall_hook(__FILE__, 'yaml_cf_uninstall');

function yaml_cf_uninstall() {
  delete_option('yaml_cf_template_settings');
  delete_option('yaml_cf_schemas');
  delete_option('yaml_cf_partial_data');

  // Delete all post meta for this plugin across all posts
  delete_post_meta_by_key('_yaml_cf_data');

  // Delete data object types and their entries
  $data_object_types = get_option('yaml_cf_data_object_types', []);
  if (!empty($data_object_types)) {
    foreach ($data_object_types as $type_slug => $type_data) {
      delete_option('yaml_cf_data_object_entries_' . $type_slug);
    }
  }
  delete_option('yaml_cf_data_object_types');

  // Clear template cache
  delete_transient('yaml_cf_templates_' . get_stylesheet());
}
