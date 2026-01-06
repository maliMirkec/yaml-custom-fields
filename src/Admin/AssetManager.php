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
      $is_post_edit = in_array($current_screen->base, ['post', 'post-new']) &&
                      $post_type_object && $post_type_object->public;
    }

    // Only load if on plugin pages or post edit screen
    if (!$is_plugin_page && !$is_post_edit) {
      return;
    }

    // Enqueue WordPress media library (needed for image/file uploads)
    wp_enqueue_media();

    wp_enqueue_style('yaml-cf-admin', YAML_CF_PLUGIN_URL . 'admin-assets/admin.css', [], YAML_CF_VERSION);
    wp_enqueue_script('yaml-cf-admin', YAML_CF_PLUGIN_URL . 'admin-assets/admin.js', ['jquery'], YAML_CF_VERSION, true);

    // Enqueue page initialization script (replaces inline scripts in templates)
    wp_enqueue_script(
      'yaml-cf-admin-page-init',
      YAML_CF_PLUGIN_URL . 'admin-assets/admin-page-init.js',
      ['jquery', 'yaml-cf-admin'],
      YAML_CF_VERSION,
      true
    );

    // Get current template and schema for post edit screens
    $schema_data = null;
    $taxonomy_terms = [];
    $post_id = RequestHelper::getParamInt('post', 0);

    if ($is_post_edit && $post_id) {
      $post = get_post($post_id);

      if ($post) {
        $template = $this->templateResolver->resolveForPost($post);

        $schemas = get_option('yaml_cf_schemas', []);

        if (isset($schemas[$template]) && !empty($schemas[$template])) {
          $schema_data = $this->schemaParser->parse($schemas[$template]);

          // Collect taxonomy terms for all taxonomy fields in the schema
          if ($schema_data && isset($schema_data['fields'])) {
            $taxonomy_terms = $this->collectTaxonomyTerms($schema_data['fields']);
          }
        }
      }
    }

    // Also collect taxonomy terms for YAML CF admin pages
    if ($is_plugin_page && empty($taxonomy_terms)) {
      $current_page = RequestHelper::getParam('page');

      // Get schema based on which admin page we're on
      $admin_schema = null;

      if ($current_page === 'yaml-cf-manage-global-data') {
        $global_schema_yaml = get_option('yaml_cf_global_schema', '');
        if (!empty($global_schema_yaml)) {
          $admin_schema = $this->schemaParser->parse($global_schema_yaml);
        }
      } elseif ($current_page === 'yaml-cf-manage-template-global-data') {
        $template = RequestHelper::getParam('template');
        if ($template) {
          $template_global_schemas = get_option('yaml_cf_template_global_schemas', []);
          if (isset($template_global_schemas[$template])) {
            $admin_schema = $this->schemaParser->parse($template_global_schemas[$template]);
          }
        }
      } elseif ($current_page === 'yaml-cf-edit-partial') {
        $template = RequestHelper::getParam('template');
        if ($template) {
          $schemas = get_option('yaml_cf_schemas', []);
          if (isset($schemas[$template])) {
            $admin_schema = $this->schemaParser->parse($schemas[$template]);
          }
        }
      }

      if ($admin_schema && isset($admin_schema['fields'])) {
        $taxonomy_terms = $this->collectTaxonomyTerms($admin_schema['fields']);
      }
    }

    wp_localize_script('yaml-cf-admin', 'yamlCF', [
      'ajax_url' => admin_url('admin-ajax.php'),
      'admin_url' => admin_url(),
      'nonce' => wp_create_nonce('yaml_cf_nonce'),
      'schema' => $schema_data,
      'taxonomyTerms' => $taxonomy_terms
    ]);

    // Page-specific conditional enqueuing
    $current_page = RequestHelper::getParam('page');

    if ($current_page === 'yaml-cf-data-validation') {
      wp_enqueue_script(
        'yaml-cf-validation',
        YAML_CF_PLUGIN_URL . 'admin-assets/admin-validation.js',
        ['jquery'],
        YAML_CF_VERSION,
        true
      );
    }

    if ($current_page === 'yaml-cf-export-data') {
      wp_enqueue_script(
        'yaml-cf-export-import',
        YAML_CF_PLUGIN_URL . 'admin-assets/admin-export-import.js',
        ['jquery', 'yaml-cf-admin'],
        YAML_CF_VERSION,
        true
      );
    }

    // Localize page config if set by controller
    if (!empty($this->page_config)) {
      wp_localize_script('yaml-cf-admin-page-init', 'yamlCFPageInit', $this->page_config);
    }
  }

  /**
   * Recursively collect taxonomy terms for all taxonomy fields in the schema
   *
   * @param array $fields Schema fields array
   * @return array Associative array of taxonomy => terms
   */
  private function collectTaxonomyTerms($fields) {
    $taxonomy_terms = [];

    foreach ($fields as $field) {
      // Check if this is a taxonomy field
      if (isset($field['type']) && $field['type'] === 'taxonomy') {
        $taxonomy = isset($field['options']['taxonomy']) ? $field['options']['taxonomy'] : 'category';

        // Fetch terms if not already fetched
        if (!isset($taxonomy_terms[$taxonomy])) {
          $terms = get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
          ]);

          if (!is_wp_error($terms) && !empty($terms)) {
            $taxonomy_terms[$taxonomy] = array_map(function($term) {
              return [
                'id' => $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug
              ];
            }, $terms);
          }
        }
      }

      // Recursively check block fields
      if (isset($field['type']) && $field['type'] === 'block' && isset($field['blocks'])) {
        foreach ($field['blocks'] as $block) {
          if (isset($block['fields'])) {
            $block_terms = $this->collectTaxonomyTerms($block['fields']);
            $taxonomy_terms = array_merge($taxonomy_terms, $block_terms);
          }
        }
      }

      // Recursively check object fields
      if (isset($field['type']) && $field['type'] === 'object' && isset($field['fields'])) {
        $object_terms = $this->collectTaxonomyTerms($field['fields']);
        $taxonomy_terms = array_merge($taxonomy_terms, $object_terms);
      }
    }

    return $taxonomy_terms;
  }
}
