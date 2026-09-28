<?php

namespace YamlCF\ImportExport;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Imports data object types and their entries
 *
 * Type schemas are validated, slugs and entry IDs sanitized, and entries sanitized
 * against the imported (validated) schema.
 */
class DataObjectImporter {
  private $attachmentValidator;
  private $sanitizer;
  private $validator;
  private $parser;

  public function __construct($attachmentValidator = null, $sanitizer = null, $validator = null, $parser = null) {
    $this->attachmentValidator = $attachmentValidator ?: new \YamlCF\Form\AttachmentValidator();
    // Values here come out of an import file, never from this user's own typing
    $this->sanitizer = ($sanitizer ?: new \YamlCF\Form\DataSanitizer())->forUntrustedSource();
    $this->validator = $validator ?: new \YamlCF\Schema\SchemaValidator();
    $this->parser = $parser ?: new \YamlCF\Schema\SchemaParser();
  }

  /**
   * Validate the structure of a data objects import
   *
   * @param mixed $data Decoded import file
   * @return array ['valid' => bool, 'message' => string]
   */
  public function validate($data) {
    if (!is_array($data) || !isset($data['plugin']) || $data['plugin'] !== 'yaml-custom-fields') {
      return ['valid' => false, 'message' => __('This is not a YAML Custom Fields export file.', 'yaml-custom-fields')];
    }
    if (!isset($data['type']) || $data['type'] !== 'data_objects') {
      return ['valid' => false, 'message' => __('This file does not contain collections.', 'yaml-custom-fields')];
    }
    if (!isset($data['types']) || !is_array($data['types'])) {
      return ['valid' => false, 'message' => __('The file contains no collections.', 'yaml-custom-fields')];
    }
    return ['valid' => true, 'message' => ''];
  }

  /**
   * Import data object types and entries (existing types with the same slug are replaced)
   *
   * @param array $data Decoded import file
   * @return array Result with 'success', 'message', 'types_imported', 'entries_imported', 'errors', 'exported_at'
   */
  public function import($data) {
    $validation = $this->validate($data);
    if (!$validation['valid']) {
      return [
        'success' => false,
        'message' => $validation['message'],
        'types_imported' => 0,
        'entries_imported' => 0,
        'errors' => [],
      ];
    }

    $types_imported = 0;
    $entries_imported = 0;
    $errors = [];
    $existing_types = get_option('yaml_cf_data_object_types', []);
    if (!is_array($existing_types)) {
      $existing_types = [];
    }

    foreach ($data['types'] as $raw_type_slug => $type_data) {
      $type_slug = sanitize_key($raw_type_slug);

      if ($type_slug === '' || !is_array($type_data) || !isset($type_data['name']) || !isset($type_data['schema']) || !is_string($type_data['schema'])) {
        /* translators: %s: data object type slug */
        $errors[] = sprintf(__('Skipped "%s": the list is incomplete.', 'yaml-custom-fields'), sanitize_text_field($raw_type_slug));
        continue;
      }

      if (strlen($type_slug) > \YamlCF\Data\EntryStore::MAX_SLUG_LENGTH) {
        /* translators: 1: data object type slug, 2: most characters a short name may have */
        $errors[] = sprintf(__('Skipped the list "%1$s": its short name is longer than %2$d characters.', 'yaml-custom-fields'), $type_slug, \YamlCF\Data\EntryStore::MAX_SLUG_LENGTH);
        continue;
      }

      $schema_yaml = \YamlCF\Schema\SchemaParser::sanitizeSource($type_data['schema']);
      // Not strict: a field with a type this version does not know is reported,
      // but the list and its entries still come across.
      $validation_result = $this->validator->validate($schema_yaml, null, false);
      if (!$validation_result['valid']) {
        /* translators: 1: data object type slug, 2: schema validation error */
        $errors[] = sprintf(__('Skipped the list "%1$s": %2$s', 'yaml-custom-fields'), $type_slug, $validation_result['message']);
        continue;
      }
      if (!empty($validation_result['warnings']) && is_array($validation_result['warnings'])) {
        foreach ($validation_result['warnings'] as $warning) {
          /* translators: 1: data object type slug, 2: the problem found in the schema */
          $errors[] = sprintf(__('The list "%1$s" was imported, but %2$s', 'yaml-custom-fields'), $type_slug, $warning);
        }
      }

      $existing_types[$type_slug] = [
        'name' => sanitize_text_field($type_data['name']),
        'schema' => $schema_yaml,
      ];

      if (isset($type_data['entries']) && is_array($type_data['entries'])) {
        $schema = $this->parser->parse($schema_yaml);
        $schema = is_array($schema) ? $schema : null;
        $cleaned_entries = [];
        foreach ($type_data['entries'] as $entry_id => $entry_data) {
          $entry_id = sanitize_text_field($entry_id);
          if ($entry_id === '' || !is_array($entry_data)) {
            continue;
          }
          $entry_data = $this->attachmentValidator->validateAndClean($entry_data, $schema);
          $cleaned_entries[$entry_id] = $this->sanitizer->sanitize($entry_data, $schema);
          $entries_imported++;
        }
        // The collection's first field labels an item, the same rule the admin
        // list and the pickers use. Without it the label falls back to whatever
        // the entry's first value happens to be, which is the file's key order
        // rather than the schema's.
        (new \YamlCF\Data\Repositories\DataObjectRepository())->replaceEntries(
          $type_slug,
          $cleaned_entries,
          !empty($schema['fields'][0]['name']) ? (string) $schema['fields'][0]['name'] : ''
        );
      }

      $types_imported++;
    }

    update_option('yaml_cf_data_object_types', $existing_types, false);

    return [
      'success' => true,
      /* translators: 1: number of imported types, 2: number of imported entries */
      'message' => sprintf(__('Import done. %1$d lists with %2$d items were added.', 'yaml-custom-fields'), $types_imported, $entries_imported),
      'types_imported' => $types_imported,
      'entries_imported' => $entries_imported,
      'errors' => $errors,
      'exported_at' => isset($data['exported_at']) && is_string($data['exported_at']) ? sanitize_text_field($data['exported_at']) : 'unknown',
    ];
  }
}
