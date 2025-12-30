<?php

namespace YamlCF\Admin\Controllers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use YamlCF\Helpers\RequestHelper;

/**
 * Controller for managing global data
 */
class GlobalDataController extends AdminController {
  private $schemaStorage;

  public function __construct($schemaStorage) {
    $this->schemaStorage = $schemaStorage;
  }

  public function render() {
    $this->checkPermission();

    $global_schema_yaml = get_option('yaml_cf_global_schema', '');
    $global_schema = $this->schemaStorage->parseSchema($global_schema_yaml);

    if (!$global_schema || !isset($global_schema['fields'])) {
      wp_safe_redirect(admin_url('admin.php?page=yaml-cf-edit-global-schema'));
      exit;
    }

    $global_data = get_option('yaml_cf_global_data', []);

    // Check for success message
    $success_message = '';
    if (RequestHelper::getParam('saved') === '1') {
      $success_message = __('Global data saved successfully!', 'yaml-custom-fields');
    }

    // Collect taxonomy terms and post types from schema
    $taxonomy_terms = [];
    $post_types = [];
    $data_objects = [];

    if ($global_schema && isset($global_schema['fields'])) {
      $taxonomy_terms = $this->collectTaxonomyTerms($global_schema['fields']);
      $post_types = $this->collectPostTypes();
      $data_objects = $this->collectDataObjects($global_schema['fields']);
    }

    // Localize schema data, taxonomy terms, post types, and data objects for JavaScript
    $this->localizeScript([
      'schema' => $global_schema,
      'taxonomyTerms' => $taxonomy_terms,
      'postTypes' => $post_types,
      'dataObjects' => $data_objects
    ]);

    // Pass messages and form tracking config to JavaScript
    $page_config = [];
    if ($success_message) {
      $page_config['successMessage'] = $success_message;
    }
    $page_config['formTracking'] = [
      'enabled' => true,
      'container' => '#yaml-cf-global-data-form',
      'fieldsSelector' => '.yaml-cf-fields',
      'message' => __('You have unsaved changes', 'yaml-custom-fields'),
      'submitSelector' => '#yaml-cf-global-data-form',
      'storageKey' => 'originalGlobalDataFormData',
      'hasChangesKey' => 'hasGlobalDataFormChanges',
      'beforeUnloadMessage' => __('You have unsaved changes. Are you sure?', 'yaml-custom-fields'),
      'gutenbergSupport' => false,
      'captureDelay' => 500
    ];
    $this->localizePageInit($page_config);

    // Load template
    $this->loadTemplate('manage-global-data-page.php', compact(
      'global_schema',
      'global_data'
    ));
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

  /**
   * Collect all public post types
   *
   * @return array Array of post types with name and label
   */
  private function collectPostTypes() {
    $post_types = get_post_types(['public' => true], 'objects');
    $result = [];

    foreach ($post_types as $post_type) {
      $result[] = [
        'name' => $post_type->name,
        'label' => $post_type->label
      ];
    }

    return $result;
  }

  /**
   * Recursively collect data object entries for all data_object fields in the schema
   *
   * @param array $fields Schema fields array
   * @return array Associative array of object_type => entries
   */
  private function collectDataObjects($fields) {
    $data_objects = [];

    foreach ($fields as $field) {
      // Check if this is a data_object field
      if (isset($field['type']) && $field['type'] === 'data_object') {
        $object_type = isset($field['options']['object_type']) ? $field['options']['object_type'] : '';

        if (!empty($object_type) && !isset($data_objects[$object_type])) {
          // Get entries for this object type
          $entries = get_option('yaml_cf_data_object_entries_' . $object_type, []);
          $data_types = get_option('yaml_cf_data_object_types', []);

          if (isset($data_types[$object_type]) && !empty($entries)) {
            // Get the schema to determine which field to use as label
            $object_schema_yaml = $data_types[$object_type]['schema'];
            $object_schema = $this->schemaStorage->parseSchema($object_schema_yaml);
            $label_field = '';

            if (!empty($object_schema['fields'])) {
              // Use first field as label
              $label_field = $object_schema['fields'][0]['name'];
            }

            // Format entries for JavaScript
            $formatted_entries = [];
            foreach ($entries as $entry_id => $entry_data) {
              $entry_label = isset($entry_data[$label_field]) ? $entry_data[$label_field] : $entry_id;
              if (is_array($entry_label)) {
                $entry_label = $entry_id; // Fallback for complex data
              }

              $formatted_entries[] = [
                'id' => $entry_id,
                'label' => $entry_label
              ];
            }

            $data_objects[$object_type] = $formatted_entries;
          }
        }
      }

      // Recursively check block fields
      if (isset($field['type']) && $field['type'] === 'block' && isset($field['blocks'])) {
        foreach ($field['blocks'] as $block) {
          if (isset($block['fields'])) {
            $block_objects = $this->collectDataObjects($block['fields']);
            $data_objects = array_merge($data_objects, $block_objects);
          }
        }
      }

      // Recursively check object fields
      if (isset($field['type']) && $field['type'] === 'object' && isset($field['fields'])) {
        $object_data = $this->collectDataObjects($field['fields']);
        $data_objects = array_merge($data_objects, $object_data);
      }
    }

    return $data_objects;
  }
}
