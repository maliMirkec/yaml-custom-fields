<?php

namespace YamlCF\Ajax\Handlers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use YamlCF\Ajax\AjaxHandler;

/**
 * Get the YAML schema of a template
 * Action: wp_ajax_yaml_cf_get_schema
 */
class GetSchema extends AjaxHandler {
  public function handle() {
    $this->verifyRequest();

    $template = $this->postText('template');
    $schemas = (array) get_option('yaml_cf_schemas', []);

    $this->sendSuccess([
      'schema' => isset($schemas[$template]) ? $schemas[$template] : '',
    ]);
  }
}
