<?php

namespace YamlCF\ImportExport;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Exports plugin settings (schemas, template settings, partial and global data)
 */
class SettingsExporter {
  /**
   * Build the settings export
   *
   * The format is unchanged from earlier versions so old export files stay importable.
   *
   * @return array Export data
   */
  public function export() {
    return [
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
        'global_data' => get_option('yaml_cf_global_data', []),
      ],
    ];
  }

  /**
   * Send export data as a JSON file download
   *
   * @param array $data Export data
   * @param string|null $filename Download file name
   */
  public function download($data, $filename = null) {
    if (!$filename) {
      $filename = 'yaml-cs-schema-' . gmdate('Y-m-d-H-i-s') . '.json';
    }
    JsonDownload::send($data, $filename);
  }
}
