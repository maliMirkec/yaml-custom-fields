<?php

namespace YamlCF\Ajax\Handlers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use YamlCF\Ajax\AjaxHandler;

/**
 * Get the parsed schema and saved data of a partial
 * Action: wp_ajax_yaml_cf_get_partial_data
 */
class GetPartialData extends AjaxHandler {
  private $parser;

  public function __construct($parser) {
    $this->parser = $parser;
  }

  public function handle() {
    $this->verifyRequest();

    $template = $this->postText('template');
    $schemas = (array) get_option('yaml_cf_schemas', []);

    if (empty($schemas[$template])) {
      $this->sendError(__('This template has no schema yet.', 'yaml-custom-fields'));
    }

    $partial_data = (array) get_option('yaml_cf_partial_data', []);

    $this->sendSuccess([
      'schema' => $this->parser->parse($schemas[$template]),
      'data' => isset($partial_data[$template]) ? $partial_data[$template] : [],
    ]);
  }
}
