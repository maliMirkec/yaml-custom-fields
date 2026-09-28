<?php

namespace YamlCF\Ajax\Handlers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use YamlCF\Ajax\AjaxHandler;

/**
 * Enable or disable YAML CF for a template
 * Action: wp_ajax_yaml_cf_save_template_settings
 */
class SaveTemplateSettings extends AjaxHandler {
  public function handle() {
    $this->verifyRequest();

    $template = $this->postTemplate();
    $enabled = $this->postText('enabled') === 'true';

    $settings = (array) get_option('yaml_cf_template_settings', []);
    $settings[$template] = $enabled;
    update_option('yaml_cf_template_settings', $settings, false);

    $schemas = (array) get_option('yaml_cf_schemas', []);
    $template_global_schemas = (array) get_option('yaml_cf_template_global_schemas', []);

    $this->sendSuccess([
      'has_schema' => !empty($schemas[$template]),
      'has_template_global_schema' => !empty($template_global_schemas[$template]),
    ]);
  }
}
