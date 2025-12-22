<?php

/**
 * Text Field Renderer
 * Renders textarea fields
 */

namespace YamlCF\Rendering\FieldRenderers;


// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
use YamlCF\Rendering\BaseFieldRenderer;

class TextRenderer extends BaseFieldRenderer {
  public function render($field, $value, $context) {
    $field_id = $this->getFieldId($field['name'], $context);

    $this->renderLabel($field, $context);

    $options = isset($field['options']) ? $field['options'] : [];
    $attrs = [
      'name' => 'yaml_cf[' . $field['name'] . ']',
      'id' => $field_id,
      'rows' => 5,
      'class' => 'large-text',
    ];

    if (isset($options['maxlength'])) {
      $attrs['maxlength'] = intval($options['maxlength']);
    }

    echo '<textarea';
    $this->outputAttrs($attrs);
    echo '>' . esc_textarea($value) . '</textarea>';
  }
}
