<?php

namespace YamlCF\Ajax\Handlers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use YamlCF\Ajax\AjaxHandler;

/**
 * Clear the cached theme template list
 * Action: wp_ajax_yaml_cf_refresh_templates
 */
class RefreshTemplates extends AjaxHandler {
  private $templateCache;
  private $notificationManager;

  public function __construct($templateCache, $notificationManager) {
    $this->templateCache = $templateCache;
    $this->notificationManager = $notificationManager;
  }

  public function handle() {
    $this->verifyRequest();

    $this->templateCache->clear();
    $this->notificationManager->success(__('Template list refreshed.', 'yaml-custom-fields'));

    $this->sendSuccess();
  }
}
