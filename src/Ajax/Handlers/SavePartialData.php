<?php

namespace YamlCF\Ajax\Handlers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use YamlCF\Ajax\AjaxHandler;

/**
 * Save partial data (JSON), sanitized against the partial schema
 * Action: wp_ajax_yaml_cf_save_partial_data
 */
class SavePartialData extends AjaxHandler {
  private $sanitizer;
  private $parser;
  private $cacheManager;

  public function __construct($sanitizer, $parser, $cacheManager) {
    $this->sanitizer = $sanitizer;
    $this->parser = $parser;
    $this->cacheManager = $cacheManager;
  }

  public function handle() {
    $this->verifyRequest();

    $template = $this->postText('template');
    $data = $this->postJson('data');

    $schemas = (array) get_option('yaml_cf_schemas', []);
    $schema = !empty($schemas[$template]) ? $this->parser->parse($schemas[$template]) : null;

    $partial_data = (array) get_option('yaml_cf_partial_data', []);
    $partial_data[$template] = $this->sanitizer->sanitize($data ? $data : [], is_array($schema) ? $schema : null);
    update_option('yaml_cf_partial_data', $partial_data);

    $this->cacheManager->clearDataCaches();

    $this->sendSuccess();
  }
}
