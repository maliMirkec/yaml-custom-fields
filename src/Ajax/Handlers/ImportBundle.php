<?php

namespace YamlCF\Ajax\Handlers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use YamlCF\Ajax\AjaxHandler;

/**
 * Import the items ticked in the import preview
 * Action: wp_ajax_yaml_cf_import_bundle
 */
class ImportBundle extends AjaxHandler {
  private $importer;

  public function __construct($importer) {
    $this->importer = $importer;
  }

  public function handle() {
    $this->verifyRequest();

    $data = $this->postJson('data');
    if ($data === null) {
      $this->sendError(__('This file could not be read. Choose a .json file exported from YAML Custom Fields.', 'yaml-custom-fields'));
    }

    // Nonce verified in verifyRequest()
    $selection = isset($_POST['selection']) && is_array($_POST['selection'])
      ? array_map('sanitize_text_field', wp_unslash($_POST['selection']))
      : [];

    if (empty($selection)) {
      $this->sendError(__('Tick at least one thing to import.', 'yaml-custom-fields'));
    }

    $result = $this->importer->import($data, $selection);
    if (!$result['success']) {
      $this->sendError($result['message']);
    }

    $this->sendSuccess([
      'message' => $result['message'],
      'imported' => $result['imported'],
      'errors' => $result['errors'],
    ]);
  }
}
