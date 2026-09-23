<?php

namespace YamlCF\Ajax\Handlers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use YamlCF\Ajax\AjaxHandler;

/**
 * Save a template schema (validated like the schema editor)
 * Action: wp_ajax_yaml_cf_save_schema
 */
class SaveSchema extends AjaxHandler {
  private $validator;

  public function __construct($validator) {
    $this->validator = $validator;
  }

  public function handle() {
    $this->verifyRequest();

    $template = $this->postText('template');
    // Nonce verified in verifyRequest()
    $schema = isset($_POST['schema']) && is_string($_POST['schema']) ? sanitize_textarea_field(wp_unslash($_POST['schema'])) : '';

    if ($schema !== '') {
      $validation_result = $this->validator->validate($schema, $template);
      if (!$validation_result['valid']) {
        $this->sendError($validation_result['message']);
      }
    }

    $schemas = (array) get_option('yaml_cf_schemas', []);
    $schemas[$template] = $schema;
    update_option('yaml_cf_schemas', $schemas);

    $this->sendSuccess();
  }
}
