<?php

namespace YamlCF\ImportExport;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Imports only the chosen items out of an export file
 *
 * Nothing new is validated or sanitized here. The file is cut down to what was
 * ticked and handed to the importers that already exist, so a partial import
 * goes through exactly the same checks a whole one does.
 *
 * Settings are always merged, never replaced: an item that was not ticked has
 * to survive on this site untouched.
 */
class BundleImporter {
  private $settingsImporter;
  private $pageDataImporter;
  private $dataObjectImporter;

  public function __construct($settingsImporter = null, $pageDataImporter = null, $dataObjectImporter = null) {
    $this->settingsImporter = $settingsImporter ?: new SettingsImporter();
    $this->pageDataImporter = $pageDataImporter ?: new PageDataImporter();
    $this->dataObjectImporter = $dataObjectImporter ?: new DataObjectImporter();
  }

  /**
   * Check that the file is one of ours and holds something importable
   *
   * @param mixed $data Decoded import file
   * @return array ['valid' => bool, 'message' => string]
   */
  public function validate($data) {
    if (!is_array($data) || !isset($data['plugin']) || $data['plugin'] !== 'yaml-custom-fields') {
      return ['valid' => false, 'message' => __('This is not a YAML Custom Fields export file.', 'yaml-custom-fields')];
    }

    $has_something = (isset($data['settings']) && is_array($data['settings']))
      || (isset($data['posts']) && is_array($data['posts']))
      || (isset($data['post']) && is_array($data['post']))
      || (isset($data['types']) && is_array($data['types']));

    if (!$has_something) {
      return ['valid' => false, 'message' => __('The file holds nothing that can be imported.', 'yaml-custom-fields')];
    }

    return ['valid' => true, 'message' => ''];
  }

  /**
   * Import the chosen items
   *
   * @param array $data Decoded import file
   * @param array $selection Item ids ticked in the preview
   * @return array ['success' => bool, 'message' => string, 'imported' => int, 'errors' => string[]]
   */
  public function import($data, $selection) {
    $validation = $this->validate($data);
    if (!$validation['valid']) {
      return ['success' => false, 'message' => $validation['message'], 'imported' => 0, 'errors' => []];
    }

    $selection = array_flip(array_map('strval', (array) $selection));
    if (empty($selection)) {
      return [
        'success' => false,
        'message' => __('Nothing was ticked, so nothing was imported.', 'yaml-custom-fields'),
        'imported' => 0,
        'errors' => [],
      ];
    }

    $errors = [];
    $imported = 0;

    $imported += $this->importSettings($data, $selection, $errors);
    $imported += $this->importPosts($data, $selection, $errors);
    $imported += $this->importTypes($data, $selection, $errors);

    return [
      'success' => $imported > 0 || empty($errors),
      'message' => $imported > 0
        /* translators: %d: number of imported items */
        ? sprintf(_n('%d item imported.', '%d items imported.', $imported, 'yaml-custom-fields'), $imported)
        : __('Nothing was imported.', 'yaml-custom-fields'),
      'imported' => $imported,
      'errors' => $errors,
    ];
  }

  /**
   * Import the ticked setup pieces
   *
   * @param array $data Decoded import file
   * @param array $selection Ticked ids as an isset() lookup
   * @param array $errors Collected errors, by reference
   * @return int Number of items imported
   */
  private function importSettings($data, $selection, &$errors) {
    if (!isset($data['settings']) || !is_array($data['settings'])) {
      return 0;
    }

    $source = $data['settings'];
    $filtered = [];
    $count = 0;

    $maps = [
      'schema:' => 'schemas',
      'tglobal_schema:' => 'template_global_schemas',
      'tglobal_data:' => 'template_global_data',
      'partial_data:' => 'partial_data',
    ];

    foreach ($maps as $prefix => $key) {
      if (!isset($source[$key]) || !is_array($source[$key])) {
        continue;
      }
      foreach ($source[$key] as $template => $value) {
        if (isset($selection[$prefix . $template])) {
          $filtered[$key][$template] = $value;
          $count++;
        }
      }
    }

    if (isset($selection['global_schema']) && isset($source['global_schema'])) {
      $filtered['global_schema'] = $source['global_schema'];
      $count++;
    }
    if (isset($selection['global_data']) && isset($source['global_data'])) {
      $filtered['global_data'] = $source['global_data'];
      $count++;
    }
    if (isset($selection['template_settings']) && isset($source['template_settings'])) {
      $filtered['template_settings'] = $source['template_settings'];
      $count++;
    }

    if (empty($filtered)) {
      return 0;
    }

    // Always merge: untouched settings on this site must survive
    $result = $this->settingsImporter->import([
      'plugin' => 'yaml-custom-fields',
      'settings' => $filtered,
    ], true);

    if (!empty($result['errors'])) {
      $errors = array_merge($errors, $result['errors']);
    }

    if (empty($result['success'])) {
      if (!empty($result['message'])) {
        $errors[] = $result['message'];
      }
      return 0;
    }

    // What the importer actually wrote, which is less than what was ticked when
    // a schema failed validation; older importers do not report it
    return isset($result['imported']) ? (int) $result['imported'] : $count;
  }

  /**
   * Import the ticked posts
   *
   * @param array $data Decoded import file
   * @param array $selection Ticked ids as an isset() lookup
   * @param array $errors Collected errors, by reference
   * @return int Number of posts imported
   */
  private function importPosts($data, $selection, &$errors) {
    $entries = [];
    if (isset($data['type']) && $data['type'] === 'single-post' && isset($data['post'])) {
      $entries = [$data['post']];
    } elseif (isset($data['posts']) && is_array($data['posts'])) {
      $entries = $data['posts'];
    }

    if (empty($entries)) {
      return 0;
    }

    $chosen = [];
    foreach ($entries as $index => $entry) {
      if (!is_array($entry)) {
        continue;
      }
      $key = (isset($entry['id']) && (int) $entry['id'] > 0) ? (string) (int) $entry['id'] : 'i' . $index;
      if (isset($selection['post:' . $key])) {
        $chosen[] = $entry;
      }
    }

    if (empty($chosen)) {
      return 0;
    }

    $result = $this->pageDataImporter->importMany([
      'plugin' => 'yaml-custom-fields',
      'match_by' => isset($data['match_by']) ? $data['match_by'] : 'slug',
      'posts' => $chosen,
    ]);

    if (!empty($result['errors'])) {
      $errors = array_merge($errors, (array) $result['errors']);
    }

    if (isset($result['imported'])) {
      return (int) $result['imported'];
    }

    // Older shapes report a count under a different key
    return isset($result['updated']) ? (int) $result['updated'] : count($chosen);
  }

  /**
   * Import the ticked collections
   *
   * @param array $data Decoded import file
   * @param array $selection Ticked ids as an isset() lookup
   * @param array $errors Collected errors, by reference
   * @return int Number of lists imported
   */
  private function importTypes($data, $selection, &$errors) {
    $types = [];
    if (isset($data['types']) && is_array($data['types'])) {
      $types = $data['types'];
    } elseif (isset($data['data_objects']['types']) && is_array($data['data_objects']['types'])) {
      $types = $data['data_objects']['types'];
    }

    if (empty($types)) {
      return 0;
    }

    $chosen = [];
    foreach ($types as $slug => $type) {
      if (isset($selection['list:' . $slug])) {
        $chosen[$slug] = $type;
      }
    }

    if (empty($chosen)) {
      return 0;
    }

    $result = $this->dataObjectImporter->import([
      'plugin' => 'yaml-custom-fields',
      'type' => 'data_objects',
      'types' => $chosen,
    ]);

    if (!empty($result['errors'])) {
      $errors = array_merge($errors, (array) $result['errors']);
    }

    return isset($result['types_imported']) ? (int) $result['types_imported'] : 0;
  }
}
