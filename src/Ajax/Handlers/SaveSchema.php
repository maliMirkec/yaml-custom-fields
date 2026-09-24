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
  private $parser;
  private $diff;

  public function __construct($validator, $parser = null, $diff = null) {
    $this->validator = $validator;
    $this->parser = $parser;
    $this->diff = $diff;
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

    // Work out what this change strands before the old schema is overwritten
    $removed_fields = $this->getRemovedFields(
      isset($schemas[$template]) ? $schemas[$template] : '',
      $schema
    );

    $schemas[$template] = $schema;
    update_option('yaml_cf_schemas', $schemas, false);

    $this->sendSuccess(['removed_fields' => $removed_fields]);
  }

  /**
   * Field names the new schema drops, whose content nothing will display
   *
   * @param string $old_yaml Schema being replaced
   * @param string $new_yaml Schema being saved
   * @return string[]
   */
  private function getRemovedFields($old_yaml, $new_yaml) {
    if ($this->parser === null || $this->diff === null) {
      return [];
    }

    if (!is_string($old_yaml) || trim($old_yaml) === '') {
      return [];
    }

    return $this->diff->removedFieldNames(
      $this->parser->parse($old_yaml),
      is_string($new_yaml) && trim($new_yaml) !== '' ? $this->parser->parse($new_yaml) : null
    );
  }
}
