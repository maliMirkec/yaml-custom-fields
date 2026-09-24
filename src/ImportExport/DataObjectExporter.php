<?php

namespace YamlCF\ImportExport;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Exports data object types and their entries
 */
class DataObjectExporter {
  private $attachmentValidator;
  private $parser;

  public function __construct($attachmentValidator = null, $parser = null) {
    $this->attachmentValidator = $attachmentValidator ?: new \YamlCF\Form\AttachmentValidator();
    $this->parser = $parser ?: new \YamlCF\Schema\SchemaParser();
  }

  /**
   * Build the data objects export (attachment IDs that no longer exist are cleared)
   *
   * @return array Export data
   */
  public function export() {
    $export_data = [
      'plugin' => 'yaml-custom-fields',
      'type' => 'data_objects',
      'version' => YAML_CF_VERSION,
      'site_url' => get_site_url(),
      'exported_at' => current_time('mysql'),
      'types' => [],
    ];

    $types = get_option('yaml_cf_data_object_types', []);
    if (!is_array($types)) {
      return $export_data;
    }

    foreach ($types as $type_slug => $type_data) {
      if (!is_array($type_data)) {
        continue;
      }
      $schema_yaml = isset($type_data['schema']) ? $type_data['schema'] : '';
      $schema = is_string($schema_yaml) && $schema_yaml !== '' ? $this->parser->parse($schema_yaml) : null;
      $schema = is_array($schema) ? $schema : null;

      $entries = $this->repositoryForEntries()->getEntries($type_slug);
      $cleaned_entries = [];
      foreach ((array) $entries as $entry_id => $entry_data) {
        $cleaned_entries[$entry_id] = $this->attachmentValidator->validateAndClean($entry_data, $schema);
      }

      $export_data['types'][$type_slug] = [
        'name' => isset($type_data['name']) ? $type_data['name'] : $type_slug,
        'schema' => $schema_yaml,
        'entries' => $cleaned_entries,
      ];
    }

    return $export_data;
  }

  /**
   * Send export data as a JSON file download
   *
   * @param array $data Export data
   */
  public function download($data) {
    $filename = 'yaml-cf-data-objects-' . sanitize_file_name(wp_parse_url(get_site_url(), PHP_URL_HOST)) . '-' . gmdate('Y-m-d-His') . '.json';
    JsonDownload::send($data, $filename);
  }

  /**
   * Where a collection's items are read from
   *
   * @return \YamlCF\Data\Repositories\DataObjectRepository
   */
  private function repositoryForEntries() {
    return new \YamlCF\Data\Repositories\DataObjectRepository();
  }
}
