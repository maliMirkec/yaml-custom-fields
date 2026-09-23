<?php

namespace YamlCF\Ajax\Handlers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use YamlCF\Ajax\AjaxHandler;

/**
 * List what an uploaded export file holds, and what it would land on here
 * Action: wp_ajax_yaml_cf_inspect_bundle
 */
class InspectBundle extends AjaxHandler {
  private $catalog;
  private $importer;

  public function __construct($catalog, $importer) {
    $this->catalog = $catalog;
    $this->importer = $importer;
  }

  public function handle() {
    $this->verifyRequest();

    $data = $this->postJson('data');
    if ($data === null) {
      $this->sendError(__('This file could not be read. Choose a .json file exported from YAML Custom Fields.', 'yaml-custom-fields'));
    }

    $validation = $this->importer->validate($data);
    if (!$validation['valid']) {
      $this->sendError($validation['message']);
    }

    $this->sendSuccess([
      'items' => $this->catalog->fromBundle($data),
      'from' => isset($data['site_url']) && is_string($data['site_url']) ? esc_url_raw($data['site_url']) : '',
      'exported_at' => isset($data['exported_at']) && is_string($data['exported_at']) ? sanitize_text_field($data['exported_at']) : '',
    ]);
  }
}
