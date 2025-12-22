<?php

namespace YamlCF\Ajax\Handlers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
use YamlCF\Ajax\AjaxHandler;

class ExportSettings extends AjaxHandler {
  public function handle() {
    // TODO: Phase 14 - Implement ExportSettings
    $this->checkCapability();
    $this->sendError('Not yet implemented');
  }
}
