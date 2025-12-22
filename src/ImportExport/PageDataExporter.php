<?php

namespace YamlCF\ImportExport;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Exports page/post custom field data
 */
class PageDataExporter {
  /**
   * Export data for a single post
   *
   * @param int $post_id Post ID
   * @return array Export data
   */
  public function exportPost($post_id) {
    // TODO: Phase 14 - Implement single post export
    $export_data = [
      'plugin' => 'yaml-custom-fields',
      'type' => 'page_data',
      'version' => YAML_CF_VERSION,
      'site_url' => get_site_url(),
      'exported_at' => current_time('mysql'),
      'post' => []
    ];

    return $export_data;
  }

  /**
   * Export data for multiple posts
   *
   * @param array $post_ids Array of post IDs
   * @return array Export data
   */
  public function exportMultiple($post_ids) {
    // TODO: Phase 14 - Implement multiple post export
    $export_data = [
      'plugin' => 'yaml-custom-fields',
      'type' => 'page_data',
      'version' => YAML_CF_VERSION,
      'site_url' => get_site_url(),
      'exported_at' => current_time('mysql'),
      'posts' => []
    ];

    return $export_data;
  }

  /**
   * Generate download headers and output JSON
   *
   * @param array $data Export data
   * @param string $filename Custom filename (optional)
   */
  public function download($data, $filename = null) {
    // TODO: Phase 14 - Implement download method
    if (!$filename) {
      $filename = 'yaml-cf-page-data-' . sanitize_file_name(wp_parse_url(get_site_url(), PHP_URL_HOST)) . '-' . gmdate('Y-m-d-His') . '.json';
    }

    nocache_headers();
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Type: application/json; charset=utf-8');

    echo wp_json_encode($data, JSON_PRETTY_PRINT);
  }
}
