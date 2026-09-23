<?php

namespace YamlCF\ImportExport;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Builds one export file from a chosen set of items
 *
 * The file keeps the same 'settings', 'posts' and 'types' keys the older,
 * separate exports used, so a bundle is readable by the existing importers and
 * an old file is still readable here.
 */
class BundleExporter {
  private $pageDataExporter;

  public function __construct($pageDataExporter = null) {
    $this->pageDataExporter = $pageDataExporter ?: new PageDataExporter();
  }

  /**
   * Build the export
   *
   * @param array $selection Item ids chosen in the wizard
   * @param array $post_ids Post IDs chosen in the post table
   * @param string $match_by 'slug' or 'id'
   * @return array Export data
   */
  public function export($selection, $post_ids = [], $match_by = 'slug') {
    $selection = array_flip(array_map('strval', (array) $selection));

    $bundle = [
      'plugin' => 'yaml-custom-fields',
      'type' => 'bundle',
      'format' => 2,
      'version' => YAML_CF_VERSION,
      'site_url' => get_site_url(),
      'exported_at' => current_time('mysql'),
    ];

    $settings = $this->collectSettings($selection);
    if (!empty($settings)) {
      $bundle['settings'] = $settings;
    }

    $post_ids = array_filter(array_map('absint', (array) $post_ids));
    if (!empty($post_ids)) {
      $posts = $this->pageDataExporter->exportMultiple($post_ids, $match_by);
      if (isset($posts['posts']) && is_array($posts['posts'])) {
        $bundle['posts'] = $posts['posts'];
        $bundle['match_by'] = isset($posts['match_by']) ? $posts['match_by'] : $match_by;
      }
    }

    $types = $this->collectTypes($selection);
    if (!empty($types)) {
      $bundle['types'] = $types;
    }

    return $bundle;
  }

  /**
   * Send the export as a JSON file download
   *
   * @param array $data Export data
   * @param string|null $filename Download file name
   */
  public function download($data, $filename = null) {
    if (!$filename) {
      $host = sanitize_file_name((string) wp_parse_url(get_site_url(), PHP_URL_HOST));
      $filename = 'yaml-cf-' . $host . '-' . gmdate('Y-m-d-His') . '.json';
    }
    JsonDownload::send($data, $filename);
  }

  /**
   * Gather the selected setup pieces
   *
   * @param array $selection Item ids as an isset() lookup
   * @return array Settings array holding only what was chosen
   */
  private function collectSettings($selection) {
    $settings = [];

    $maps = [
      'schema:' => ['option' => 'yaml_cf_schemas', 'key' => 'schemas'],
      'tglobal_schema:' => ['option' => 'yaml_cf_template_global_schemas', 'key' => 'template_global_schemas'],
      'tglobal_data:' => ['option' => 'yaml_cf_template_global_data', 'key' => 'template_global_data'],
      'partial_data:' => ['option' => 'yaml_cf_partial_data', 'key' => 'partial_data'],
    ];

    foreach ($maps as $prefix => $target) {
      $stored = get_option($target['option'], []);
      if (!is_array($stored)) {
        continue;
      }
      foreach ($stored as $template => $value) {
        if (isset($selection[$prefix . $template])) {
          $settings[$target['key']][$template] = $value;
        }
      }
    }

    if (isset($selection['global_schema'])) {
      $settings['global_schema'] = get_option('yaml_cf_global_schema', '');
    }
    if (isset($selection['global_data'])) {
      $settings['global_data'] = get_option('yaml_cf_global_data', []);
    }
    if (isset($selection['template_settings'])) {
      $settings['template_settings'] = get_option('yaml_cf_template_settings', []);
    }

    return $settings;
  }

  /**
   * Gather the selected collections with their items
   *
   * @param array $selection Item ids as an isset() lookup
   * @return array Types keyed by slug
   */
  private function collectTypes($selection) {
    $stored = get_option('yaml_cf_data_object_types', []);
    if (!is_array($stored)) {
      return [];
    }

    $types = [];
    foreach ($stored as $slug => $type) {
      if (!isset($selection['list:' . $slug]) || !is_array($type)) {
        continue;
      }

      $types[$slug] = [
        'name' => isset($type['name']) ? $type['name'] : $slug,
        'schema' => isset($type['schema']) ? $type['schema'] : '',
        'entries' => (array) get_option('yaml_cf_data_object_entries_' . $slug, []),
      ];
    }

    return $types;
  }
}
