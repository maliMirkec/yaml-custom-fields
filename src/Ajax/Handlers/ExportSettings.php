<?php

namespace YamlCF\Ajax\Handlers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use YamlCF\Ajax\AjaxHandler;

/**
 * Return the settings export as JSON
 * Action: wp_ajax_yaml_cf_export_settings
 */
class ExportSettings extends AjaxHandler {
  private $exporter;

  public function __construct($exporter) {
    $this->exporter = $exporter;
  }

  public function handle() {
    $this->verifyRequest();
    $this->sendSuccess($this->exporter->export());
  }
}
