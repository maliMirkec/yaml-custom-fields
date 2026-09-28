<?php

/**
 * Code Renderer
 * A monospaced text box for HTML, CSS or script
 */

namespace YamlCF\Rendering\FieldRenderers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use YamlCF\Helpers\CodeFieldCodec;
use YamlCF\Rendering\BaseFieldRenderer;
use YamlCF\Rendering\BlockFieldRendererInterface;

class CodeRenderer extends BaseFieldRenderer implements BlockFieldRendererInterface {
  public function render($field, $value, $state) {
    // Stored base64-encoded; the writer edits the code itself
    $value = CodeFieldCodec::decode($value);
    echo '<textarea name="yaml_cf[' . esc_attr($state['field_name']) . ']" id="' . esc_attr($state['field_id']) . '" rows="10" class="large-text code" data-language="' . esc_attr($this->language($field)) . '">' . esc_textarea($value) . '</textarea>';
  }

  public function renderInBlock($field, $value, $state) {
    // Stored encoded inside a block as well; this used to show the encoded form
    $value = CodeFieldCodec::decode($value);
    echo '<textarea name="' . esc_attr($this->blockInputName($state)) . '" id="' . esc_attr($state['field_id']) . '" rows="10" class="large-text code" data-language="' . esc_attr($this->language($field)) . '"' . disabled($state['readonly'], true, false) . '>' . esc_textarea($value) . '</textarea>';
  }

  /**
   * @param array $field Field definition
   * @return string The language named in the schema, html by default
   */
  private function language($field) {
    $options = isset($field['options']) ? $field['options'] : [];
    return isset($options['language']) ? $options['language'] : 'html';
  }
}
