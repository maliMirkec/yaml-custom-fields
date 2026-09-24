<?php

/**
 * BlockRenderer
 * Not implemented yet: field rendering lives in YAML_Custom_Fields::render_fields_flat().
 */
namespace YamlCF\Rendering\FieldRenderers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
use YamlCF\Rendering\BaseFieldRenderer;

class BlockRenderer extends BaseFieldRenderer {
  public function render($field, $value, $context) {
    $this->notImplemented(isset($field['type']) ? $field['type'] : 'block');
  }
}
