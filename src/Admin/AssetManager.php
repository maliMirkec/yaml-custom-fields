<?php

namespace YamlCF\Admin;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use YamlCF\Helpers\RequestHelper;

/**
 * Manages admin asset enqueuing (CSS, JavaScript)
 */
class AssetManager {
  private $templateResolver;
  private $schemaParser;
  private $page_config = [];

  public function __construct($templateResolver, $schemaParser) {
    $this->templateResolver = $templateResolver;
    $this->schemaParser = $schemaParser;
  }

  /**
   * Set page-specific configuration for JavaScript
   *
   * @param array $config Configuration data to pass to JavaScript
   */
  public function setPageConfig($config) {
    $this->page_config = $config;
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
      $is_post_edit = in_array($current_screen->base, ['post', 'post-new'], true) &&
                      $post_type_object && $post_type_object->public;
    }

    // Only load if on plugin pages or post edit screen
    if (!$is_plugin_page && !$is_post_edit) {
      return;
    }

    // Enqueue WordPress media library (needed for image/file uploads)
    wp_enqueue_media();

    wp_enqueue_style('yaml-cf-admin', YAML_CF_PLUGIN_URL . 'admin-assets/admin.css', [], YAML_CF_VERSION);
    // jquery-ui-sortable: reordering a gallery (image field with list: true) by dragging
    wp_enqueue_script('yaml-cf-admin', YAML_CF_PLUGIN_URL . 'admin-assets/admin.js', ['jquery', 'jquery-ui-sortable', 'wp-i18n'], YAML_CF_VERSION, true);
    $this->setScriptTranslations('yaml-cf-admin');

    // Enqueue page initialization script (replaces inline scripts in templates)
    wp_enqueue_script(
      'yaml-cf-admin-page-init',
      YAML_CF_PLUGIN_URL . 'admin-assets/admin-page-init.js',
      ['jquery', 'wp-i18n', 'yaml-cf-admin'],
      YAML_CF_VERSION,
      true
    );
    $this->setScriptTranslations('yaml-cf-admin-page-init');

    // Get current template and schema for post edit screens
    $schema_data = null;
    // Which schema this screen is editing, so that adding a section can ask the
    // server to render it (see YamlCF\Ajax\Handlers\RenderBlockItem)
    $schema_source = null;
    $post_id = RequestHelper::getParamInt('post', 0);

    if ($is_post_edit && $post_id) {
      $post = get_post($post_id);

      if ($post) {
        $template = $this->templateResolver->resolveForPost($post);

        $schemas = get_option('yaml_cf_schemas', []);

        if (isset($schemas[$template]) && !empty($schemas[$template])) {
          $schema_source = ['source' => 'template', 'key' => $template];
          $schema_data = $this->schemaParser->parse($schemas[$template]);
        }
      }
    }

    // The plugin's own screens render fields too, each from a different schema
    if ($is_plugin_page && $schema_source === null) {
      $current_page = RequestHelper::getParam('page');

      // Get schema based on which admin page we're on
      $admin_schema = null;

      if ($current_page === 'yaml-cf-manage-global-data') {
        $global_schema_yaml = get_option('yaml_cf_global_schema', '');
        if (!empty($global_schema_yaml)) {
          $admin_schema = $this->schemaParser->parse($global_schema_yaml);
          $schema_source = ['source' => 'global', 'key' => ''];
        }
      } elseif ($current_page === 'yaml-cf-manage-template-global-data') {
        $template = RequestHelper::getParam('template');
        if ($template) {
          $template_global_schemas = get_option('yaml_cf_template_global_schemas', []);
          if (isset($template_global_schemas[$template])) {
            $admin_schema = $this->schemaParser->parse($template_global_schemas[$template]);
            $schema_source = ['source' => 'template_global', 'key' => $template];
          }
        }
      } elseif ($current_page === 'yaml-cf-edit-partial') {
        $template = RequestHelper::getParam('template');
        if ($template) {
          $schemas = get_option('yaml_cf_schemas', []);
          if (isset($schemas[$template])) {
            $admin_schema = $this->schemaParser->parse($schemas[$template]);
            $schema_source = ['source' => 'partial', 'key' => $template];
          }
        }
      }
    }

    wp_localize_script('yaml-cf-admin', 'yamlCF', [
      'ajax_url' => admin_url('admin-ajax.php'),
      'admin_url' => admin_url(),
      'nonce' => wp_create_nonce('yaml_cf_nonce'),
      'schema' => $schema_data,
      'schemaSource' => $schema_source,
    ]);

    // Page-specific conditional enqueuing
    $current_page = RequestHelper::getParam('page');

    if ($current_page === 'yaml-cf-data-validation') {
      wp_enqueue_script(
        'yaml-cf-validation',
        YAML_CF_PLUGIN_URL . 'admin-assets/admin-validation.js',
        ['jquery', 'wp-i18n'],
        YAML_CF_VERSION,
        true
      );
      $this->setScriptTranslations('yaml-cf-validation');
    }

    if ($current_page === 'yaml-cf-export-data') {
      wp_enqueue_script(
        'yaml-cf-export-import',
        YAML_CF_PLUGIN_URL . 'admin-assets/admin-export-import.js',
        ['jquery', 'wp-i18n', 'yaml-cf-admin'],
        YAML_CF_VERSION,
        true
      );
      $this->setScriptTranslations('yaml-cf-export-import');
    }

    // Localize page config if set by controller
    if (!empty($this->page_config)) {
      wp_localize_script('yaml-cf-admin-page-init', 'yamlCFPageInit', $this->page_config);
    }
  }

  /**
   * Load JavaScript translations (wp.i18n) for a script handle
   * Uses language packs from translate.wordpress.org, or JSON files in /languages for local development.
   *
   * @param string $handle Script handle
   */
  private function setScriptTranslations($handle) {
    wp_set_script_translations($handle, 'yaml-custom-fields', YAML_CF_PLUGIN_DIR . 'languages');
  }
}
