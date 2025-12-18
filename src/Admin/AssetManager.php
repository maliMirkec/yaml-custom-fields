<?php
namespace YamlCF\Admin;

use YamlCF\Helpers\RequestHelper;

/**
 * Manages admin asset enqueuing (CSS, JavaScript)
 */
class AssetManager {
  private $templateResolver;
  private $schemaParser;

  public function __construct($templateResolver, $schemaParser) {
    $this->templateResolver = $templateResolver;
    $this->schemaParser = $schemaParser;
  }

  /**
   * Enqueue admin assets
   */
  public function enqueueAssets($hook) {
    // Check if we're on any YAML Custom Fields admin page
    $is_plugin_page = (strpos($hook, 'yaml-cf') !== false || $hook === 'toplevel_page_yaml-custom-fields');

    // Load on post edit screens
    $current_screen = get_current_screen();
    $is_post_edit = false;

    if ($current_screen) {
      // Check if editing any public post type
      $post_type_object = get_post_type_object($current_screen->post_type);
      $is_post_edit = in_array($current_screen->base, ['post', 'post-new']) &&
                      $post_type_object && $post_type_object->public;
    }

    // Only load if on plugin pages or post edit screen
    if (!$is_plugin_page && !$is_post_edit) {
      return;
    }

    // Enqueue WordPress media library (needed for image/file uploads)
    wp_enqueue_media();

    wp_enqueue_style('yaml-cf-admin', YAML_CF_PLUGIN_URL . 'assets/admin.css', [], YAML_CF_VERSION);
    wp_enqueue_script('yaml-cf-admin', YAML_CF_PLUGIN_URL . 'assets/admin.js', ['jquery'], YAML_CF_VERSION, true);

    // Get current template and schema for post edit screens
    $schema_data = null;
    $post_id = RequestHelper::getParamInt('post', 0);
    if ($is_post_edit && $post_id) {
      $post = get_post($post_id);
      if ($post) {
        $template = $this->templateResolver->resolveForPost($post);

        $schemas = get_option('yaml_cf_schemas', []);
        if (isset($schemas[$template]) && !empty($schemas[$template])) {
          $schema_data = $this->schemaParser->parse($schemas[$template]);
        }
      }
    }

    wp_localize_script('yaml-cf-admin', 'yamlCF', [
      'ajax_url' => admin_url('admin-ajax.php'),
      'admin_url' => admin_url(),
      'nonce' => wp_create_nonce('yaml_cf_nonce'),
      'schema' => $schema_data
    ]);
  }
}
