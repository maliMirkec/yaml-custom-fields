<?php

namespace YamlCF\Ajax\Handlers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
use YamlCF\Ajax\AjaxHandler;

class ImportSettings extends AjaxHandler {
  public function handle() {
    // TODO: Phase 14 - Implement ImportSettings
    $this->checkCapability();
    $this->sendError('Not yet implemented');
  }
}
