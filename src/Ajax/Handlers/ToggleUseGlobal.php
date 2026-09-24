<?php

namespace YamlCF\Ajax\Handlers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use YamlCF\Ajax\AjaxHandler;

/**
 * Toggle site details for a template
 * Action: wp_ajax_yaml_cf_toggle_use_global
 */
class ToggleUseGlobal extends AjaxHandler {
  public function handle() {
    $this->verifyRequest();

    $template = $this->postText('template');
    $use_global = $this->postText('use_global') === 'true';

    $settings = (array) get_option('yaml_cf_template_settings', []);
    $settings[$template . '_use_global'] = $use_global;
    update_option('yaml_cf_template_settings', $settings, false);

    $this->sendSuccess(['use_global' => $use_global]);
  }
}
