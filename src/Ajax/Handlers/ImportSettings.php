<?php

namespace YamlCF\Ajax\Handlers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use YamlCF\Ajax\AjaxHandler;

/**
 * Import settings from an export file
 * Action: wp_ajax_yaml_cf_import_settings
 */
class ImportSettings extends AjaxHandler {
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

    $result = $this->importer->import($data, $this->postText('merge') === 'true');
    if (!$result['success']) {
      $this->sendError($result['message']);
    }

    $this->sendSuccess([
      'message' => $result['message'],
      'errors' => $result['errors'],
      'imported_from' => isset($data['site_url']) && is_string($data['site_url']) ? esc_url_raw($data['site_url']) : 'unknown',
      'exported_at' => isset($data['exported_at']) && is_string($data['exported_at']) ? sanitize_text_field($data['exported_at']) : 'unknown',
    ]);
  }
}
