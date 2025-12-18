<?php
namespace YamlCF\ImportExport;

/**
 * Exports data object types and entries
 */
class DataObjectExporter {
  private $attachmentValidator;

  public function __construct($attachmentValidator) {
    $this->attachmentValidator = $attachmentValidator;
  }

  /**
   * Export all data object types and their entries
   *
   * @return array Export data
   */
  public function export() {
    // TODO: Phase 14 - Implement DataObjectExporter
    $export_data = [
      'plugin' => 'yaml-custom-fields',
      'type' => 'data_objects',
      'version' => YAML_CF_VERSION,
      'site_url' => get_site_url(),
      'exported_at' => current_time('mysql'),
      'types' => []
    ];

    return $export_data;
  }

  /**
   * Generate download headers and output JSON
   *
   * @param array $data Export data
   */
  public function download($data) {
    // TODO: Phase 14 - Implement download method
    $filename = 'yaml-cf-data-objects-' . sanitize_file_name(wp_parse_url(get_site_url(), PHP_URL_HOST)) . '-' . gmdate('Y-m-d-His') . '.json';
    nocache_headers();
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Type: application/json; charset=utf-8');

    echo wp_json_encode($data, JSON_PRETTY_PRINT);
  }
}
