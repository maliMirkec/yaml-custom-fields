<?php

namespace YamlCF\Ajax\Handlers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
use YamlCF\Ajax\AjaxHandler;

class ImportDataObjects extends AjaxHandler {
  public function handle() {
    // TODO: Phase 14 - Implement ImportDataObjects
    $this->checkCapability();
    $this->sendError('Not yet implemented');
  }
}
