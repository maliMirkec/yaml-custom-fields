<?php

namespace YamlCF\Ajax\Handlers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
use YamlCF\Ajax\AjaxHandler;

class SaveTemplateSettings extends AjaxHandler {
  public function handle() {
    // TODO: Phase 14 - Implement SaveTemplateSettings
    $this->checkCapability();
    $this->sendError('Not yet implemented');
  }
}
