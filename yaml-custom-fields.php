<?php
/**
 * Plugin Name: YAML Custom Fields
 * Plugin URI: https://github.com/maliMirkec/yaml-custom-fields
 * Description: Add your own fields to pages, posts and every part of your site. You choose the fields; writers fill them in.
 * Version: 2.0.0
 * Requires at least: 6.4
 * Requires PHP: 8.1
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
    // Every hook is registered by YamlCF\Core\HookManager
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
   * Handle post deletion - remove from tracking
   *
   * @param int $post_id Post ID being deleted
   */
  public function handle_post_deletion($post_id) {
    \YamlCF\Cache\PostTracker::untrack($post_id);
  }

  /**
   * Download one post's fields (kept for compatibility; see YamlCF\Form\ExportRequestHandler)
   *
   * @return void
   */
  public function handle_single_post_export() {
    \YamlCF\Core\Plugin::getInstance()->get('form_handler')->exports()->singlePost();
  }

  /**
   * Download the settings (kept for compatibility; see YamlCF\Form\ExportRequestHandler)
   *
   * @return void
   */
  public function handle_settings_export() {
    \YamlCF\Core\Plugin::getInstance()->get('form_handler')->exports()->settings();
  }

  /**
   * Download several posts' fields (kept for compatibility; see YamlCF\Form\ExportRequestHandler)
   *
   * @return void
   */
  public function handle_page_data_export() {
    \YamlCF\Core\Plugin::getInstance()->get('form_handler')->exports()->pageData();
  }

  /**
   * Download the collections (kept for compatibility; see YamlCF\Form\ExportRequestHandler)
   *
   * @return void
   */
  public function handle_data_objects_export() {
    \YamlCF\Core\Plugin::getInstance()->get('form_handler')->exports()->dataObjects();
  }

  /**
   * Handle the collection forms (kept for compatibility; see YamlCF\Form\FormHandler)
   *
   * @return void
   */
  public function handle_data_object_type_submissions() {
    \YamlCF\Core\Plugin::getInstance()->get('form_handler')->handleDataObjectSubmissions();
  }

  /**
   * Handle the admin screens' forms (kept for compatibility; see YamlCF\Form\FormHandler)
   *
   * @return void
   */
  public function handle_form_submissions() {
    \YamlCF\Core\Plugin::getInstance()->get('form_handler')->handleSubmissions();
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
    return \YamlCF\Helpers\CodeFieldCodec::decode($value);
  }


  /**
   * @deprecated Now handled by CacheManager::clearTemplateCache()
   * Kept for backward compatibility - delegates to new architecture
   */
  public function clear_template_cache() {
    \YamlCF\Core\Plugin::getInstance()->get('cache_manager')->clearTemplateCache();
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
    \YamlCF\Core\Plugin::getInstance()->get('meta_box')->register($post_type, $post);
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
    \YamlCF\Core\Plugin::getInstance()->get('meta_box')->render($post);
  }

  /**
   * Print the custom fields box directly under the title, in the classic editor
   *
   * Only ever runs there: the block editor does not fire edit_form_after_title,
   * and register_meta_box() stands down when this hook is going to fire, so the
   * box is printed once and only once.
   *
   * @param WP_Post $post Post being edited
   * @return void
   */
  public function render_schema_meta_box_after_title($post) {
    \YamlCF\Core\Plugin::getInstance()->get('meta_box')->renderAfterTitle($post);
  }

  /**
   * The service that prints fields as a form (YamlCF\Rendering\FieldRenderer)
   *
   * @return \YamlCF\Rendering\FieldRenderer
   */
  private function field_renderer() {
    return \YamlCF\Core\Plugin::getInstance()->get('field_renderer');
  }

  /**
   * Print a set of fields as a form
   *
   * Kept for the screens and any other code that calls it; delegates to
   * YamlCF\Rendering\FieldRenderer::renderFields().
   *
   * @param array $fields Fields from the schema
   * @param array $saved_data Saved values keyed by field name
   * @param string $prefix Name prefix for nested fields
   * @param array|null $context Render context
   * @return void
   */
  public function render_schema_fields($fields, $saved_data, $prefix = '', $context = null) {
    $this->field_renderer()->renderFields($fields, $saved_data, $prefix, $context);
  }

  /**
   * How many choices a taxonomy or collection may have before its field turns
   * into a search box
   *
   * Kept for backward compatibility; delegates to
   * YamlCF\Rendering\PickerRenderer::threshold().
   *
   * @param string $type Field type: 'taxonomy' or 'data_object'
   * @param string $key Taxonomy name or collection slug
   * @return int Threshold
   */
  public static function get_picker_threshold($type, $key) {
    return \YamlCF\Rendering\PickerRenderer::threshold($type, $key);
  }

  /**
   * Print one empty block item, for the "Add section" button
   *
   * Kept for backward compatibility; delegates to
   * YamlCF\Rendering\FieldRenderer::renderNewBlockItem().
   *
   * @param array|null $schema Parsed schema holding the block field
   * @param string $field_name Name of the block field
   * @param string $block_type Which block type to add
   * @param int $index Position of the new block in the list
   * @param array|null $context Rendering context
   * @return bool Whether the block field was found
   */
  public function render_new_block_item($schema, $field_name, $block_type, $index, $context = null) {
    return $this->field_renderer()->renderNewBlockItem($schema, $field_name, $block_type, $index, $context);
  }

  /**
   * Drop memoized field values when post meta they are built from changes
   *
   * @param int|array $meta_id Meta row ID, or IDs when a meta row is deleted (unused)
   * @param int $object_id Post ID
   * @param string $meta_key Meta key that changed
   * @return void
   */
  public function flush_resolved_fields_for_meta($meta_id, $object_id, $meta_key) {
    \YamlCF\Core\Plugin::getInstance()->get('field_resolver')->flushForMeta($meta_id, $object_id, $meta_key);
  }

  /**
   * Drop every memoized field value when a plugin option changes
   *
   * Schemas, shared content and site details apply across posts, so there is no
   * single post to forget.
   *
   * @param string $option Option name that changed
   * @return void
   */
  public function flush_resolved_fields_for_option($option) {
    \YamlCF\Core\Plugin::getInstance()->get('field_resolver')->flushForOption($option);
  }

  public function save_schema_data($post_id) {
    \YamlCF\Core\Plugin::getInstance()->get('meta_box_saver')->save($post_id);
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

// Start the plugin: builds the service container and registers every hook
// (YamlCF\Core\HookManager). The YamlCF classes load through build/vendor/scoper-autoload.php.
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
  return \YamlCF\Core\Plugin::getInstance()->get('field_accessor')->getField($field_name, $post_id, $context_data);
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
  return \YamlCF\Core\Plugin::getInstance()->get('field_accessor')->getFieldUnfiltered($field_name, $post_id, $context_data);
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
  return \YamlCF\Core\Plugin::getInstance()->get('field_resolver')->autoDecodeIfCodeField($value, $schema, $field_name);
}

/**
 * Parse a YAML schema string once per request.
 *
 * @param string $yaml YAML schema.
 * @return array|null Parsed schema or null if invalid.
 */
function yaml_cf_parse_schema_cached($yaml) {
  return \YamlCF\Core\Plugin::getInstance()->get('field_resolver')->parseSchemaCached($yaml);
}

/**
 * Get the YAML template global schema for a template.
 *
 * @param string $template Template file name.
 * @return string YAML schema or '' if none.
 */
function yaml_cf_get_template_global_schema($template) {
  return \YamlCF\Core\Plugin::getInstance()->get('field_resolver')->templateGlobalSchema($template);
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
  return \YamlCF\Helpers\CodeFieldCodec::maybeDecode($value);
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
  return \YamlCF\Core\Plugin::getInstance()->get('field_accessor')->getFields($post_id);
}

/**
 * Work out every field value for a post, from every source that can supply one.
 *
 * This is the single place the precedence rules live. yaml_cf_get_field() looks
 * one name up in the result and yaml_cf_get_fields() returns all of it, so the
 * two can never disagree about the same field again.
 *
 * Sources, highest priority first:
 *
 *   1. The shared (template global) value, for each field the editor ticked
 *      "Use the shared value on this page" for.
 *   2. The shared value, for fields that exist only in the shared schema. Older
 *      versions froze a copy of these into post meta; the copy is discarded.
 *   3. The value stored on the page.
 *   4. The shared value, for a field the page has never been saved with. A page
 *      saved with the field left empty keeps its empty value.
 *   5. Site details, when the template uses them.
 *
 * This is the order the Documentation page describes.
 *
 * The result is memoized for the rest of the request: a template that reads
 * thirty fields used to re-read the same six options thirty times over.
 *
 * @param int $post_id Post ID.
 * @return array Field values keyed by field name, code fields already decoded.
 */
function yaml_cf_resolve_post_fields($post_id) {
  return \YamlCF\Core\Plugin::getInstance()->get('field_resolver')->resolvePostFields($post_id);
}

/**
 * The request-lifetime store behind yaml_cf_resolve_post_fields().
 *
 * Held in its own function so that flushing it is possible from elsewhere.
 *
 * @return array Resolved field values keyed by post ID (by reference).
 */
function &yaml_cf_resolved_post_fields_cache() {
  $cache = &\YamlCF\Core\Plugin::getInstance()->get('field_resolver')->resolvedCache();
  return $cache;
}

/**
 * Forget resolved field values, so the next read sees what was just saved.
 *
 * @param int|null $post_id Post to forget, or null for every post.
 * @return void
 */
function yaml_cf_flush_resolved_post_fields($post_id = null) {
  \YamlCF\Core\Plugin::getInstance()->get('field_resolver')->flush($post_id);
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
  return \YamlCF\Core\Plugin::getInstance()->get('field_resolver')->decodeAll($data, $schema);
}

/**
 * Check if a field has a value
 *
 * A field left empty or cleared in the editor ('' or an empty list) has none.
 * An unticked box (false) and 0 are values.
 *
 * @param string $field_name The name of the field to check
 * @param int|string $post_id Optional. Post ID or 'partial:filename' for partials. Defaults to current post.
 * @return bool True if the field has a value, false otherwise
 */
function yaml_cf_has_field($field_name, $post_id = null) {
  return \YamlCF\Core\Plugin::getInstance()->get('field_accessor')->hasField($field_name, $post_id);
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
  return \YamlCF\Core\Plugin::getInstance()->get('global_field_accessor')->getField($field_name);
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
  return \YamlCF\Core\Plugin::getInstance()->get('global_field_accessor')->getFields();
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
  return \YamlCF\Core\Plugin::getInstance()->get('global_field_accessor')->hasField($field_name);
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

// The Documentation page and the readme have always shown these three, but
// until now they did not exist: following the docs was a fatal error.
if (!function_exists('ycf_get_global_field')) {
  /**
   * Alias for yaml_cf_get_global_field()
   */
  function ycf_get_global_field($field_name) {
    return yaml_cf_get_global_field($field_name);
  }
}

if (!function_exists('ycf_get_global_fields')) {
  /**
   * Alias for yaml_cf_get_global_fields()
   */
  function ycf_get_global_fields() {
    return yaml_cf_get_global_fields();
  }
}

if (!function_exists('ycf_has_global_field')) {
  /**
   * Alias for yaml_cf_has_global_field()
   */
  function ycf_has_global_field($field_name) {
    return yaml_cf_has_global_field($field_name);
  }
}

/**
 * Get image data for an image field
 * Returns an array with image information
 *
 * @param string $field_name The name of the image field
 * @param int|string|null $post_id Optional. Post ID or 'partial:filename' for partials. Defaults to current post.
 * @param string|int[] $size Optional. Image size name (thumbnail, medium, large, full) or [width, height]. Defaults to 'full'.
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
  return \YamlCF\Core\Plugin::getInstance()->get('image_accessor')->getImage($field_name, $post_id, $size, $context_data);
}

if (!function_exists('yaml_cf_resolve_image_data')) {
  /**
   * Build the image-info array for a single attachment ID
   *
   * @param int|string $attachment_id Attachment ID
   * @param string|int[] $size Image size name, or [width, height]
   * @return array|null Image data, or null when the ID is not an attachment
   */
  function yaml_cf_resolve_image_data($attachment_id, $size = 'full') {
    return \YamlCF\Core\Plugin::getInstance()->get('image_accessor')->resolveImageData($attachment_id, $size);
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
  return \YamlCF\Core\Plugin::getInstance()->get('file_accessor')->getFile($field_name, $post_id, $context_data);
}

if (!function_exists('yaml_cf_resolve_file_data')) {
  /**
   * Build the file-info array for a single attachment ID
   *
   * @param mixed $attachment_id Attachment ID
   * @return array|null Array with 'id', 'url', 'path', 'filename', 'filesize', 'mime_type', 'title' keys or null if not found
   */
  function yaml_cf_resolve_file_data($attachment_id) {
    return \YamlCF\Core\Plugin::getInstance()->get('file_accessor')->resolveFileData($attachment_id);
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
  return \YamlCF\Core\Plugin::getInstance()->get('taxonomy_accessor')->getTerm($field_name, $post_id, $context_data);
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
  return \YamlCF\Core\Plugin::getInstance()->get('post_type_accessor')->getPostType($field_name, $post_id, $context_data);
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
  return \YamlCF\Core\Plugin::getInstance()->get('data_object_accessor')->getDataObject($field_name, $post_id, $context_data);
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
  return \YamlCF\Core\Plugin::getInstance()->get('data_object_accessor')->getDataObjects($object_type);
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
