<?php

namespace YamlCF\Ajax\Handlers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use YamlCF\Ajax\AjaxHandler;

/**
 * Import data object types and entries from an export file
 * Action: wp_ajax_yaml_cf_import_data_objects
 */
class ImportDataObjects extends AjaxHandler {
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

    $result = $this->importer->import($data);
    if (!$result['success']) {
      $this->sendError($result['message']);
    }

    unset($result['success']);
    $this->sendSuccess($result);
  }
}
