<?php

namespace YamlCF\Ajax\Handlers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
use YamlCF\Ajax\AjaxHandler;

class ExportPageData extends AjaxHandler {
  public function handle() {
    // TODO: Phase 14 - Implement ExportPageData
    $this->checkCapability();
    $this->sendError('Not yet implemented');
  }
}
