<?php

namespace YamlCF\Rendering\FieldRenderers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
use YamlCF\Rendering\BaseFieldRenderer;

class CodeRenderer extends BaseFieldRenderer {
  public function render($field, $value, $context) {
    $field_id = $this->getFieldId($field['name'], $context);
    $this->renderLabel($field, $context);
    $options = isset($field['options']) ? $field['options'] : [];
    $language = isset($options['language']) ? $options['language'] : 'html';

    // Decode base64 encoded code field value for display
    $value = $this->decodeCodeField($value);

    echo '<textarea name="yaml_cf[' . esc_attr($field['name']) . ']" id="' . esc_attr($field_id) . '" rows="10" class="large-text code" data-language="' . esc_attr($language) . '">' . esc_textarea($value) . '</textarea>';
  }

  /**
   * Decode a code field value that may be base64 encoded.
   *
   * @param string $value The value to decode.
   * @return string The decoded value.
   */
  private function decodeCodeField($value) {
    if (empty($value) || !is_string($value)) {
      return '';
    }

    // Check for marker prefix
    $marker = '__YAMLCF_B64__';
    if (strpos($value, $marker) === 0) {
      $encoded = substr($value, strlen($marker));
      $decoded = base64_decode($encoded, true);
      if ($decoded !== false) {
        return $decoded;
      }
    }

    // Return as-is if not encoded (legacy data)
    return $value;
  }
}
